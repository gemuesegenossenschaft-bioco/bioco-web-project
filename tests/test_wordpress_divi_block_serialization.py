import subprocess
import json
import re
from pathlib import Path


ROOT = Path(__file__).parents[1]

# Byte-faithful WordPress core model of serialize_block / serialize_blocks.
# Iterates innerContent exactly; null maps to next serialized child;
# no added or trimmed newlines. Empty final content emits self-closing syntax.
WP_SERIALIZER_STUB = r'''
function _wp_serialize_block($block) {
    $name = $block['blockName'];
    $attrs = empty($block['attrs']) ? '' : ' ' . json_encode($block['attrs'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $open = '<!-- wp:' . $name . $attrs . ' -->';

    $content = '';
    $child_idx = 0;
    $inner_content = $block['innerContent'] ?? [];
    foreach ($inner_content as $piece) {
        if ($piece === null) {
            if ($child_idx < count($block['innerBlocks'] ?? [])) {
                $content .= _wp_serialize_block($block['innerBlocks'][$child_idx]);
                $child_idx++;
            }
        } else {
            $content .= $piece;
        }
    }

    if ($content === '') {
        return '<!-- wp:' . $name . (empty($attrs) ? ' ' : $attrs . ' ') . '/-->';
    }
    return $open . $content . '<!-- /wp:' . $name . ' -->';
}
function serialize_blocks($blocks) {
    $out = '';
    foreach ($blocks as $b) {
        $out .= _wp_serialize_block($b);
    }
    return $out;
}
'''


# ---------------------------------------------------------------------------
# Tests
# ---------------------------------------------------------------------------


def test_stub_self_closes_on_empty_content():
    """Meta-test: the stub must emit WordPress self-closing syntax when content is empty."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        + WP_SERIALIZER_STUB +
        "$block = ['blockName' => 'divi/text', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => ['']];\n"
        "$out = _wp_serialize_block($block);\n"
        "echo json_encode(['out' => $out]);\n"
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    payload = json.loads(result)
    # WordPress core self-closes when block_content is empty: one comment only.
    assert payload["out"] == "<!-- wp:divi/text /-->"


def test_divi_text_block_structure():
    """Leaf text block has correct standard keys and rich HTML only in attrs."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        + WP_SERIALIZER_STUB +
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "$block = bioco_import_divi_block('divi/text', [\n"
        "    'content' => ['innerContent' => ['desktop' => ['value' => '<p>Hello</p>']]]\n"
        "]);\n"
        "echo json_encode($block);\n"
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    block = json.loads(result)

    assert block["blockName"] == "divi/text"
    assert block["attrs"] == {
        "content": {"innerContent": {"desktop": {"value": "<p>Hello</p>"}}}
    }
    assert block["innerBlocks"] == []
    assert block["innerHTML"] == ""
    # Leaf must force paired comments — innerContent must be non-empty string
    # so WordPress does not self-close.
    assert block["innerContent"] == ["\n"]


