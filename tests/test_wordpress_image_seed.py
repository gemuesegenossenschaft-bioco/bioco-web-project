import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def test_exported_images_reach_import_plan(tmp_path):
    exported = subprocess.run(
        [
            "php",
            "wordpress/scripts/fetch-cms-seed.php",
            "--slug=fixture",
            f"--from-file={ROOT / 'tests/fixtures/wordpress-sections-with-images.json'}",
            "--print",
        ],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    seed = json.loads(exported[exported.index("{") :])

    assert seed["sections"][0]["image_url"] == "https://cms.example.test/single.jpg"
    assert "images" not in seed["sections"][0]
    assert seed["sections"][1]["images"][1]["alt"] == "Card two"
    assert seed["sections"][2]["images"][0]["url"] == "https://cms.example.test/gallery-1.jpg"

    php = """
    define('ABSPATH', __DIR__);
    $ALT_META = [];
    $ALT_WRITES = [];
    // Distinct attachment ids per gallery url so alt writes stay attributable.
    function get_posts($args) {
        $url = $args['meta_value'];
        if ($url === 'https://cms.example.test/missing.jpg') return [];
        $ids = ['https://cms.example.test/gallery-1.jpg' => 61, 'https://cms.example.test/gallery-2.jpg' => 62];
        return [(object) ['ID' => $ids[$url] ?? 42]];
    }
    function get_post_meta($id, $key, $single = false) { global $ALT_META; return $ALT_META[$id][$key] ?? ''; }
    function update_post_meta($id, $key, $value) {
        global $ALT_META, $ALT_WRITES;
        $ALT_META[$id][$key] = $value;
        $ALT_WRITES[] = [$id, $key, $value];
        return true;
    }
    function media_sideload_image($url, $post, $desc, $return) { return (object) ['error' => true]; }
    function is_wp_error($value) { return is_object($value) && !empty($value->error); }
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';
    $plan = bioco_import_build_page_plan(json_decode(stream_get_contents(STDIN), true));
    $resolved = $plan;
    $warnings = [];
    foreach ($resolved as &$item) bioco_import_resolve_pending_images($item['values'], 'apply', $warnings);
    unset($item);
    $altWrites = $ALT_WRITES;
    // Second apply pass over the same plan with the alt meta already in place:
    // must be write-free (idempotency guarantee).
    $ALT_WRITES = [];
    $again = $plan;
    foreach ($again as &$item2) bioco_import_resolve_pending_images($item2['values'], 'apply', $warnings);
    unset($item2);
    echo json_encode([
        'plan' => $plan,
        'resolved' => $resolved,
        'altWrites' => $altWrites,
        'altWritesSecondPass' => $ALT_WRITES,
    ]);
    """
    planned = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        input=json.dumps(seed),
        text=True,
        capture_output=True,
        check=True,
    )
    result = json.loads(planned.stdout)
    plan = {item["block"]: item["values"] for item in result["plan"]}

    assert plan["media-text"]["image"] == {"__bioco_pending_image__": "https://cms.example.test/single.jpg"}
    assert plan["cards-grid"]["cards"][0]["image"] == {"__bioco_pending_image__": "https://cms.example.test/card-1.jpg"}
    # A gallery has no sibling alt field: the seed alt has to travel with the
    # image marker so the resolver can put it on the attachment itself, which
    # is where ACF (and gallery-strip/render.php's $item['alt']) reads it from.
    assert plan["gallery-strip"]["gallery"][0] == {
        "__bioco_pending_image__": "https://cms.example.test/gallery-1.jpg",
        "__bioco_pending_image_alt__": "Gallery one",
    }
    assert plan["gallery-strip"]["gallery"][2] == {
        "__bioco_pending_image__": "https://cms.example.test/gallery-2.jpg",
        "__bioco_pending_image_alt__": "Gallery two",
    }
    assert plan["text-columns"]["image"] == {"__bioco_pending_image__": "https://cms.example.test/columns.jpg"}
    assert plan["text-columns"]["image_alt"] == "Columns"
    resolved = {item["block"]: item["values"] for item in result["resolved"]}
    assert resolved["cards-grid"]["cards"][0]["image"] == 42
    assert resolved["gallery-strip"]["gallery"] == [61, 62]
    assert result["altWrites"] == [
        [61, "_wp_attachment_image_alt", "Gallery one"],
        [62, "_wp_attachment_image_alt", "Gallery two"],
    ]
    assert result["altWritesSecondPass"] == []
    assert resolved["text-columns"]["image"] == 42
    assert not any("image_url" in warning or "image_alt" in warning for item in result["plan"] for warning in item["warnings"])

    real_plan = subprocess.run(
        ["php", "wordpress/scripts/check-seed-plan.php"],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    assert not any(
        block in line and ("image_url" in line or "image_alt" in line)
        for line in real_plan.splitlines()
        for block in ("cards-grid", "gallery-strip", "text-columns")
    )
