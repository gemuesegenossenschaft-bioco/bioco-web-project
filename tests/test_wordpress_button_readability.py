"""Rendered-browser regression for #178 (WordPress button readability).

Opt-in browser suite: these tests drive the real repo CSS (bioco-tokens.css,
bioco-blocks.css, the shared bioco-core shell bioco-shell.css,
bioco-divi/style.css) inside Chromium against DOM fixtures captured from
staging.bioco.ch (tests/fixtures/button-readability/) and assert *computed and
painted* behaviour, not CSS source strings.

Run explicitly with Playwright available:

    BIOCO_BUTTON_BROWSER_TESTS=1 python3 -m pytest tests/test_wordpress_button_readability.py -q

Requires for the opt-in run: Playwright (Chromium) for the browser checks and
Pillow for the painted-pixel focus-ring analysis:

    pip install playwright Pillow && playwright install chromium

With BIOCO_BUTTON_BROWSER_TESTS=1 a missing Playwright install or missing
Chromium build FAILS the suite (the opt-in demands the stack); without the
env var the module is skipped so the WordPress staging release preflight
(`wordpress/scripts/release-wordpress-preflight.sh`, plain `pytest tests/`)
never requires Playwright on machines without it.

Fixtures are static captures: no form submission, no mail, no network writes.
All page requests are intercepted: only the local base origin is served,
everything else (e.g. fixture-referenced staging image URLs) is aborted.
The pricing-calculator tier interaction reads href/state only; the local
calculator view.js stays active.
"""

import functools
import http.server
import io
import os
import threading

import pytest

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

OPT_IN_ENV = "BIOCO_BUTTON_BROWSER_TESTS"
OPTED_IN = os.environ.get(OPT_IN_ENV) == "1"

pytestmark = pytest.mark.skipif(
    not OPTED_IN,
    reason=f"browser opt-in: set {OPT_IN_ENV}=1 to run Chromium checks (see module docstring)",
)

# Organic torn-paper polygon as computed on bioco.ch reference buttons
# (btn-organic, globals.css). Chrome-normalized form.
REF_POLYGON = (
    "polygon(4% 8%, 12% 2%, 26% 6%, 36% 3%, 48% 7%, 60% 2%, 74% 6%, 86% 3%, "
    "96% 10%, 98% 24%, 96% 42%, 99% 58%, 96% 76%, 98% 90%, 90% 98%, 74% 94%, "
    "60% 98%, 46% 94%, 32% 98%, 18% 94%, 8% 96%, 2% 86%, 4% 70%, 2% 54%, "
    "6% 36%, 2% 20%)"
)
GREEN = "rgb(46, 125, 50)"
GREEN_DARK = "rgb(27, 94, 32)"
CARROT = (255, 140, 0)
WHITE = "rgb(255, 255, 255)"

VIEWPORTS = {"desktop": (1440, 900), "mobile": (390, 844)}

PROBE_JS = """([sel, pseudo]) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const cs = getComputedStyle(el, pseudo || null);
    const r = el.getBoundingClientRect();
    const pick = (c) => ({
        display: c.display, position: c.position, isolation: c.isolation,
        color: c.color, backgroundColor: c.backgroundColor,
        borderWidth: c.borderWidth, borderStyle: c.borderStyle,
        borderColor: c.borderColor, borderRadius: c.borderRadius,
        clipPath: c.clipPath, fontSize: c.fontSize, fontWeight: c.fontWeight,
        padding: c.padding, lineHeight: c.lineHeight, minHeight: c.minHeight,
        textDecorationLine: c.textDecorationLine,
        boxShadow: c.boxShadow, outlineWidth: c.outlineWidth,
        outlineStyle: c.outlineStyle, outlineColor: c.outlineColor,
        opacity: c.opacity, cursor: c.cursor,
        rect: {x: r.x, y: r.y, w: r.width, h: r.height},
    });
    const out = pick(cs);
    const bf = getComputedStyle(el, '::before');
    out.before = {
        content: bf.content, bg: bf.backgroundColor,
        clipPath: bf.clipPath, zIndex: bf.zIndex,
        insetTop: bf.top, insetLeft: bf.left,
    };
    return out;
}"""


