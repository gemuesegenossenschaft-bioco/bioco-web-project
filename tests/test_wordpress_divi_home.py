import subprocess
import json
import base64
from pathlib import Path


ROOT = Path(__file__).parents[1]

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


def _run_php(code: str) -> dict:
    """Run a PHP snippet and return the JSON-decoded stdout."""
    result = subprocess.run(
        ["php", "-r", code],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    )
    return json.loads(result.stdout)


def _php_preamble() -> str:
    """PHP preamble: define ABSPATH, stub serializer, require native Divi composition."""
    return (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        "function wp_get_attachment_image_url($id, $size = 'full') {\n"
        "    static $map = [42 => 'https://example.com/hero.jpg', 99 => 'https://example.com/feature.jpg'];\n"
        "    return $map[$id] ?? false;\n"
        "}\n"
        + WP_SERIALIZER_STUB
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
    )


def _php_with_item(item: dict) -> str:
    """Build PHP code that passes the resolved plan item via base64 JSON decode."""
    payload = base64.b64encode(json.dumps(item).encode("utf-8")).decode("ascii")
    return (
        _php_preamble()
        + f"$item = json_decode(base64_decode('{payload}'), true);\n"
        + "$tree = Bioco_Import_Divi_Composer::section($item); echo json_encode($tree);"
    )


def _all_block_names(block):
    """Recursively yield every blockName in the tree."""
    yield block["blockName"]
    for child in block.get("innerBlocks", []):
        yield from _all_block_names(child)


# ---------------------------------------------------------------------------
# Hero
# ---------------------------------------------------------------------------


