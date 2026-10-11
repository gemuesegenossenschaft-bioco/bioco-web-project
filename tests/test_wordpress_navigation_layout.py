"""Render the saved header through its real PHP adapter and CSS in Chromium.

The small Divi boundary models the later Theme Builder list defaults observed
on an event. It does not replace native editor save/reopen or release QA.
"""
import base64
import json
from functools import cache
from pathlib import Path

import pytest
from playwright.sync_api import sync_playwright

from test_wordpress_editable_shell import render

ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
THEME = ROOT / "wordpress/web/app/themes/bioco-divi"
ROUTES = ["wir", "home", "abos", "aktuelles", "event"]
DIVI_LIST_DEFAULTS = """
.et-l--header ul { list-style-type: disc; padding: 0 0 23px 1em; line-height: 26px; }
"""


@pytest.fixture(scope="module")
def browser():
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        yield browser
        browser.close()


@pytest.fixture
def page(browser):
    page = browser.new_page()
    page.route("**/*", lambda route: route.abort())
    yield page
    page.close()


@cache
def header(route, values_json="{}"):
    return render(page=route, theme_builder=True, **json.loads(values_json))["html"]


def install(page, route, values=None):
    body_class = "wp-child-theme-bioco-divi et-db " + ("home" if route == "home" else "")
    markup = '<header class="et-l et-l--header">' + header(route, json.dumps(values or {})) + "</header>"
    # CPT Theme Builder markup has its own #et-boc wrapper.
    if route == "event":
        markup = '<div id="et-boc">' + markup + "<main style=\"height:3000px\"></main></div>"
    else:
        markup += '<main style="height:3000px"></main>'
    page.set_content('<body class="' + body_class + '">' + markup + "</body>")
    page.add_style_tag(path=str(CORE / "assets/bioco-tokens.css"))
    page.add_style_tag(path=str(CORE / "assets/bioco-shell.css"))
    page.add_style_tag(path=str(THEME / "style.css"))
    # Both Divi's normal and late CPT asset order must keep neutral nav lists.
    page.add_style_tag(content=DIVI_LIST_DEFAULTS)
    logo = base64.b64encode((CORE / "assets/bioco-logo.png").read_bytes()).decode()
    page.locator(".bioco-logo img").evaluate("(image, src) => { image.src = src; return image.decode(); }", "data:image/png;base64," + logo)
    page.add_script_tag(path=str(CORE / "assets/bioco-navigation.js"))


def geometry(page):
    return page.evaluate("""() => {
      const selectors = ['header', '.bioco-page-shell', '.bioco-primary-nav',
        '.bioco-utility-nav', '.bioco-primary-nav ul', '.bioco-utility-nav ul',
        '.bioco-logo', '.bioco-logo img', '.bioco-primary-nav li a', '.bioco-primary-cta'];
      return selectors.map(selector => {
        const element = document.querySelector(selector);
        const rect = element.getBoundingClientRect(), style = getComputedStyle(element);
        return {selector, x: rect.x, y: rect.y, width: rect.width, height: rect.height,
          font: style.font, lineHeight: style.lineHeight, padding: style.padding,
          listStyle: style.listStyleType, border: style.borderWidth, position: style.position};
      });
    }""")


def no_logo_clipping_or_overflow(page):
    assert page.locator(".bioco-logo img").evaluate("""image => {
      const logo = image.getBoundingClientRect(), frame = document.querySelector('.bioco-page-shell').getBoundingClientRect();
      return image.complete && image.naturalWidth > 0 && logo.left >= frame.left &&
        logo.right <= frame.right && logo.left >= 0 && logo.right <= innerWidth &&
        document.documentElement.scrollWidth <= innerWidth;
    }""")


@pytest.mark.parametrize("width", [390, 901, 1280, 1440, 2048])
def test_same_saved_header_has_equal_geometry_on_all_routes(page, width):
    page.set_viewport_size({"width": width, "height": 900})
    baseline = None
    for route in ROUTES:
        install(page, route)
        current = geometry(page)
        if baseline is None:
            baseline = current
        assert current == baseline, route
        no_logo_clipping_or_overflow(page)
        assert page.locator(".bioco-primary-nav ul").evaluate("el => getComputedStyle(el).listStyleType") == "none"
        if route in ["wir", "abos", "aktuelles"]:
            assert page.locator('.bioco-primary-nav a[aria-current="page"]').count() == 1
            assert page.locator('.bioco-primary-nav a[aria-current="page"]').evaluate("el => getComputedStyle(el).textDecorationLine") == "underline"