def _channel(color: str):
    """Parse 'rgb(r, g, b)' or 'rgba(r, g, b, a)' into (r, g, b)."""
    nums = [float(p) for p in color.replace("rgba", "rgb").strip("rgb() ").split(",")][:3]
    return nums


def _luminance(r, g, b):
    def lin(c):
        c /= 255.0
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)


def contrast_ratio(fg, bg):
    lf = _luminance(*_channel(fg))
    lb = _luminance(*_channel(bg))
    lighter, darker = max(lf, lb), min(lf, lb)
    return (lighter + 0.05) / (darker + 0.05)


class _QuietHandler(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):  # keep pytest output clean
        pass


@pytest.fixture(scope="module")
def browser_ctx():
    """Serve the repo root over HTTP and yield (base_url, browser).

    Cleanup runs via try/finally so the HTTP server and the browser are shut
    down even when a test (or the launch) fails.
    """
    server = http.server.ThreadingHTTPServer(
        ("127.0.0.1", 0), functools.partial(_QuietHandler, directory=REPO)
    )
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    base = f"http://127.0.0.1:{server.server_address[1]}"
    try:
        try:
            from playwright.sync_api import sync_playwright
        except ImportError:
            if OPTED_IN:
                pytest.fail(
                    f"{OPT_IN_ENV}=1 is set but playwright is not installed; "
                    "install it (pip install playwright && playwright install chromium)"
                )
            pytest.skip("playwright is not installed")

        manager = sync_playwright().start()
        try:
            try:
                browser = manager.chromium.launch()
            except Exception as exc:
                if OPTED_IN:
                    pytest.fail(f"{OPT_IN_ENV}=1 is set but chromium is unusable: {exc}")
                pytest.skip(f"chromium unavailable: {exc}")
            try:
                yield base, browser
            finally:
                browser.close()
        finally:
            manager.stop()
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


@pytest.fixture()
def page(browser_ctx):
    base, browser = browser_ctx
    context = browser.new_context()
    page = context.new_page()
    page.set_default_timeout(15_000)

    def _allow_local_only(route):
        url = route.request.url
        if url == base or url.startswith(base + "/"):
            route.continue_()
        else:
            route.abort()

    # Fixtures reference staging asset URLs; the suite must stay local-only.
    # Local scripts (navigation.js, calculator view.js) keep loading.
    page.route("**/*", _allow_local_only)
    try:
        yield base, page
    finally:
        context.close()


def _style(page, sel, pseudo=None):
    data = page.evaluate(PROBE_JS, [sel, pseudo])
    assert data is not None, f"element not found: {sel}"
    return data


def test_collapsed_utility_navigation_reopens_on_keyboard_focus(page):
    base, browser_page = page
    browser_page.goto(base)
    browser_page.set_content(f'''<link rel="stylesheet" href="{base}/wordpress/web/app/mu-plugins/bioco-core/assets/bioco-shell.css">
        <div class="bioco-navigation-shell is-utility-hidden">
          <nav class="bioco-utility-nav"><a href="#account">Account</a></nav>
        </div><a href="#next">Next</a>''')
    browser_page.wait_for_function("getComputedStyle(document.querySelector('.bioco-utility-nav')).height === '0px'")
    browser_page.keyboard.press("Tab")
    assert browser_page.locator(".bioco-utility-nav a").evaluate("el => el === document.activeElement")
    browser_page.wait_for_function("document.querySelector('.bioco-utility-nav').getBoundingClientRect().height >= 69")