def test_hero_section_structure():
    """Hero block produces section > row > column with image, heading, text."""
    item = {
        "block": "hero",
        "values": {
            "headline": "Willkommen\nbei Bioco",
            "subtitle": "Gemeinsam\ngärtnern",
            "image": 42,
            "image_alt": "Hero-Bild",
        },
    }
    tree = _run_php(_php_with_item(item))

    assert tree["blockName"] == "divi/section"
    assert tree["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-hero"

    row = tree["innerBlocks"][0]
    assert row["blockName"] == "divi/row"
    assert row["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-hero-row"
    assert row["attrs"]["module"]["advanced"]["columnStructure"]["desktop"]["value"] == "4_4"

    col = row["innerBlocks"][0]
    assert col["blockName"] == "divi/column"
    assert col["attrs"]["module"]["advanced"]["type"]["desktop"]["value"] == "4_4"
    assert col["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-hero-column"

    # Order inside column: image, heading, text
    img, h1, txt = col["innerBlocks"]

    assert img["blockName"] == "divi/image"
    assert img["attrs"]["image"]["innerContent"]["desktop"]["value"]["src"] == "https://example.com/hero.jpg"
    assert img["attrs"]["image"]["innerContent"]["desktop"]["value"]["id"] == 42
    assert img["attrs"]["image"]["innerContent"]["desktop"]["value"]["alt"] == "Hero-Bild"
    assert img["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-hero-image"

    assert h1["blockName"] == "divi/heading"
    assert h1["attrs"]["title"]["innerContent"]["desktop"]["value"] == "Willkommen<br>bei Bioco"
    assert h1["attrs"]["title"]["decoration"]["font"]["font"]["desktop"]["value"]["headingLevel"] == "h1"
    assert h1["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-hero-title"

    assert txt["blockName"] == "divi/text"
    assert txt["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<p>Gemeinsam<br>gärtnern</p>"
    assert txt["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-hero-subtitle"


def test_hero_omits_image_when_url_unavailable():
    """Hero image is omitted when wp_get_attachment_image_url returns false."""
    item = {
        "block": "hero",
        "values": {
            "headline": "Hi",
            "subtitle": "Ho",
            "image": 999,
            "image_alt": "Missing",
        },
    }
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        "function wp_get_attachment_image_url($id, $size = 'full') { return false; }\n"
        + WP_SERIALIZER_STUB
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + f"$item = json_decode(base64_decode('{base64.b64encode(json.dumps(item).encode('utf-8')).decode('ascii')}'), true);\n"
        + "$tree = Bioco_Import_Divi_Composer::section($item); echo json_encode($tree);"
    )
    tree = _run_php(php)
    col = tree["innerBlocks"][0]["innerBlocks"][0]
    names = [b["blockName"] for b in col["innerBlocks"]]
    assert "divi/image" not in names
    assert names == ["divi/heading", "divi/text"]


def test_hero_headline_crlf_normalization():
    """Hero headline converts CRLF and CR to <br>."""
    item = {
        "block": "hero",
        "values": {
            "headline": "A\r\nB\rC",
            "subtitle": "D",
            "image": 42,
            "image_alt": "",
        },
    }
    tree = _run_php(_php_with_item(item))
    h1 = tree["innerBlocks"][0]["innerBlocks"][0]["innerBlocks"][1]
    assert h1["attrs"]["title"]["innerContent"]["desktop"]["value"] == "A<br>B<br>C"


def test_hero_subtitle_wrapped_in_p():
    """Hero subtitle is wrapped in <p> with <br> for newlines."""
    item = {
        "block": "hero",
        "values": {
            "headline": "H",
            "subtitle": "Line1\nLine2",
            "image": 42,
            "image_alt": "",
        },
    }
    tree = _run_php(_php_with_item(item))
    txt = tree["innerBlocks"][0]["innerBlocks"][0]["innerBlocks"][2]
    assert txt["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<p>Line1<br>Line2</p>"


# ---------------------------------------------------------------------------
# Media-text
# ---------------------------------------------------------------------------


def test_media_text_left_structure():
    """media-text with media_side=left produces image column then content column."""
    item = {
        "block": "media-text",
        "values": {
            "title": "Unser Anbau",
            "text": "<p>Wir bauen <strong>bio</strong> an.</p>",
            "image": 99,
            "image_alt": "Feld",
            "media_side": "left",
            "style_variant": "feature",
            "buttons": [
                {"text": "Mehr", "href": "/anbau", "variant": "primary"},
                {"text": "Kontakt", "href": "/kontakt", "variant": "secondary"},
            ],
        },
    }
    tree = _run_php(_php_with_item(item))

    assert tree["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature"

    row = tree["innerBlocks"][0]
    assert row["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-row"
    assert row["attrs"]["module"]["advanced"]["columnStructure"]["desktop"]["value"] == "1_2,1_2"

    col1, col2 = row["innerBlocks"]
    assert col1["attrs"]["module"]["advanced"]["type"]["desktop"]["value"] == "1_2"
    assert col1["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-media"
    assert col2["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-content"

    img = col1["innerBlocks"][0]
    assert img["blockName"] == "divi/image"
    assert img["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-image"

    h2 = col2["innerBlocks"][0]
    assert h2["blockName"] == "divi/heading"
    assert h2["attrs"]["title"]["innerContent"]["desktop"]["value"] == "Unser Anbau"
    assert h2["attrs"]["title"]["decoration"]["font"]["font"]["desktop"]["value"]["headingLevel"] == "h2"
    assert h2["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-title"

    txt = col2["innerBlocks"][1]
    assert txt["blockName"] == "divi/text"
    assert txt["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<p>Wir bauen <strong>bio</strong> an.</p>"
    assert txt["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-text"

    btn1 = col2["innerBlocks"][2]
    assert btn1["blockName"] == "divi/button"
    assert btn1["attrs"]["button"]["innerContent"]["desktop"]["value"]["text"] == "Mehr"
    assert btn1["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkUrl"] == "/anbau"
    assert btn1["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkTarget"] == "off"
    assert btn1["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-button bioco-home-button--primary"

    btn2 = col2["innerBlocks"][3]
    assert btn2["attrs"]["button"]["innerContent"]["desktop"]["value"]["text"] == "Kontakt"
    assert btn2["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkUrl"] == "/kontakt"
    assert btn2["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-button bioco-home-button--secondary"


def test_media_text_right_swaps_columns():
    """media_side=right places content column first, image column second."""
    item = {
        "block": "media-text",
        "values": {
            "title": "T",
            "text": "B",
            "image": 99,
            "image_alt": "X",
            "media_side": "right",
            "style_variant": "feature",
            "buttons": [],
        },
    }
    tree = _run_php(_php_with_item(item))

    row = tree["innerBlocks"][0]
    col1, col2 = row["innerBlocks"]
    assert col1["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-content"
    assert col2["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-media"
    assert col1["innerBlocks"][0]["blockName"] == "divi/heading"
    assert col2["innerBlocks"][0]["blockName"] == "divi/image"


def test_media_text_unknown_button_variant_defaults_to_primary():
    """Unknown button variant sanitizes to primary."""
    item = {
        "block": "media-text",
        "values": {
            "title": "T",
            "text": "B",
            "image": 99,
            "image_alt": "X",
            "media_side": "left",
            "style_variant": "feature",
            "buttons": [{"text": "Go", "href": "/go", "variant": "fancy"}],
        },
    }
    tree = _run_php(_php_with_item(item))

    btn = tree["innerBlocks"][0]["innerBlocks"][1]["innerBlocks"][2]
    assert btn["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-button bioco-home-button--primary"


def test_media_text_omits_image_when_url_unavailable():
    """media-text image column is still present but empty when URL unavailable."""
    item = {
        "block": "media-text",
        "values": {
            "title": "T",
            "text": "B",
            "image": 999,
            "image_alt": "Missing",
            "media_side": "left",
            "style_variant": "feature",
            "buttons": [],
        },
    }
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        "function wp_get_attachment_image_url($id, $size = 'full') { return false; }\n"
        + WP_SERIALIZER_STUB
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + f"$item = json_decode(base64_decode('{base64.b64encode(json.dumps(item).encode('utf-8')).decode('ascii')}'), true);\n"
        + "$tree = Bioco_Import_Divi_Composer::section($item); echo json_encode($tree);"
    )
    tree = _run_php(php)
    media_col = tree["innerBlocks"][0]["innerBlocks"][0]
    assert media_col["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-feature-media"
    assert media_col["innerBlocks"] == []


# ---------------------------------------------------------------------------
# Rich-text
# ---------------------------------------------------------------------------


def test_rich_text_structure():
    """rich-text produces section > row > single column with heading, body, buttons."""
    item = {
        "block": "rich-text",
        "values": {
            "title": "Mitmachen",
            "text": "<p>Jetzt <em>anmelden</em>.</p>",
            "style_variant": "feature",
            "buttons": [{"text": "Anmelden", "href": "/anmelden", "variant": "primary"}],
        },
    }
    tree = _run_php(_php_with_item(item))

    assert tree["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-cta"

    row = tree["innerBlocks"][0]
    assert row["attrs"]["module"]["advanced"]["columnStructure"]["desktop"]["value"] == "4_4"
    assert "htmlAttributes" not in row["attrs"]["module"]["advanced"]

    col = row["innerBlocks"][0]
    assert col["attrs"]["module"]["advanced"]["type"]["desktop"]["value"] == "4_4"
    assert col["attrs"]["module"]["advanced"]["htmlAttributes"]["desktop"]["value"]["class"] == "bioco-home-cta-content"

    h2, txt, btn = col["innerBlocks"]
    assert h2["blockName"] == "divi/heading"
    assert h2["attrs"]["title"]["innerContent"]["desktop"]["value"] == "Mitmachen"
    assert h2["attrs"]["title"]["decoration"]["font"]["font"]["desktop"]["value"]["headingLevel"] == "h2"

    assert txt["blockName"] == "divi/text"
    assert txt["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<p>Jetzt <em>anmelden</em>.</p>"

    assert btn["blockName"] == "divi/button"
    assert btn["attrs"]["button"]["innerContent"]["desktop"]["value"]["text"] == "Anmelden"
    assert btn["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkUrl"] == "/anmelden"


def test_rich_text_plain_text_body_preserved():
    """Plain text body is preserved exactly and not re-wrapped."""
    item = {
        "block": "rich-text",
        "values": {
            "title": "T",
            "text": "Ein einfacher Text ohne Markup",
            "style_variant": "feature",
            "buttons": [],
        },
    }
    tree = _run_php(_php_with_item(item))
    txt = tree["innerBlocks"][0]["innerBlocks"][0]["innerBlocks"][1]
    assert txt["attrs"]["content"]["innerContent"]["desktop"]["value"] == "Ein einfacher Text ohne Markup"


# ---------------------------------------------------------------------------
# Unicode / apostrophe safety
# ---------------------------------------------------------------------------


def test_unicode_and_apostrophes_survive_transport():
    """Apostrophes and Unicode characters survive base64 transport into PHP."""
    item = {
        "block": "rich-text",
        "values": {
            "title": "It’s bioco’s Gemüse",
            "text": "<p>Frische Äpfel, Ökologie & Überzeugung.</p>",
            "style_variant": "feature",
            "buttons": [{"text": "Weiter", "href": "/it’s", "variant": "primary"}],
        },
    }
    tree = _run_php(_php_with_item(item))

    h2 = tree["innerBlocks"][0]["innerBlocks"][0]["innerBlocks"][0]
    assert h2["attrs"]["title"]["innerContent"]["desktop"]["value"] == "It’s bioco’s Gemüse"

    txt = tree["innerBlocks"][0]["innerBlocks"][0]["innerBlocks"][1]
    assert txt["attrs"]["content"]["innerContent"]["desktop"]["value"] == "<p>Frische Äpfel, Ökologie & Überzeugung.</p>"

    btn = tree["innerBlocks"][0]["innerBlocks"][0]["innerBlocks"][2]
    assert btn["attrs"]["button"]["innerContent"]["desktop"]["value"]["linkUrl"] == "/it’s"


# ---------------------------------------------------------------------------
# Rejection & safety
# ---------------------------------------------------------------------------


def test_rejects_unknown_block():
    """Any block other than hero, media-text, rich-text throws InvalidArgumentException."""
    item = {"block": "gallery", "values": {"images": []}}
    payload = base64.b64encode(json.dumps(item).encode("utf-8")).decode("ascii")
    php = (
        "define('ABSPATH', __DIR__);\n"
        "function is_wp_error($value) { return false; }\n"
        + WP_SERIALIZER_STUB
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';\n"
        + "require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';\n"
        + f"$item = json_decode(base64_decode('{payload}'), true);\n"
        + "try { Bioco_Import_Divi_Composer::section($item); echo json_encode(['ok'=>true]); }"
        + "catch (InvalidArgumentException $e) { echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]); }"
    )
    result = _run_php(php)
    assert result["ok"] is False
    assert "gallery" in result["msg"]


def test_every_block_name_starts_with_divi():
    """Recursively assert every blockName starts with 'divi/' and no code/shortcode blocks."""
    item = {
        "block": "media-text",
        "values": {
            "title": "T",
            "text": "B",
            "image": 99,
            "image_alt": "X",
            "media_side": "left",
            "style_variant": "feature",
            "buttons": [{"text": "Go", "href": "/go", "variant": "primary"}],
        },
    }
    tree = _run_php(_php_with_item(item))

    for name in _all_block_names(tree):
        assert name.startswith("divi/"), f"Unexpected block name: {name}"
        assert name not in ("divi/code", "divi/shortcode-module"), f"Forbidden block: {name}"