def test_divi_text_block_paired_comments():
    """Leaf divi/text serializes to paired open/close comments with only whitespace."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        + WP_SERIALIZER_STUB +
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "$blocks = [\n"
        "    bioco_import_divi_block('divi/text', [\n"
        "        'content' => ['innerContent' => ['desktop' => ['value' => '<p>Hello</p>']]]\n"
        "    ])\n"
        "];\n"
        "$markup = bioco_import_serialize_divi_blocks($blocks);\n"
        "echo json_encode(['markup' => $markup]);\n"
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    payload = json.loads(result)
    markup = payload["markup"]

    # Must be paired comments, not self-closing
    assert "<!-- wp:divi/text" in markup
    assert "<!-- /wp:divi/text -->" in markup
    # No self-closing form
    assert "<!-- wp:divi/text /-->" not in markup
    assert "<!-- wp:divi/text /><!-- /wp:divi/text -->" not in markup
    # Rich HTML must NOT appear in serialized output
    assert "<p>Hello</p>" not in markup


def test_divi_nested_section_row_column_text():
    """Nesting: section > row > column > text produces correct block tree."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        + WP_SERIALIZER_STUB +
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "$block = bioco_import_divi_block('divi/section', [], [\n"
        "    bioco_import_divi_block('divi/row', [], [\n"
        "        bioco_import_divi_block('divi/column', [], [\n"
        "            bioco_import_divi_block('divi/text', [\n"
        "                'content' => ['innerContent' => ['desktop' => ['value' => '<p>Nested</p>']]]\n"
        "            ])\n"
        "        ])\n"
        "    ])\n"
        "]);\n"
        "echo json_encode($block);\n"
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    block = json.loads(result)

    assert block["blockName"] == "divi/section"
    assert block["attrs"] == []
    assert block["innerHTML"] == ""
    assert block["innerContent"] == [None]

    row = block["innerBlocks"][0]
    assert row["blockName"] == "divi/row"
    assert row["innerContent"] == [None]

    col = row["innerBlocks"][0]
    assert col["blockName"] == "divi/column"
    assert col["innerContent"] == [None]

    txt = col["innerBlocks"][0]
    assert txt["blockName"] == "divi/text"
    assert txt["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<p>Nested</p>"
    assert txt["innerContent"] == ["\n"]


def test_divi_serialize_blocks_delegates_to_serialize_blocks():
    """Serializer delegates to WordPress serialize_blocks; tests use faithful stub."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        + WP_SERIALIZER_STUB +
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "$blocks = [\n"
        "    bioco_import_divi_block('divi/text', [\n"
        "        'content' => ['innerContent' => ['desktop' => ['value' => '<p>A</p>']]]\n"
        "    ]),\n"
        "    bioco_import_divi_block('divi/text', [\n"
        "        'content' => ['innerContent' => ['desktop' => ['value' => '<p>B</p>']]]\n"
        "    ]),\n"
        "];\n"
        "$markup = bioco_import_serialize_divi_blocks($blocks);\n"
        "echo json_encode(['markup' => $markup]);\n"
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    payload = json.loads(result)
    markup = payload["markup"]

    assert markup.count("<!-- wp:divi/text ") == 2
    assert markup.count("<!-- /wp:divi/text -->") == 2
    assert "<p>A</p>" not in markup
    assert "<p>B</p>" not in markup


def test_divi_serialize_blocks_empty_list():
    """Empty list returns empty string."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "echo json_encode(['markup' => bioco_import_serialize_divi_blocks([])]);\n"
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    payload = json.loads(result)
    assert payload["markup"] == ""


def test_divi_block_rejects_invalid_names():
    """Only the four allowed block names are accepted; others throw InvalidArgumentException."""
    for bad_name in ["core/html", "divi/code", "divi/shortcode-module", "paragraph", "divi/unknown", "text", "section"]:
        php = (
            "define('ABSPATH', __DIR__);\n"
            "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
            "try {\n"
            f"    bioco_import_divi_block('{bad_name}');\n"
            "    echo json_encode(['ok' => true]);\n"
            "} catch (InvalidArgumentException $e) {\n"
            "    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);\n"
            "}\n"
        )
        result = subprocess.run(
            ["php", "-r", php],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=True,
        ).stdout
        payload = json.loads(result)
        assert payload["ok"] is False, f"Expected rejection for {bad_name}"
        assert bad_name in payload["msg"]


def test_divi_serialize_blocks_exact_comment_order():
    """Nested serialization produces exact comment names in section-row-column-text order."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        + WP_SERIALIZER_STUB +
        "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        "$blocks = [\n"
        "    bioco_import_divi_block('divi/section', [], [\n"
        "        bioco_import_divi_block('divi/row', [], [\n"
        "            bioco_import_divi_block('divi/column', [], [\n"
        "                bioco_import_divi_block('divi/text', [\n"
        "                    'content' => ['innerContent' => ['desktop' => ['value' => '<p>Hi</p>']]]\n"
        "                ])\n"
        "            ])\n"
        "        ])\n"
        "    ]),\n"
        "];\n"
        "$markup = bioco_import_serialize_divi_blocks($blocks);\n"
        "echo json_encode(['markup' => $markup]);\n"
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    payload = json.loads(result)
    markup = payload["markup"]

    # Extract comment tokens with regex; compare the eight exact names in order.
    tokens = re.findall(r'<!-- (/?wp:([^ ]+))', markup)
    names = [t[1] for t in tokens]
    assert names == [
        "divi/section", "divi/row", "divi/column", "divi/text",
        "divi/text", "divi/column", "divi/row", "divi/section",
    ]
