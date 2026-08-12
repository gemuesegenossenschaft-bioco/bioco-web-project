import subprocess
import json
from pathlib import Path


ROOT = Path(__file__).parents[1]


def test_scf_clone_companion_keys_use_registered_source_keys():
    php = r'''
    define('ABSPATH', __DIR__);
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/acf-fields.php';
    $fields = [
        [
            'name' => 'image',
            'key' => 'field_synthetic_image',
            '__key' => 'field_source_image',
            'type' => 'image',
        ],
        [
            'name' => 'gallery',
            'key' => 'field_synthetic_gallery',
            '__key' => 'field_source_gallery',
            'type' => 'gallery',
        ],
        [
            'name' => 'buttons',
            'key' => 'field_synthetic_buttons',
            '__key' => 'field_source_buttons',
            'type' => 'repeater',
            'sub_fields' => [
                ['name' => 'text', 'key' => 'field_button_text', 'type' => 'text'],
            ],
        ],
    ];
    echo json_encode(bioco_import_acf_block_data([
        'image' => 23,
        'gallery' => [29, 30],
        'buttons' => [['text' => 'Mehr erfahren']],
    ], $fields));
    '''
    data = json.loads(subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout)

    assert data["_image"] == "field_source_image"
    assert data["_gallery"] == "field_source_gallery"
    assert data["_buttons"] == "field_source_buttons"
    assert data["_buttons_0_text"] == "field_button_text"


def test_scf_block_name_and_post_content_slashing():
    php = r'''
    define('ABSPATH', __DIR__);
    define('BIOCO_IMPORT_BLOCKS_DIR', getcwd() . '/wordpress/web/app/mu-plugins/bioco-core/blocks');
    function acf_get_field_group($key) { return ['key' => $key]; }
    function acf_get_fields($group) {
        return [['name' => 'text', 'key' => 'field_test_text', 'type' => 'wysiwyg']];
    }
    function is_wp_error($value) { return false; }
    function serialize_block($block) {
        $attrs = json_encode($block['attrs'], JSON_HEX_TAG | JSON_HEX_AMP);
        return '<!-- wp:' . $block['blockName'] . ' ' . $attrs . ' /-->';
    }
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/acf-fields.php';
    $warnings = [];
    $errors = [];
    $markup = bioco_import_serialize_acf_block(
        'media-text',
        'group_test',
        ['text' => '<p>Hello</p>'],
        $warnings,
        $errors
    );
    echo json_encode([
        'markup' => $markup,
        'stored' => stripslashes(addslashes($markup)),
    ]);
    '''
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    payload = __import__("json").loads(result)
    markup = payload["markup"]

    assert markup.startswith("<!-- wp:bioco/media-text ")
    assert r"\u003C" in markup and r"\u003E" in markup
    assert payload["stored"] == markup

    pages = (
        ROOT / "wordpress/web/app/mu-plugins/bioco-import/includes/pages.php"
    ).read_text()
    assert pages.count("'post_content' => wp_slash($desiredContent)") == 2

    collections = (
        ROOT / "wordpress/web/app/mu-plugins/bioco-import/includes/collections.php"
    ).read_text()
    assert collections.count("'post_content' => wp_slash($content)") == 2
