import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def test_duplicate_section_markers_preserve_block_order():
    php = r"""
    define('ABSPATH', __DIR__);
    function parse_blocks($content) {
        return [
            ['blockName' => null, 'innerHTML' => '<!-- bioco:section gruppen -->'],
            ['blockName' => 'bioco/rich-text', 'attrs' => ['data' => ['title' => 'Gruppen']]],
            ['blockName' => null, 'innerHTML' => '<!-- bioco:section gruppen -->'],
            ['blockName' => 'bioco/group-cards', 'attrs' => ['data' => ['columns' => '3']]],
        ];
    }
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/verify.php';
    $blocks = bioco_import_parse_marked_blocks('ignored');
    echo json_encode([
        'parsed' => $blocks,
        'first' => bioco_import_take_marked_block($blocks, 'gruppen'),
        'second' => bioco_import_take_marked_block($blocks, 'gruppen'),
    ]);
    """
    result = subprocess.run(
        ["php", "-r", php], cwd=ROOT, text=True, capture_output=True, check=True
    )

    output = json.loads(result.stdout)
    expected = [
        {"blockName": "bioco/rich-text", "data": {"title": "Gruppen"}},
        {"blockName": "bioco/group-cards", "data": {"columns": "3"}},
    ]
    assert output["parsed"]["gruppen"] == expected
    assert [output["first"], output["second"]] == expected
