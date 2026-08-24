import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"


def _wir_plan():
    php = r'''
    define('ABSPATH', __DIR__);
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
    $seed = json_decode(file_get_contents('wordpress/content-seed/wir.json'), true);
    echo json_encode(bioco_import_build_page_plan($seed));
    '''
    result = subprocess.run(
        ["php", "-r", php], cwd=ROOT, text=True, capture_output=True, check=True
    )
    return json.loads(result.stdout)


def test_hof_team_container_width_reaches_cards_grid_plan():
    item = next(
        item for item in _wir_plan()
        if item["block"] == "cards-grid" and "hof_team" in item["section_ids"]
    )

    assert item["values"]["container_width"] == "xl"
    assert not any("containerWidth" in warning for warning in item.get("warnings", []))


def test_timeline_headings_become_titles_and_body_stays_rich_text():
    item = next(item for item in _wir_plan() if item["block"] == "timeline")
    expected = {
        "Gründung": "Die Gründung von biocò fand am 15.11.2013 statt.",
        "Erste Gartensaison": "War dann die erste Gartensaison",
        "Packraum": "Der Packraum wird erstellt",
        "Mitgliederwachstum": "Weiteres Wachstum der Mitgliederzahl",
        "Laufenten": "Wir haben zwei Pärchen Laufenten",
        "Neue Website": "Launch der neuen Website",
    }

    assert [row["title"] for row in item["values"]["items"]] == list(expected)
    for row in item["values"]["items"]:
        assert row["text"].startswith("<p>")
        assert expected[row["title"]] in row["text"]
        assert "<h2>" not in row["text"]
    assert not any("geglättet" in warning or "geglattet" in warning for warning in item.get("warnings", []))


def test_wide_grid_blocks_declare_and_render_container_width():
    for block in ("cards_grid", "gallery_strip"):
        fields = json.loads(
            (CORE / f"acf-json/group_bioco_block_{block}.json").read_text()
        )["fields"]
        container = next(field for field in fields if field.get("name") == "container_width")
        assert container["default_value"] == "xl"
        assert set(container["choices"]) == {"sm", "md", "lg", "xl", "full"}

        render = (CORE / f"blocks/{block.replace('_', '-')}/render.php").read_text()
        assert "get_field('container_width')" in render
        assert 'data-container="<?php echo esc_attr($container_width); ?>"' in render

    css = (CORE / "assets/bioco-blocks.css").read_text()
    for selector in (".cms-cards-grid", ".cms-gallery-strip"):
        for width in ("sm", "md", "lg", "xl", "full"):
            assert f"{selector}[data-container='{width}']" in css
