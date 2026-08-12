import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


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
