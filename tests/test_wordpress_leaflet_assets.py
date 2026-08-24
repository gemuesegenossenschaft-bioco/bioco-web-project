import json
import re
from pathlib import Path


ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"


def test_map_blocks_use_registered_leaflet_assets_only_when_rendered():
    core = (CORE / "bioco-core.php").read_text(encoding="utf-8")

    assert "plugin_dir_url(__FILE__) . 'assets/vendor/leaflet/'" in core
    assert re.search(
        r"wp_register_style\(\s*'bioco-leaflet'.*?\$leaflet_url\s*\.\s*'leaflet\.css'",
        core,
        re.DOTALL,
    )
    for block_name in ("depot-map", "geisshof-map"):
        handle = f"bioco-{block_name}-view-script"
        metadata = json.loads((CORE / "blocks" / block_name / "block.json").read_text())

        assert metadata["viewScript"] == handle
        assert metadata["viewStyle"] == "bioco-leaflet"
        assert re.search(
            rf"wp_register_script\(\s*'{handle}'.*?blocks/{block_name}/view\.js.*?\['bioco-leaflet'\]",
            core,
            re.DOTALL,
        )

    assert "wp_enqueue_script('bioco-leaflet'" not in core
    assert "wp_enqueue_style('bioco-leaflet'" not in core


def test_leaflet_css_marker_images_resolve_inside_vendored_directory():
    vendor = CORE / "assets/vendor/leaflet"
    css = (vendor / "leaflet.css").read_text(encoding="utf-8")
    image_urls = set(re.findall(r"url\((?:['\"])?(images/[^)'\"]+)", css))

    assert "images/marker-icon.png" in image_urls
    assert image_urls
    assert all((vendor / relative_url).is_file() for relative_url in image_urls)
