import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).parents[1]


def test_export_aborts_before_print_or_write_when_fields_are_unmapped(tmp_path):
    payload = json.loads(
        (ROOT / "tests/fixtures/wordpress-sections-with-images.json").read_text()
    )
    payload["sections"][0]["unexpectedContent"] = "must not be dropped"
    source = tmp_path / "unmapped.json"
    source.write_text(json.dumps(payload))

    for extra_args in (["--print"], [f"--out={tmp_path}"]):
        result = subprocess.run(
            [
                "php",
                "wordpress/scripts/fetch-cms-seed.php",
                "--slug=unmapped-fixture",
                f"--from-file={source}",
                *extra_args,
            ],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=False,
        )

        assert result.returncode == 1
        assert result.stdout == ""
        assert "unexpectedContent" in result.stderr
        assert not (tmp_path / "unmapped-fixture.json").exists()


def test_export_publishes_a_complete_seed_without_leaving_temp_files(tmp_path):
    result = subprocess.run(
        [
            "php",
            "wordpress/scripts/fetch-cms-seed.php",
            "--slug=atomic-fixture",
            f"--from-file={ROOT / 'tests/fixtures/wordpress-sections-with-images.json'}",
            f"--out={tmp_path}",
        ],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    )

    seed_path = tmp_path / "atomic-fixture.json"
    seed = json.loads(seed_path.read_text())
    assert seed["slug"] == "atomic-fixture"
    assert "Geschrieben:" in result.stdout
    assert list(tmp_path.glob(".bioco-seed-*")) == []


def test_export_cleans_temp_file_when_publication_fails(tmp_path):
    target = tmp_path / "atomic-fixture.json"
    target.mkdir()
    result = subprocess.run(
        [
            "php",
            "wordpress/scripts/fetch-cms-seed.php",
            "--slug=atomic-fixture",
            f"--from-file={ROOT / 'tests/fixtures/wordpress-sections-with-images.json'}",
            f"--out={tmp_path}",
        ],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=False,
    )

    assert result.returncode == 1
    assert "konnte nicht veroeffentlicht werden" in result.stderr
    assert target.is_dir()
    assert list(tmp_path.glob(".bioco-seed-*")) == []


def test_event_card_image_second_apply_skips_equal_attachment_but_updates_changed_values():
    php = r'''
    define('ABSPATH', __DIR__);
    $CURRENT_CARD_IMAGE = null;
    $FIELD_WRITES = [];

    function sanitize_title($title) { return 'erntefest'; }
    function wp_slash($value) { return $value; }
    function wp_date($format, $timestamp, $timezone) { return date($format, $timestamp); }
    function wp_timezone() { return new DateTimeZone('Europe/Zurich'); }
    function is_wp_error($value) { return false; }
    function get_posts($args) {
        if ($args['post_type'] === 'event') return [(object) ['ID' => 41]];
        if ($args['post_type'] === 'attachment') return [(object) ['ID' => 73]];
        return [];
    }
    function get_field($field, $postId) {
        global $CURRENT_CARD_IMAGE;
        return $field === 'card_image' ? $CURRENT_CARD_IMAGE : null;
    }
    function update_field($field, $value, $postId) {
        global $FIELD_WRITES;
        $FIELD_WRITES[] = [$field, $value, $postId];
        return true;
    }

    require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/collections.php';

    $item = [
        'title' => 'Erntefest',
        'cardImage' => 'https://cms.example.test/event.jpg',
    ];
    $cases = [
        'equal' => ['ID' => 73],
        'different' => ['ID' => 74],
        'missing' => null,
    ];
    $results = [];
    foreach ($cases as $name => $current) {
        $CURRENT_CARD_IMAGE = $current;
        $FIELD_WRITES = [];
        $report = bioco_import_report_new();
        bioco_import_import_event_item($item, 'apply', false, $report);
        $results[$name] = [
            'writes' => $FIELD_WRITES,
            'cardRows' => array_values(array_filter(
                $report['rows'],
                fn($row) => $row['field'] === 'card_image'
            )),
        ];
    }
    echo json_encode($results);
    '''
    result = json.loads(subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout)

    assert result["equal"]["writes"] == []
    assert [row["status"] for row in result["equal"]["cardRows"]] == ["ok-equal"]
    assert result["different"]["writes"] == [["card_image", 73, 41]]
    assert [row["status"] for row in result["different"]["cardRows"]] == ["update"]
    assert result["missing"]["writes"] == [["card_image", 73, 41]]
    assert [row["status"] for row in result["missing"]["cardRows"]] == ["update"]


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
    $ALT_META = [61 => ['_wp_attachment_image_alt' => 'Existing gallery alt']];
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
        'altMeta' => $ALT_META,
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
    assert {key: plan["media-text"][key] for key in (
        "container_width", "media_side", "media_width", "media_ratio",
        "media_fit", "vertical_align", "gap", "rounded",
    )} == {
        "container_width": "xl",
        "media_side": "left",
        "media_width": "40",
        "media_ratio": "16:9",
        "media_fit": "contain",
        "vertical_align": "start",
        "gap": "sm",
        "rounded": "none",
    }
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
    assert result["altWrites"] == [[62, "_wp_attachment_image_alt", "Gallery two"]]
    assert result["altMeta"]["61"]["_wp_attachment_image_alt"] == "Existing gallery alt"
    assert result["altWritesSecondPass"] == []
    assert resolved["text-columns"]["image"] == 42
    assert not any("image_url" in warning or "image_alt" in warning for item in result["plan"] for warning in item["warnings"])

    media_fields = json.loads((
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/acf-json/group_bioco_block_media_text.json"
    ).read_text())["fields"]
    assert {
        "container_width", "media_width", "media_ratio", "media_fit",
        "vertical_align", "gap", "rounded",
    } <= {field["name"] for field in media_fields}

    media_render = (
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/blocks/media-text/render.php"
    ).read_text()
    for field in ("container_width", "media_width", "media_ratio", "media_fit", "vertical_align", "gap", "rounded"):
        assert f"get_field('{field}')" in media_render
        assert f"data-{field.replace('_', '-')}" in media_render

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
