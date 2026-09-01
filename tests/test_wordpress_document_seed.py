"""Issue #151: Dokumentverweise im Seed.

Die fünf PDFs (zwei Statuten, drei Intranet-Dokumente) liegen als Quelle im
Repo unter content-seed/documents/. Der Import lädt sie einmal in die
Mediathek, taggt sie mit _bioco_import_source_url = "documents/<datei>" und
schreibt jeden "documents/…"-Verweis (Button-href und Rich-Text-Link) auf die
Attachment-URL um. Ein erneuter Lauf ohne Änderung schreibt nichts; Dry-Run
und Verify importieren nie.
"""

import json
import os
import subprocess
from pathlib import Path

ROOT = Path(__file__).parents[1]

DOCUMENTS = [
    "13-11-15_Statuten_bioco.pdf",
    "2212_Betriebsreglement.pdf",
    "2510_fahrspesenrueckforderung.pdf",
    "gemuese_ausliefertour_dienstag_2026_04.pdf",
    "gemuese_ausliefertour_freitag_2026_04.pdf",
]


def test_documents_exist_in_both_seed_trees():
    for root in ("wordpress", "cms"):
        for name in DOCUMENTS:
            path = ROOT / root / "content-seed/documents" / name
            assert path.is_file(), path
            assert path.read_bytes()[:5] == b"%PDF-", name


def test_seeds_reference_documents_instead_of_legacy_hosts():
    statuten = json.loads((ROOT / "wordpress/content-seed/statuten.json").read_text())
    hrefs = [
        button["href"]
        for section in statuten["sections"]
        for button in section.get("buttons", [])
    ]
    assert "documents/13-11-15_Statuten_bioco.pdf" in hrefs
    assert "documents/2212_Betriebsreglement.pdf" in hrefs
    assert not any(h.startswith("/statuten/") for h in hrefs)

    intranet = (ROOT / "wordpress/content-seed/intranet.json").read_text()
    assert "cms.bioco.ch" not in intranet
    assert f'documents/{DOCUMENTS[2]}' in intranet
    assert f'documents/{DOCUMENTS[3]}' in intranet
    assert f'documents/{DOCUMENTS[4]}' in intranet


DRIVER = r"""
define('ABSPATH', __DIR__);
$ATTACHMENTS = json_decode(getenv('DOC_PREATTACHED') ?: '{}', true); // documents path => id
$NEXT_ID = 901;
$IMPORT_CALLS = [];

function get_posts($args) {
    global $ATTACHMENTS;
    $found = $ATTACHMENTS[$args['meta_value']] ?? null;
    return $found === null ? [] : [(object) ['ID' => $found]];
}
function wp_tempnam($name) { return tempnam(sys_get_temp_dir(), 'bioco-doc-'); }
function media_handle_sideload($fileArray, $postId) {
    global $NEXT_ID, $IMPORT_CALLS;
    if (!is_file($fileArray['tmp_name'])) return (object) ['error' => ['no-file']];
    $IMPORT_CALLS[] = $fileArray['name'];
    return $NEXT_ID++;
}
function is_wp_error($value) { return is_object($value) && isset($value->error); }
function wp_get_attachment_url($id) { return 'https://staging.bioco.test/wp-content/uploads/doc-' . $id . '.pdf'; }
function update_post_meta($id, $key, $value) { global $ATTACHMENTS; if ($key === '_bioco_import_source_url') $ATTACHMENTS[$value] = $id; return true; }
function wp_delete_file($path) { @unlink($path); }

require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';

$mode = getenv('DOC_MODE');
$warnings = [];
$results = [];
foreach (['statuten', 'intranet'] as $slug) {
    $seed = json_decode(file_get_contents("wordpress/content-seed/{$slug}.json"), true);
    $seed['_bioco_seed_dir'] = 'wordpress/content-seed';
    $plan = bioco_import_build_page_plan($seed);
    foreach ($plan as &$item) {
        bioco_import_resolve_pending_images($item['values'], $mode, $warnings);
        bioco_import_resolve_pending_documents($item['values'], $seed, $mode, $warnings);
    }
    unset($item);
    $results[$slug] = array_map(
        fn($i) => ['block' => $i['block'], 'values' => $i['values']],
        $plan
    );
}
echo json_encode([
    'results' => $results,
    'importCalls' => $IMPORT_CALLS,
    'attachments' => $ATTACHMENTS,
    'warnings' => $warnings,
]);
"""


def _run(mode: str, preattached: dict | None = None) -> dict:
    env = {**os.environ, "DOC_MODE": mode, "DOC_PREATTACHED": json.dumps(preattached or {})}
    return json.loads(subprocess.run(
        ["php", "-r", DRIVER],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
        env=env,
    ).stdout)


def test_apply_resolves_every_document_href_to_the_media_library():
    result = _run("apply")

    hrefs = [
        v["href"]
        for item in result["results"]["statuten"]
        for v in item["values"].get("buttons", [])
        if "doc-" in v["href"]
    ]
    assert sorted(hrefs) == sorted([
        "https://staging.bioco.test/wp-content/uploads/doc-901.pdf",
        "https://staging.bioco.test/wp-content/uploads/doc-902.pdf",
    ])
    assert not any(h.startswith("documents/") for h in hrefs)

    intra_text = next(
        item["values"]["text"]
        for item in result["results"]["intranet"]
        if item["block"] == "rich-text" and "Ausliefertour" in item["values"].get("text", "")
    )
    assert "documents/" not in intra_text
    assert "cms.bioco.ch" not in intra_text
    for suffix in ("doc-903", "doc-904", "doc-905"):
        assert suffix in intra_text

    assert sorted(result["importCalls"]) == sorted(DOCUMENTS)


def test_attachments_carry_the_import_source_tag():
    result = _run("apply")
    # Idempotency contract: the source tag maps each documents path to its
    # attachment, so a re-run can find the attachment without re-importing.
    assert sorted(result["attachments"]) == sorted(f"documents/{name}" for name in DOCUMENTS)


def test_unchanged_rerun_is_write_free():
    first = _run("apply")
    preattached = first["attachments"]
    # Simulate the tagging done by the real importer: attachments exist with
    # _bioco_import_source_url set. A second apply must resolve purely from
    # the tag and never call media_handle_sideload again.
    assert _run("apply", preattached)["importCalls"] == []
    assert _run("verify", preattached)["importCalls"] == []


def test_verify_and_dry_run_never_import():
    result = _run("verify")
    assert result["importCalls"] == []
    intra_text = next(
        item["values"]["text"]
        for item in result["results"]["intranet"]
        if item["block"] == "rich-text" and "Ausliefertour" in item["values"].get("text", "")
    )
    # Unresolved: hrefs stay as seeded, so verify reports a clean mismatch
    # instead of half-rewritten markup against a never-imported page.
    assert "documents/" in intra_text
    assert "cms.bioco.ch" not in intra_text