@pytest.mark.parametrize("route", ROUTES)
def test_shared_header_sticks_collapses_and_keeps_keyboard_utility_links_visible(page, route):
    page.set_viewport_size({"width": 1440, "height": 900})
    install(page, route)
    page.evaluate("window.scrollTo(0, 700)")
    page.wait_for_function("document.querySelector('.bioco-navigation-shell').classList.contains('is-utility-hidden')")
    page.wait_for_function("document.querySelector('.bioco-utility-nav').clientHeight === 0")
    assert page.locator("header").bounding_box()["y"] == 0
    utility = page.locator(".bioco-utility-nav a").first
    utility.focus()
    page.wait_for_function("document.querySelector('.bioco-utility-nav').getBoundingClientRect().height === 70")
    assert utility.evaluate("el => getComputedStyle(el).outlineStyle") == "solid"
    assert utility.evaluate("el => getComputedStyle(el).outlineWidth") == "3px"


@pytest.mark.parametrize("route", ROUTES)
def test_mobile_menu_opens_by_keyboard_and_escape_restores_toggle_focus(page, route):
    page.set_viewport_size({"width": 390, "height": 844})
    install(page, route)
    toggle = page.locator(".bioco-menu-toggle")
    assert toggle.get_attribute("aria-label") == "Menü öffnen"
    assert not page.locator(".bioco-primary-nav ul").is_visible()
    toggle.focus()
    page.keyboard.press("Enter")
    assert toggle.get_attribute("aria-expanded") == "true"
    assert toggle.get_attribute("aria-label") == "Menü schliessen"
    assert page.locator(".bioco-primary-nav ul").is_visible()
    assert page.locator(".bioco-primary-nav ul").evaluate("el => getComputedStyle(el).padding") == "12px 20px 24px"
    first = page.locator(".bioco-primary-nav ul a").first
    assert first.evaluate("el => document.activeElement === el")
    assert first.evaluate("el => getComputedStyle(el).outlineWidth") == "3px"
    no_logo_clipping_or_overflow(page)
    page.keyboard.press("Escape")
    assert toggle.get_attribute("aria-expanded") == "false"
    assert toggle.evaluate("el => document.activeElement === el")


@pytest.mark.parametrize("width", [390, 1440])
def test_authored_header_geometry_remains_equal_on_home_and_inner_pages(page, width):
    page.set_viewport_size({"width": width, "height": 900})
    values = {"logo_width": 150, "logo_width_mobile": 120, "font_size": 19,
              "font_size_mobile": 17, "gap": 8, "container_width": 1100,
              "inline_padding": 32, "padding_vertical": 18, "padding_mobile": 24}
    baseline = None
    for route in ROUTES:
        install(page, route, values)
        current = geometry(page)
        if baseline is None:
            baseline = current
        assert current == baseline, route
        assert page.locator(".bioco-logo").bounding_box()["width"] == (120 if width == 390 else 150)
        no_logo_clipping_or_overflow(page)


@pytest.mark.parametrize("route", ["home", "event"])
def test_short_mobile_menu_follows_saved_bar_height_and_keeps_last_link_visible(page, route):
    page.set_viewport_size({"width": 390, "height": 320})
    install(page, route, {"padding_vertical": 18, "toggle_size": 64})
    toggle = page.locator(".bioco-menu-toggle")
    toggle.focus()
    page.keyboard.press("Enter")
    menu = page.locator("#bioco-primary-menu")
    nav_box = page.locator(".bioco-primary-nav").bounding_box()
    assert abs(menu.bounding_box()["y"] - nav_box["height"]) <= 1
    for _ in range(menu.locator("a").count() - 1):
        page.keyboard.press("Tab")
    last = menu.locator("a").last
    assert last.evaluate("el => document.activeElement === el")
    box = last.bounding_box()
    assert box["y"] >= nav_box["height"]
    assert box["y"] + box["height"] <= 320
    assert menu.evaluate("el => el.scrollTop > 0")
    # A saved responsive size change while open must recalculate available space.
    page.locator(".bioco-editable-header").evaluate("el => el.style.setProperty('--bioco-header-padding-vertical', '28px')")
    page.wait_for_function("document.querySelector('.bioco-primary-nav').style.getPropertyValue('--bioco-primary-nav-height') === '121px'")
    assert abs(menu.bounding_box()["y"] - page.locator(".bioco-primary-nav").bounding_box()["height"]) <= 1
