"""Remaining migration defects, exercised at rendered output boundaries."""
import base64
import json
import subprocess
from html.parser import HTMLParser
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"


class Links(HTMLParser):
    def __init__(self, markup):
        super().__init__()
        self.links = []
        self.tags = []
        self.feed(markup)

    def handle_starttag(self, tag, attrs):
        self.tags.append(tag)
        if tag == "a":
            self.links.append(dict(attrs))


def render_map(locations):
    encoded = base64.b64encode(json.dumps(locations).encode()).decode()
    code = f"""
define('ABSPATH', '/');
function esc_html($v) {{return htmlspecialchars((string)$v, ENT_QUOTES);}}
function esc_attr($v) {{return htmlspecialchars((string)$v, ENT_QUOTES);}}
function esc_url($v) {{return htmlspecialchars((string)$v, ENT_QUOTES);}}
function wp_json_encode($v) {{return json_encode($v);}}
require {json.dumps(str(CORE / 'includes/helpers.php'))};
$locations = json_decode(base64_decode('{encoded}'), true);
bioco_render_map_block($locations, 47.4, 8.3, 12, 'Standorte', 'Route planen', 'Leer');
"""
    return subprocess.check_output(["php", "-r", code], text=True)


def depot_locations():
    seed = json.loads((ROOT / "wordpress/content-seed/standorte-depots.json").read_text())
    return next(s for s in seed["sections"] if s.get("section_component") == "depot_map")["section_config"]["locations"]


def test_depot_websites_are_clickable_without_personal_contacts():
    markup = render_map(depot_locations())
    links = Links(markup).links
    websites = {
        "https://www.xn--chrttli-7wa.ch/", "https://www.ohne.ch/",
        "https://anixis.ch/", "http://lemonia.ch/",
    }
    assert websites <= {link.get("href") for link in links}
    for link in links:
        if link.get("href") in websites:
            assert set(link.get("rel", "").split()) >= {"noopener", "noreferrer"}
    assert not any(link.get("href", "").startswith(("tel:", "mailto:")) for link in links)


def test_map_description_escapes_markup_and_only_links_http_urls():
    markup = render_map([{
        "name": "<script>alert(1)</script>", "lat": 47, "lng": 8,
        "description": '<img src=x onerror=alert(1)> javascript:alert(1) https://example.test/?a=1&b=2.',
    }])
    parsed = Links(markup)
    assert "script" not in parsed.tags and "img" not in parsed.tags
    assert "https://example.test/?a=1&b=2" in {a.get("href") for a in parsed.links}
    assert not any(a.get("href", "").startswith("javascript:") for a in parsed.links)


def test_ci_installs_the_browser_required_by_its_capture_gate():
    workflow = (ROOT / ".github/workflows/deploy-wordpress-staging.yml").read_text()
    requirements = (ROOT / ".github/workflows/requirements-ci.txt").read_text()
    assert "playwright==" in requirements
    setup = workflow.index("python -m playwright install --with-deps chromium")
    assert setup < workflow.index('run: wordpress/scripts/release-wordpress-staging.sh')


def test_schnuppertag_seed_contains_the_existing_approved_arrival_guidance():
    seed = json.loads((ROOT / "wordpress/content-seed/tag-der-offenen-tuer.json").read_text())
    arrival = next(s for s in seed["sections"] if s["section_id"] == "anreise-parken")
    for instruction in ("Velo oder Bus", "nicht auf den Hof", "Wendeplatz", "unten an der Strasse"):
        assert instruction in arrival["section_text"]
    mirror = json.loads((ROOT / "cms/content-seed/tag-der-offenen-tuer.json").read_text())
    assert seed == mirror


@pytest.mark.parametrize("slug,minimal", [("anmeldung", True), ("kontakt", False), ("anmeldung-danke", False)])
def test_signup_uses_only_the_home_logo_and_omits_the_footer(slug, minimal):
    code = f"""
define('ABSPATH', '/');
function esc_html($v) {{return htmlspecialchars((string)$v, ENT_QUOTES);}}
function esc_attr($v) {{return htmlspecialchars((string)$v, ENT_QUOTES);}}
function esc_url($v) {{return htmlspecialchars((string)$v, ENT_QUOTES);}}
function home_url($path='/') {{return 'https://example.test'.$path;}}
function plugins_url($path, $file) {{return 'https://example.test/'.$path;}}
function is_page($slug) {{return $slug === {json.dumps(slug)};}}
require {json.dumps(str(CORE / 'includes/navigation.php'))};
echo json_encode(['header'=>bioco_render_primary_navigation(), 'footer'=>bioco_render_site_footer()]);
"""
    result = json.loads(subprocess.check_output(["php", "-r", code], text=True))
    header = Links(result["header"])
    if minimal:
        assert len(header.links) == 1
        assert header.links[0]["href"] == "https://example.test/"
        assert header.links[0].get("aria-label")
        assert "button" not in header.tags
        assert result["footer"] == ""
    else:
        assert len(header.links) > 1
        assert "button" in header.tags
        assert "footer" in Links(result["footer"]).tags


def test_depot_popups_link_websites_and_escape_editor_content():
    from playwright.sync_api import sync_playwright

    locations = depot_locations() + [{
        "name": '<img src=x onerror="alert(1)">', "lat": 47, "lng": 8,
        "description": '<script>alert(1)</script> javascript:alert(1) https://example.test/?a=1&b=2.',
    }]
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page()
        page.set_content('<section class="cms-depot-map">' + render_map(locations) + '</section><div id="popups"></div>')
        page.evaluate("""() => {
          window.BiocoConsent = {mountMap: (wrapper, start) => start(wrapper)};
          window.L = {
            map: () => ({setView() {return this;}, fitBounds() {}}),
            tileLayer: () => ({addTo() {}}),
            marker: () => ({addTo() {return this;}, bindPopup(html) {
              const popup = document.createElement('div');
              popup.innerHTML = html;
              document.querySelector('#popups').appendChild(popup);
            }}),
            featureGroup: () => ({getBounds: () => ({pad() {}})})
          };
        }""")
        page.add_script_tag(path=str(CORE / "blocks/depot-map/view.js"))
        links = page.locator('#popups a').evaluate_all('(links) => links.map(a => ({href: a.getAttribute("href"), rel:a.rel}))')
        assert {"https://www.xn--chrttli-7wa.ch/", "https://www.ohne.ch/", "https://anixis.ch/", "http://lemonia.ch/", "https://example.test/?a=1&b=2"} <= {a["href"] for a in links}
        assert all("noopener" in a["rel"] and "noreferrer" in a["rel"] for a in links)
        assert page.locator('#popups script, #popups img').count() == 0
        browser.close()
