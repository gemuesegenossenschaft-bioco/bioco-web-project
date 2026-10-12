"""Native Divi heading styles must beat the child theme's legacy defaults."""

from pathlib import Path

import pytest
from playwright.sync_api import sync_playwright


THEME = Path(__file__).parents[1] / "wordpress/web/app/themes/bioco-divi/style.css"


@pytest.fixture(scope="module")
def browser():
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        yield browser
        browser.close()


def test_legacy_heading_defaults_yield_to_divi_saved_size_and_color(browser):
    page = browser.new_page(viewport={"width": 1280, "height": 800})
    page.set_content("""
      <section class="bioco-divi-section bioco-divi-page-intro">
        <div class="et_pb_heading et_pb_heading_0 bioco-divi-title">
          <div class="et_pb_heading_container"><h1 class="et_pb_module_header">Native heading</h1></div>
        </div>
        <div class="et_pb_heading et_pb_heading_1 bioco-divi-title">
          <div class="et_pb_heading_container"><h1 class="et_pb_module_header">Legacy default</h1></div>
        </div>
      </section>
    """)
    # Divi 5 emits saved module properties; the theme can load later.
    page.add_style_tag(content="""
      .et_pb_heading_0 .et_pb_module_header { font-size: 34px; color: #287a36; }
      @media (max-width: 767px) {
        .et_pb_heading_0 .et_pb_module_header { font-size: 26px; }
      }
    """)
    page.add_style_tag(path=str(THEME))
    # The PHP composer supplies the second heading with only a level and class.
    assert page.locator(".et_pb_heading_1 .et_pb_module_header").evaluate(
        "element => getComputedStyle(element).fontSize"
    ) == "40px"
    native = page.locator(".et_pb_heading_0 .et_pb_module_header")
    assert native.evaluate("element => getComputedStyle(element).fontSize") == "34px"
    assert native.evaluate("element => getComputedStyle(element).color") == "rgb(40, 122, 54)"

    page.set_viewport_size({"width": 390, "height": 844})
    assert native.evaluate("element => getComputedStyle(element).fontSize") == "26px"
    assert page.locator(".et_pb_heading_1 .et_pb_module_header").evaluate(
        "element => getComputedStyle(element).fontSize"
    ) == "28px"
    page.close()
