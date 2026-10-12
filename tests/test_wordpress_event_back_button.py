"""Native back-button defaults and editable CSS boundaries for #217.

PHP executes both real layout generators and the WordPress block parser.
Chromium checks the shared wrapping/focus CSS against the observed Divi anchor
and wrapper shape. It does not emulate Divi's preset/style renderer; native
save/reopen and the rendered preset remain hosted operator checks.
Keep/Replace/Remove evidence map: tests/README.md.
"""
import json
from hashlib import sha256
import subprocess
from pathlib import Path

import pytest

from test_wordpress_event_templates import run, walk
from test_wordpress_single_event import modules, render
from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
THEME = ROOT / 'wordpress/web/app/themes/bioco-divi/style.css'
TOKENS = ROOT / 'wordpress/web/app/mu-plugins/bioco-core/assets/bioco-tokens.css'


def native_back():
    return next(block for block in walk(run()['layout']) if block['blockName'] == 'divi/button')


def assert_native_token(value, css_var, token_type):
    if token_type != 'color':
        assert value == 'var(--gvid-bioco-' + sha256(css_var.encode()).hexdigest()[:16] + ')'
        return
    assert value.startswith('$variable(') and value.endswith(')$')
    payload = json.loads(value[len('$variable('):-2])
    prefix = 'gcid-' if token_type == 'color' else 'gvid-'
    assert payload == {'type': token_type, 'value': {
        'name': prefix + 'bioco-' + sha256(css_var.encode()).hexdigest()[:16], 'settings': {},
    }}


@pytest.mark.parametrize('post_type', ['event', 'post'])
def test_template_and_article_fallback_share_the_existing_secondary_preset(post_type):
    back = native_back()
    fallback = modules(render(post_type=post_type))[-1]
    assert fallback['blockName'] == back['blockName'] and fallback['attrs'] == back['attrs']
    attrs = back['attrs']
    assert attrs.get('modulePreset') == ['bioco-5f12512a732ddec5']
    assert attrs['button']['innerContent']['desktop']['value'] == {
        'text': 'Zurück zu Aktuelles', 'linkUrl': 'https://example.test/aktuelles/', 'linkTarget': 'off',
    }
    # Colours/font/padding stay with the existing editable shared preset.
    decoration = attrs['button']['decoration']
    assert decoration['font']['font']['desktop']['value'] == {'lineHeight': '1.5em'}
    assert 'spacing' not in decoration
    assert decoration['sizing']['desktop']['value'] == {'minHeight': '44px', 'maxWidth': '100%'}
    for radius in decoration['border']['desktop']['value']['radius'].values():
        assert_native_token(radius, '--wp--custom--radius--md', 'content')
    assert_native_token(decoration['font']['font']['desktop']['hover']['color'], '--wp--preset--color--bioco-green-dark', 'color')
    assert_native_token(decoration['background']['desktop']['hover']['color'], '--wp--preset--color--bioco-bg', 'color')
    assert attrs['module']['decoration']['spacing']['desktop']['value']['margin']['top'] == '48px'


def test_native_label_link_responsive_design_and_spacing_edits_round_trip():
    back = native_back()
    attrs = back['attrs']
    attrs['button']['innerContent']['desktop']['value'].update(text='Alle Rückblicke & Neuigkeiten', linkUrl='/rueckblicke/')
    attrs['button']['decoration']['font']['font']['desktop']['value']['color'] = '#123456'
    attrs['button']['decoration']['font']['font']['phone'] = {'value': {'size': '18px'}}
    attrs['button']['decoration']['border']['desktop']['value']['radius']['topLeft'] = '24px'
    attrs['module']['decoration']['spacing']['desktop']['value']['margin']['top'] = '12px'
    attrs['module']['decoration']['spacing']['phone'] = {'value': {'margin': {'top': '24px'}}}
    php = "require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';\n" + WP_SERIALIZER_STUB + r'''
$blocks = json_decode(stream_get_contents(STDIN), true);
$saved = serialize_blocks($blocks);
$reopened = (new WP_Block_Parser())->parse($saved);
echo json_encode(['blocks'=>$reopened, 'stable'=>serialize_blocks($reopened)===$saved]);
'''
    result = subprocess.run(['php', '-r', php], cwd=ROOT, input=json.dumps([back]),
                            text=True, capture_output=True, check=True)
    assert result.stderr == ''
    reopened = json.loads(result.stdout)
    assert reopened['stable'] and reopened['blocks'] == [back]


@pytest.fixture(scope='module')
def browser():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as playwright:
        instance = playwright.chromium.launch()
        yield instance
        instance.close()


@pytest.mark.parametrize('width', [320, 390, 1440])
def test_keyboard_focus_wrapping_footer_gap_and_authored_styles(browser, width):
    page = browser.new_page(viewport={'width': width, 'height': 900})
    try:
        page.route('**/*', lambda route: route.abort())
        # Observed native DOM shape; boundary styles stand in for a saved editor
        # choice, not for the licensed renderer. The real child CSS is under test.
        page.set_content('''<body class="wp-child-theme-bioco-divi"><div id="page-container">
        <main id="bioco-main-content"><div id="et-boc"><div class="et-l">
        <section class="bioco-article-section et_pb_section"><div class="bioco-article-row et_pb_row">
        <div class="bioco-article-column et_pb_column"><p>Veranstaltung</p>
        <div class="et_pb_module et_pb_button_module_wrapper" style="margin-top:12px">
        <a class="et_pb_button_0 et_pb_button et_pb_module bioco-article-back" href="/aktuelles/"
           style="color:#123456;background:#fff;border:2px solid transparent;border-radius:24px;
           padding:8px 24px;font:700 16px/24px sans-serif;display:inline-block;box-sizing:border-box;
           max-width:100%;min-height:44px">'''
                         + 'ZurückzuallenVeranstaltungenundRückblicken' * 4
                         + '''</a></div></div></div></section></div></div></main>
        <footer style="border-top:1px solid #222">Footer</footer></div></body>''')
        page.add_style_tag(content=TOKENS.read_text())
        page.add_style_tag(content=THEME.read_text())
        page.keyboard.press('Tab')
        result = page.locator('.bioco-article-back').evaluate('''a => {
            const s=getComputedStyle(a), b=a.getBoundingClientRect(),
                f=document.querySelector('footer').getBoundingClientRect();
            return {focused:document.activeElement===a, color:s.color, radius:s.borderRadius,
                outline:s.outline, outlineOffset:s.outlineOffset, margin:s.marginTop,
                height:b.height, right:b.right, footerGap:f.top-b.bottom,
                overflow:document.documentElement.scrollWidth>innerWidth,
                clipped:s.clipPath!=='none'};
        }''')
        assert result['focused']
        assert result['color'] == 'rgb(18, 52, 86)' and result['radius'] == '24px'
        assert result['outline'] == 'rgb(255, 140, 0) solid 3px' and result['outlineOffset'] == '3px'
        assert result['margin'] == '0px'  # Native wrapper spacing wins over the retired anchor margin.
        assert result['height'] >= 44 and result['right'] <= width
        assert result['footerGap'] >= 56 and not result['overflow'] and not result['clipped']
    finally:
        page.close()