def test_short_mobile_menu_scrolls_to_last_link(page):
    base, pg = page
    pg.set_viewport_size({"width": 600, "height": 320})
    pg.goto(f"{base}/tests/fixtures/button-readability/home.html", wait_until="load")
    pg.locator(".bioco-menu-toggle").click()
    for _ in range(pg.locator("#bioco-primary-menu a").count() - 1):
        pg.keyboard.press("Tab")
    last_link = pg.locator("#bioco-primary-menu a").last
    assert last_link.evaluate("el => el === document.activeElement")
    box = last_link.bounding_box()
    assert box["y"] >= 76
    assert box["y"] + box["height"] <= 320
    assert pg.locator("#bioco-primary-menu").evaluate("el => el.scrollTop > 0")


def _assert_organic_reference(style, *, min_height):
    assert style["clipPath"].replace(" ", "") == REF_POLYGON.replace(" ", ""), style["clipPath"]
    assert style["borderRadius"] == "28px 22px 26px 18px", style["borderRadius"]
    assert style["fontWeight"] == "700", style["fontWeight"]
    assert style["padding"] == "12px 24px", style["padding"]
    assert style["textDecorationLine"] == "none", style["textDecorationLine"]
    assert style["boxShadow"] != "none", "reference buttons carry a shadow"
    assert style["rect"]["h"] >= min_height, style["rect"]


def _assert_label_readable(style, bg=None):
    bg = bg or style["backgroundColor"]
    assert style["color"] == WHITE, style["color"]
    assert contrast_ratio(style["color"], bg) >= 4.5, (
        style["color"], bg, contrast_ratio(style["color"], bg))


def _focus_via_keyboard(page, sel):
    """Real keyboard focus (Tab) so :focus-visible matches, as users trigger it."""
    page.evaluate("document.body.focus()")
    for _ in range(40):
        active = page.evaluate(
            "({tag: document.activeElement.tagName, cls: (document.activeElement.className || '').toString()})"
        )
        if sel.lstrip(".") in active["cls"].split() or (
            sel.startswith("a") and active["tag"] == "A" and sel[2:].lstrip(".") in active["cls"].split()
        ):
            return
        page.keyboard.press("Tab")
    page.evaluate(f"document.querySelector('{sel}').focus()")
    # Programmatic fallback may not set :focus-visible; force the state the way
    # Chrome does for keyboard origin, so the assertion targets real behaviour.
    page.evaluate(
        """(sel) => { const el = document.querySelector(sel);
            el.setAttribute('data-focused-via-keyboard', '1'); }""",
        sel,
    )
    pytest.fail(f"could not reach {sel} via keyboard Tab within 40 stops")


def _outline_pixels_outside(page, sel, band=8):
    """Count carrot-outline pixels painted strictly OUTSIDE the border box.

    A clip-path on the element clips its outline; counting painted pixels in a
    band beyond the border box catches exactly that clipping (root review
    finding: computed outline values alone are false assurance).
    """
    from PIL import Image

    # Keyboard focus can expand the utility row and move the target. Measure
    # after finite transitions finish so the screenshot uses the same box.
    page.evaluate("""async () => {
        await document.fonts.ready;
        await Promise.all(document.getAnimations()
            .filter(animation => Number.isFinite(animation.effect.getComputedTiming().endTime))
            .map(animation => animation.finished.catch(() => {})));
    }""")
    box = page.locator(sel).first.bounding_box()
    margin = band + 12
    shot = page.screenshot(
        clip={"x": box["x"] - margin, "y": box["y"] - margin,
              "width": box["width"] + 2 * margin, "height": box["height"] + 2 * margin})
    img = Image.open(io.BytesIO(shot)).convert("RGB")

    def is_carrot(px):
        return all(abs(px[i] - CARROT[i]) <= 60 for i in range(3))

    # Screenshot-local coordinates: the border box starts at (margin, margin).
    # Count carrot pixels strictly OUTSIDE the border box (the enclosing ring).
    m = margin
    count = 0
    for y in range(img.height):
        for x in range(img.width):
            inside_box = (m <= x < m + box["width"]) and (m <= y < m + box["height"])
            if not inside_box and is_carrot(img.getpixel((x, y))):
                count += 1
    return count


