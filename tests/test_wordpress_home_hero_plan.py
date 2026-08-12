import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def test_home_plan_starts_with_the_approved_hero():
    php = r'''
    define('ABSPATH', __DIR__);
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
    $seed = json_decode(file_get_contents('wordpress/content-seed/home.json'), true);
    echo json_encode(bioco_import_build_page_plan($seed)[0]);
    '''
    item = json.loads(subprocess.run(
        ["php", "-r", php], cwd=ROOT, text=True, capture_output=True, check=True
    ).stdout)

    assert item["block"] == "hero"
    assert item["acf_group"] == "group_bioco_block_hero"
    assert item["values"]["headline"] == "Gemeinsam\nGemüse anbauen"
    assert item["values"]["subtitle"] == "Solidarische Landwirtschaft\nin Baden"
    assert item["values"]["image"]["__bioco_pending_image__"].endswith(
        "/frontseitestartseite.jpg"
    )
