"""Keyboard adapter behavior; Keep/Replace/Remove map in tests/README.md.

Chromium executes the real child-theme script and CSS. A title-click boundary
stands in for licensed Divi; real Divi keyboard activation requires staged drafts.
"""
import json
import subprocess
from pathlib import Path

import pytest
from playwright.sync_api import sync_playwright

from test_wordpress_divi_static_layouts import _compose

ROOT = Path(__file__).parents[1]
THEME = ROOT / 'wordpress/web/app/themes/bioco-divi'


@pytest.fixture
def page():
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        page.route('**/*', lambda route: route.abort())
        yield page
        browser.close()


def install(page, builder=False):
    page.set_content('''<body class="''' + ('et-fb' if builder else '') + '''">
      <div class="bioco-editorial-toggle et_pb_toggle_close">
        <h3 class="et_pb_toggle_title" style="font-size:23px;color:rgb(12,34,56)"><strong>First</strong></h3>
        <div class="et_pb_toggle_content">Body one</div>
      </div>
      <div class="bioco-editorial-toggle et_pb_toggle_open">
        <h3 class="et_pb_toggle_title">Second</h3>
        <div class="et_pb_toggle_content" id="authored-panel">Body two</div>
      </div>
      <div class="bioco-editorial-toggle et_pb_toggle_close">
        <h3 class="et_pb_toggle_title"><a href="/document.pdf">Authored link</a></h3>
        <div class="et_pb_toggle_content">Body three</div>
      </div>
    </body>''')
    page.add_style_tag(path=str(THEME / 'style.css'))
    page.add_script_tag(path=str(THEME / 'editorial.js'))
    page.evaluate('''() => document.querySelectorAll('.et_pb_toggle_title').forEach(title => {
      title.addEventListener('click', () => {
        const module = title.parentElement;
        module.dataset.clicks = String(Number(module.dataset.clicks || 0) + 1);
        const open = module.classList.toggle('et_pb_toggle_open');
        module.classList.toggle('et_pb_toggle_close', !open);
      });
    })''')


@pytest.mark.parametrize('width', [390, 1440])
def test_enter_space_and_click_activate_divi_once_and_update_accessible_state(page, width):
    page.set_viewport_size({'width':width, 'height':900})
    install(page)
    trigger = page.get_by_role('button', name='First', exact=True)
    assert trigger.get_attribute('aria-expanded') == 'false'
    page.keyboard.press('Tab')
    assert trigger.evaluate('(el) => document.activeElement === el')
    assert trigger.evaluate('(el) => getComputedStyle(el).outlineWidth') == '3px'
    page.keyboard.press('Enter')
    page.wait_for_function("document.querySelector('.bioco-editorial-toggle-trigger').getAttribute('aria-expanded') === 'true'")
    page.keyboard.press('Space')
    page.wait_for_function("document.querySelector('.bioco-editorial-toggle-trigger').getAttribute('aria-expanded') === 'false'")
    trigger.click()
    page.wait_for_function("document.querySelector('.bioco-editorial-toggle-trigger').getAttribute('aria-expanded') === 'true'")
    assert page.locator('.bioco-editorial-toggle').first.get_attribute('data-clicks') == '3'
    assert page.get_by_role('button', name='Second').get_attribute('aria-expanded') == 'true'


def test_headings_authored_markup_design_and_panel_ids_survive_repeated_initialization(page):
    install(page)
    page.add_script_tag(path=str(THEME / 'editorial.js'))
    assert page.get_by_role('button').count() == 3
    assert page.get_by_role('heading', name='First', level=3).count() == 1
    trigger = page.get_by_role('button', name='First')
    assert trigger.locator('strong').text_content() == 'First'
    assert trigger.evaluate('(el) => getComputedStyle(el).fontSize') == '23px'
    assert trigger.evaluate('(el) => getComputedStyle(el).color') == 'rgb(12, 34, 56)'
    panel = page.locator('#' + trigger.get_attribute('aria-controls'))
    assert panel.get_attribute('aria-labelledby') == trigger.get_attribute('id')
    assert panel.get_attribute('role') == 'region'
    assert page.get_by_role('button', name='Second').get_attribute('aria-controls') == 'authored-panel'
    assert page.get_by_role('link', name='Authored link').get_attribute('href') == '/document.pdf'
    assert page.locator('button button').count() == 0


def test_authored_title_link_retains_a_separate_keyboard_toggle(page):
    install(page)
    assert page.get_by_role('heading', name='Authored link', level=3).count() == 1
    assert page.get_by_role('link', name='Authored link').get_attribute('href') == '/document.pdf'
    trigger = page.get_by_role('button', name='Authored link')
    assert trigger.locator('a').count() == 0
    trigger.focus()
    page.keyboard.press('Enter')
    page.wait_for_function("document.querySelector('.bioco-editorial-toggle-trigger--icon').getAttribute('aria-expanded') === 'true'")
    assert page.locator('.bioco-editorial-toggle').last.get_attribute('data-clicks') == '1'


def test_visual_builder_keeps_its_own_title_editing_and_keyboard_behavior(page):
    install(page, builder=True)
    assert page.get_by_role('button').count() == 0
    assert page.get_by_role('heading', name='First', level=3).count() == 1


def test_new_toggle_preserves_original_summary_typography_in_native_settings():
    tree = _compose({'block':'accordion', 'values':{'items':[{'title':'First', 'body':'<p>Body</p>'}]}})
    font = tree['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']['desktop']['value']
    assert font['size'] == '16px' and font['weight'] == '600'


def test_theme_registers_the_public_adapter_with_a_file_version():
    code = r'''
    define('ABSPATH', '/');
    $actions = [];
    function add_action($hook, $callback) { $GLOBALS['actions'][$hook][] = $callback; }
    function add_filter(...$args) {}
    function get_template_directory_uri() { return 'https://example.test/Divi'; }
    function get_stylesheet_directory_uri() { return 'https://example.test/bioco-divi'; }
    function get_stylesheet_directory() { return getcwd() . '/wordpress/web/app/themes/bioco-divi'; }
    function wp_enqueue_style(...$args) {}
    function wp_enqueue_script(...$args) { $GLOBALS['scripts'][] = $args; }
    require 'wordpress/web/app/themes/bioco-divi/functions.php';
    foreach ($actions['wp_enqueue_scripts'] as $callback) $callback();
    echo json_encode($GLOBALS['scripts']);
    '''
    result = subprocess.run(['php','-r',code],cwd=ROOT,text=True,capture_output=True,check=True)
    scripts = json.loads(result.stdout)
    assert scripts == [['bioco-divi-editorial', 'https://example.test/bioco-divi/editorial.js', [], str(int((THEME / 'editorial.js').stat().st_mtime)), True]]