class TestHomePrimaryButton:
    """«Lerne uns kennen» matches bioco.ch form, type, spacing, colour."""

    @pytest.mark.parametrize("viewport", VIEWPORTS.items(), ids=lambda v: v[0])
    def test_matches_reference(self, page, viewport):
        _, (w, h) = viewport
        _, pg = page
        pg.set_viewport_size({"width": w, "height": h})
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        style = _style(pg, "a.bioco-home-button--primary")
        _assert_organic_reference(style, min_height=48)
        assert style["backgroundColor"] == GREEN
        assert style["color"] == WHITE
        assert style["borderWidth"] == "2px"
        assert style["borderColor"] == GREEN
        assert contrast_ratio(style["color"], style["backgroundColor"]) >= 4.5

    def test_hover_dark_green_keeps_label(self, page):
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        pg.hover("a.bioco-home-button--primary")
        pg.wait_for_timeout(350)
        style = _style(pg, "a.bioco-home-button--primary")
        assert style["backgroundColor"] == GREEN_DARK
        _assert_label_readable(style)

    def test_keyboard_focus_ring_is_painted_outside_the_shape(self, page):
        """Root review: clip-path clipped the focus ring — prove the ring is
        painted beyond the border box, by pixel evidence, on the torn shape."""
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        _focus_via_keyboard(pg, ".bioco-home-button--primary")
        style = _style(pg, "a.bioco-home-button--primary")
        assert style["clipPath"] == "none", "clip must lift while focused"
        assert style["outlineStyle"] == "solid", style["outlineStyle"]
        assert float(style["outlineWidth"].replace("px", "")) >= 2, style["outlineWidth"]
        painted = _outline_pixels_outside(pg, "a.bioco-home-button--primary")
        assert painted >= 200, (
            f"focus ring not painted outside the border box ({painted} px)"
        )


class TestHomeSecondaryButton:
    @pytest.mark.parametrize("viewport", VIEWPORTS.items(), ids=lambda v: v[0])
    def test_feature_secondary_matches_prod_primary_mapping(self, page, viewport):
        """Prod renders «Was gerade wächst» as btn-primary (green surface)."""
        _, (w, h) = viewport
        _, pg = page
        pg.set_viewport_size({"width": w, "height": h})
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        style = _style(pg, "a.bioco-home-button--secondary")
        assert style["clipPath"].replace(" ", "") == REF_POLYGON.replace(" ", "")
        assert style["backgroundColor"] == GREEN
        _assert_label_readable(style)

    def test_cta_section_secondary_is_white_surface(self, page):
        """Prod .btn-secondary.btn-organic: white surface, green label."""
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        style = _style(pg, ".bioco-home-cta a.bioco-home-button--secondary")
        assert style["clipPath"].replace(" ", "") == REF_POLYGON.replace(" ", "")
        assert style["backgroundColor"] == WHITE
        assert style["color"] == GREEN
        assert contrast_ratio(style["color"], style["backgroundColor"]) >= 4.5

    def test_hover_stays_readable(self, page):
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        pg.hover(".bioco-home-cta a.bioco-home-button--secondary")
        pg.wait_for_timeout(350)
        style = _style(pg, ".bioco-home-cta a.bioco-home-button--secondary")
        assert contrast_ratio(style["color"], style["backgroundColor"]) >= 4.5


class TestNavigationCta:
    """«BIOCÒ WERDEN» readable in both navigations, never underlined."""

    def test_desktop_current_page_state(self, page):
        # Real staging state: /bioco-werden marks the CTA aria-current="page".
        _, pg = page
        pg.set_viewport_size({"width": 1440, "height": 900})
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        style = _style(pg, ".bioco-primary-nav .bioco-primary-cta")
        assert style["color"] == WHITE, style["color"]
        assert style["textDecorationLine"] == "none", style["textDecorationLine"]
        assert style["before"]["bg"] == GREEN
        assert contrast_ratio(style["color"], style["before"]["bg"]) >= 4.5

    def test_desktop_hover_state(self, page):
        _, pg = page
        pg.set_viewport_size({"width": 1440, "height": 900})
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        pg.hover(".bioco-primary-nav .bioco-primary-cta")
        pg.wait_for_timeout(350)
        style = _style(pg, ".bioco-primary-nav .bioco-primary-cta")
        assert style["color"] == WHITE, style["color"]
        assert style["before"]["bg"] != "rgba(0, 0, 0, 0)", "polygon must stay visible"

    def test_mobile_menu_state(self, page):
        _, pg = page
        pg.set_viewport_size({"width": 390, "height": 844})
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        pg.click(".bioco-menu-toggle")
        pg.wait_for_timeout(400)
        cta = _style(pg, ".bioco-primary-nav .bioco-primary-cta")
        assert cta["rect"]["w"] > 0 and cta["rect"]["h"] > 0, cta["rect"]
        assert cta["color"] == WHITE, cta["color"]
        assert cta["textDecorationLine"] == "none", cta["textDecorationLine"]

    def test_mobile_menu_active_route_state(self, page):
        """Active route (/bioco-werden): aria-current must not recolor the CTA
        in the open mobile menu either (root review addition)."""
        _, pg = page
        pg.set_viewport_size({"width": 390, "height": 844})
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        pg.click(".bioco-menu-toggle")
        pg.wait_for_timeout(400)
        cta = _style(pg, ".bioco-primary-nav .bioco-primary-cta")
        assert cta["rect"]["w"] > 0 and cta["rect"]["h"] > 0, cta["rect"]
        assert cta["color"] == WHITE, cta["color"]
        assert cta["textDecorationLine"] == "none", cta["textDecorationLine"]
        assert cta["before"]["bg"] == GREEN
        assert contrast_ratio(cta["color"], cta["before"]["bg"]) >= 4.5


class TestSharedBtnFamily:
    """mu-plugin .btn buttons inside Divi content (calculator, forms, links)."""

    @pytest.mark.parametrize("viewport", VIEWPORTS.items(), ids=lambda v: v[0])
    def test_pricing_calculator_cta_label_readable(self, page, viewport):
        _, (w, h) = viewport
        _, pg = page
        pg.set_viewport_size({"width": w, "height": h})
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        style = _style(pg, '[data-pc-field="cta"]')
        assert style["color"] == WHITE, f"green-on-green regression: {style['color']}"
        assert style["backgroundColor"] == GREEN, style["backgroundColor"]
        # Prod reference: plain solid button, 18px radius, no torn polygon.
        assert style["clipPath"] == "none", style["clipPath"]
        assert style["borderRadius"] == "18px", style["borderRadius"]
        assert style["textDecorationLine"] == "none"
        _assert_label_readable(style)

    def test_form_submit_button_visible_without_submitting(self, page):
        # Root evidence: /anmeldung submit «Anmeldung einreichen» rendered
        # transparent. Must be a solid readable button; fixture never submits.
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        style = _style(pg, "form button.btn.btn-primary:not([disabled])")
        assert style["color"] == WHITE, style["color"]
        assert style["backgroundColor"] == GREEN, style["backgroundColor"]
        assert style["clipPath"] == "none"
        _assert_label_readable(style)

    def test_events_archive_link_readable(self, page):
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        style = _style(pg, "a.events-feed-archive-link")
        assert style["color"] == WHITE, style["color"]
        assert style["backgroundColor"] == GREEN, style["backgroundColor"]
        # Prod: plain rounded 18px, no polygon for the non-organic archive link.
        assert style["clipPath"] == "none"
        assert style["borderRadius"] == "18px"

    def test_generic_divi_content_button_readable(self, page):
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        style = _style(pg, "a.bioco-divi-button--primary")
        assert style["clipPath"].replace(" ", "") == REF_POLYGON.replace(" ", ""), \
            style["clipPath"]
        assert style["backgroundColor"] == GREEN
        _assert_label_readable(style)

    def test_generic_organic_focus_ring_is_painted_outside_the_shape(self, page):
        """Same clipping regression guard for generic Divi content buttons."""
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        _focus_via_keyboard(pg, ".bioco-divi-button--primary")
        style = _style(pg, "a.bioco-divi-button--primary")
        assert style["clipPath"] == "none", "clip must lift while focused"
        assert style["outlineStyle"] == "solid", style["outlineStyle"]
        painted = _outline_pixels_outside(pg, "a.bioco-divi-button--primary")
        assert painted >= 200, (
            f"focus ring not painted outside the border box ({painted} px)"
        )

    def test_btn_orange_keeps_its_surface(self, page):
        """Root review: hiding ::before removed the orange surface entirely.
        This test preserves the existing legacy `btn-orange` surface/white-label
        style (currently unused in prod markup, CSS-only). It is a preservation
        assertion, NOT an accessibility claim — no contrast target applies here."""
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        style = _style(pg, "a.btn.btn-orange")
        assert style["before"]["bg"] == "rgb(255, 140, 0)", style["before"]["bg"]
        assert style["color"] == WHITE, style["color"]
        assert style["backgroundColor"] == "rgba(0, 0, 0, 0)"

    def test_fixture_extras_are_siblings_of_calculator_module(self, page):
        """Fixture integrity guard: the appended probe sections/forms must be
        true siblings of the pricing-calculator text module inside
        .et_builder_inner_content, not descendants of the calculator section."""
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        boundary = pg.evaluate("""() => {
            const section = document.querySelector('section#pricing-calculator');
            const extras = document.querySelector('.et_pb_section_extra');
            return {
                extrasInsideSection: !!(section && extras && section.contains(extras)),
                extrasParent: extras ? extras.parentElement.className : null,
            };
        }""")
        assert not boundary["extrasInsideSection"], boundary
        assert boundary["extrasParent"] == "et_builder_inner_content et_pb_gutters3", boundary

    def test_disabled_submit_state_is_recognizable(self, page):
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        style = _style(pg, "form button.btn.btn-primary[disabled]")
        assert float(style["opacity"]) < 1, "disabled state must be visually distinct"
        assert style["cursor"] == "not-allowed", style["cursor"]
        # Label remains present for the recognisable state.
        assert style["color"] == WHITE
        assert contrast_ratio(style["color"], style["backgroundColor"]) >= 4.5

    def test_pricing_selection_carries_into_cta_url(self, page):
        """Real view.js: tier + additional shares must carry over into the CTA
        href (acceptance: Auswahlwerte werden ins Ziel übernommen). No click on
        the CTA itself — href is read only."""
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/bioco-werden.html",
                wait_until="load")
        pg.wait_for_timeout(400)
        initial = pg.get_attribute('[data-pc-field="cta"]', "href")
        assert "abo=halb-1-person" in initial and "shares=1" in initial and "additional=0" in initial

        pg.click('[data-tier-slug="standard-2-3-personen"]')
        pg.wait_for_timeout(200)
        moved = pg.get_attribute('[data-pc-field="cta"]', "href")
        assert "abo=standard-2-3-personen" in moved, moved
        assert "shares=2" in moved, moved

        pg.click('[data-pc-action="add-share"]')
        pg.wait_for_timeout(200)
        with_extra = pg.get_attribute('[data-pc-field="cta"]', "href")
        assert "shares=3" in with_extra and "additional=1" in with_extra, with_extra


class TestPlainLinksKeepLook:
    """Normale Textlinks behalten ihre Darstellung (#178 scope line)."""

    def test_text_link_not_buttonized(self, page):
        _, pg = page
        pg.goto(f"{page[0]}/tests/fixtures/button-readability/home.html",
                wait_until="load")
        style = _style(pg, "main a[href='/solawi']")
        assert style["color"] == GREEN, style["color"]
        assert style["clipPath"] == "none", style["clipPath"]
        assert style["boxShadow"] == "none", style["boxShadow"]
        assert style["borderRadius"] == "0px", style["borderRadius"]
