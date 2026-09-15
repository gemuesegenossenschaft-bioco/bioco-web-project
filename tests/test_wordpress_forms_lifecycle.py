"""Real-browser regression for #181 (shared six-form lifecycle).

Opt-in browser suite: Chromium drives the REAL `render.php` output of all six
form blocks (rendered live by PHP with only WordPress runtime functions
stubbed), the REAL `bioco-forms-lifecycle.js` runtime and the REAL per-block
view scripts. Fetch and Cloudflare Turnstile are stubbed inside the browser
before any page script runs; every network request is intercepted — nothing
leaves the machine, no mail, no real submission, no production writes.

Run explicitly:

    BIOCO_FORMS_BROWSER_TESTS=1 python3 -m pytest tests/test_wordpress_forms_lifecycle.py -q

Requires: Playwright (Chromium), php CLI with ext-json, node (only used by the
dependency-compatibility harness in test_wordpress_membership_handoff.py).

With BIOCO_FORMS_BROWSER_TESTS=1 a missing Playwright install or missing
Chromium build FAILS the suite (the opt-in demands the stack); without the env
var the module is skipped so the WordPress staging release preflight
(`wordpress/scripts/release-wordpress-preflight.sh`, plain `pytest tests/`)
never requires Playwright on machines without it.

What the default (opt-out) suite does NOT cover here is stated honestly in
tests/README.md: native browser semantics (reportValidity bubbles, real
dispatchEvent propagation, widget lifecycle) only run under Chromium.
"""

import base64
import json
import os
import subprocess
import urllib.request
from pathlib import Path
from urllib.parse import urlparse

import pytest

REPO = Path(__file__).resolve().parents[1]
CORE = REPO / "wordpress/web/app/mu-plugins/bioco-core"
FORMS = REPO / "wordpress/web/app/mu-plugins/bioco-forms"
RUNTIME_REL = "/wp-content/mu-plugins/bioco-core/assets/bioco-forms-lifecycle.js"
VIEW_RELS = {
    slug: f"/wp-content/mu-plugins/bioco-core/blocks/{slug}/view.js"
    for slug in (
        "contact-form", "subscribe-form", "visit-day-form",
        "waiting-list-form", "event-signup-form", "membership-form",
    )
}

TEST_ORIGIN = "https://forms.test"
THANK_YOU_PATH = "/anmeldung-danke/"

OPT_IN_ENV = "BIOCO_FORMS_BROWSER_TESTS"
OPTED_IN = os.environ.get(OPT_IN_ENV) == "1"

# Loader-bound override for the test host: the runtime reads
# window.BIOCO_FORMS_TURNSTILE_TIMEOUT_MS when it evaluates (init scripts run
# before any page script), so the never-settling/spent-element waits stay
# short here instead of the shipped 4s default.
BIOCO_FORMS_TURNSTILE_TEST_TIMEOUT_MS = 300

pytestmark = pytest.mark.skipif(
    not OPTED_IN,
    reason=f"browser opt-in: set {OPT_IN_ENV}=1 to run Chromium checks (see module docstring)",
)


# ---------------------------------------------------------------------------
# Real markup: PHP renders each block's real render.php through the real
# dynamic-component seam, and the real bioco_forms_localize_block() contract
# produces the localized config objects the view scripts consume.
# ---------------------------------------------------------------------------

COMPONENT_KEYS = {
    "contact-form": "contact_form",
    "subscribe-form": "subscribe_form",
    "visit-day-form": "visit_day_form",
    "waiting-list-form": "waiting_list_form",
    "event-signup-form": "event_signup_form",
    "membership-form": "membership_form",
}

# Editorial inputs are test data, not shipped copy: they feed $title/$labels
# through the real render templates. Every visible form string the SHIPPED
# code adds (success/error/captcha copy, pending labels) is asserted from the
# adapter contracts, never redefined here.
FORM_VALUES = {
    "contact-form": {
        "anchor": "kontakt-formular",
        "title": "Kontaktprobe",
        "text": "Haben Sie Fragen?",
        "phone_label": "Telefon (optional)",
        "submit_label": "Nachricht senden",
        "submitting_label": "Wird gesendet",
    },
    "subscribe-form": {
        "anchor": "newsletter-anmeldung",
        "title": "Newsletterprobe",
        "text": "Bleiben Sie informiert.",
        "submit_label": "Abonnieren",
        "submitting_label": "Wird abonniert",
    },
    "visit-day-form": {
        "anchor": "schnuppertag-anmeldung",
        "title": "Schnuppertagprobe",
        "name_label": "Name",
        "email_label": "E-Mail",
        "phone_label": "Telefon",
        "date_label": "Datum",
        "participants_label": "Personen",
        "notes_label": "Notizen",
        "privacy_label": "Datenschutz ok",
        "submit_label": "Schnuppertag buchen",
        "submitting_label": "Wird gebucht",
    },
    "waiting-list-form": {
        "anchor": "warteliste-anmeldung",
        "title": "Wartelistenprobe",
        "name_label": "Name",
        "email_label": "E-Mail",
        "phone_label": "Telefon",
        "interest_label": "Interesse",
        "interest_placeholder": "Bitte wählen",
        "interest_options": [
            {"value": "gemuese", "label": "Gemüse"},
            {"value": "eier", "label": "Eier"},
        ],
        "notes_label": "Notizen",
        "privacy_label": "Datenschutz ok",
        "submit_label": "Auf die Warteliste",
        "submitting_label": "Wird eingetragen",
    },
    "event-signup-form": {
        "anchor": "event-anmeldung",
        "title": "Eventprobe",
        "event_title_prefix": "Anmeldung:",
        "name_label": "Name",
        "email_label": "E-Mail",
        "phone_label": "Telefon (optional)",
        "notes_label": "Notizen (optional)",
        "submit_label": "Anmelden",
        "submitting_label": "Wird angemeldet",
    },
    "membership-form": {
        "anchor": "mitgliedschaft-anmeldung",
        "title": "Mitgliedschaftsprobe",
        "text": "Werde Mitglied.",
        "commitment_title": "Verpflichtung",
        "commitment_intro": "Bitte bestätige.",
        "commitments": [
            {"heading": "Statuten", "text": "Ich akzeptiere die Statuten."},
            {"heading": "Genossenschaft", "text": "Ich trage die Genossenschaft mit."},
        ],
        "personal_title": "Personalien",
        "first_name_label": "Vorname",
        "last_name_label": "Nachname",
        "address_label": "Adresse",
        "zip_label": "PLZ",
        "city_label": "Ort",
        "phone_label": "Telefon",
        "email_label": "E-Mail",
        "depot_payment_title": "Depot und Zahlung",
        "depot_label": "Depot",
        "depot_placeholder": "Depot wählen",
        "depots": [{"option": "Baden"}, {"option": "Zürich"}],
        "payment_label": "Zahlungsweise",
        "payment_hint": "Hinweis",
        "quarterly_label": "Vierteljährlich",
        "annual_label": "Jährlich",
        "participation_title": "Mitarbeit",
        "participation_intro": "Hilf mit.",
        "preferred_days_label": "Tage",
        "preferred_days": [{"option": "Dienstag"}, {"option": "Freitag"}],
        "preferred_times_label": "Zeiten",
        "preferred_times": [{"option": "Morgen"}, {"option": "Abend"}],
        "activity_areas_label": "Bereiche",
        "activity_areas": [{"option": "Ernte"}, {"option": "Packerei"}],
        "other_activity_label": "Sonstiges",
        "additional_subscriptions_title": "Zusatzabos",
        "additional_subscriptions_intro": "Mehr Gemüse.",
        "additional_subscriptions": [{"option": "Eier-Abo"}, {"option": "Obst-Abo"}],
        "additional_products_label": "Weitere Produkte",
        "additional_products_hint": "Hinweis",
        "additional_products_placeholder": "Produkte",
        "confirmation_title": "Bestätigung",
        "privacy_label": "Datenschutz ok",
        "submit_label": "Mitglied werden",
        "submitting_label": "Wird übermittelt",
    },
}

# Message content comes from the same editorial seeds used by installation.
_MESSAGE_DEFAULTS = json.loads((REPO / "wordpress/content-seed/block-content/defaults.json").read_text())["blocks"]
for _slug, _values in FORM_VALUES.items():
    for _key in ("success_message", "fallback_error", "captcha_error", "transport_error"):
        if _key in _MESSAGE_DEFAULTS[_slug]:
            _values[_key] = _MESSAGE_DEFAULTS[_slug][_key]

_RENDER_PHP = r"""
define('ABSPATH', __DIR__);
$GLOBALS['BIOCO_ENQUEUED'] = [];
$GLOBALS['BIOCO_LOCALIZED'] = [];
function add_action($hook, $callback) {}
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function wp_enqueue_script(...$args) { $GLOBALS['BIOCO_ENQUEUED'][] = $args; }
function wp_enqueue_style(...$args) {}
function wp_localize_script($handle, $object, $values) {
    $GLOBALS['BIOCO_LOCALIZED'][$object] = $values;
}
function rest_url($path = '') { return 'https://forms.test/wp-json/' . $path; }
function esc_url_raw($value) { return (string) $value; }
function wp_get_environment_type() { return 'staging'; }
function get_the_ID() { return 77; }
function get_the_title() { return 'Jubiläumsfest'; }
function is_singular($post_type = '') { return true; }
function __($text, $domain = '') { return $text; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function wp_kses($html, $allowed) { return $html; }
putenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY=test-site-key');
putenv('TURNSTILE_SECRET_KEY=test-secret-only-server-side');
require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';
$values = json_decode(base64_decode($argv[1]), true);
$sections = [];
foreach ($values as $component => $value) {
    $sections[$component] = bioco_render_dynamic_component($component, $value);
}
echo base64_encode(json_encode([
    'configs' => $GLOBALS['BIOCO_LOCALIZED'],
    'sections' => $sections,
    'enqueued' => $GLOBALS['BIOCO_ENQUEUED'],
], JSON_UNESCAPED_UNICODE));
"""


@pytest.fixture(scope="module")
def rendered_fixture():
    values = {COMPONENT_KEYS[slug]: FORM_VALUES[slug] for slug in FORM_VALUES}
    result = subprocess.run(
        ["php", "-r", _RENDER_PHP, base64.b64encode(json.dumps(values).encode()).decode()],
        cwd=REPO,
        text=True,
        capture_output=True,
        check=False,
    )
    assert result.returncode == 0, result.stderr
    return json.loads(base64.b64decode(result.stdout))


def build_fixture_page(rendered_fixture, extra_contact_instance=True,
                       preenqueued_turnstile=False, omit_runtime=False):
    """One page carrying every form section twice for contact (isolation) and
    once otherwise, the real localized config objects, and the real scripts
    in WordPress load order (runtime dependency first, then view scripts).

    omit_runtime=True reproduces the missing-runtime-FILE case at the
    emitted level: when bioco-core cannot register the runtime handle, it
    keeps the adapters enqueued without the dependency and WordPress emits
    no runtime script at all (CodeRabbit finding, conditional dependency)."""
    parts = [
        "<!doctype html><html lang=\"de\"><head><meta charset=\"utf-8\">"
        "<title>bioco forms fixture</title></head><body>",
    ]
    for object_name, values in rendered_fixture["configs"].items():
        parts.append(
            f"<script>window[{json.dumps(object_name)}] = "
            f"{json.dumps(values, ensure_ascii=False)};</script>"
        )
    # data-config override (bioco-forms.php documents per-form config object
    # overrides): the second contact instance points at a custom object.
    parts.append(
        "<script>window.biocoContactFormConfigCustom = "
        + json.dumps({
            "restUrl": "https://forms.test/wp-json/bioco/v1/contact-alt",
            "turnstileSiteKey": "test-site-key",
        }, ensure_ascii=False)
        + ";</script>"
    )
    for slug in FORM_VALUES:
        parts.append(rendered_fixture["sections"][COMPONENT_KEYS[slug]])
    if extra_contact_instance:
        # Second instance of the same form: same rendered markup, distinct
        # anchor and a data-config override to a custom localized object —
        # mirrors the documented per-form config override.
        parts.append(rendered_fixture["sections"]["contact_form"].replace(
            'id="kontakt-formular"', 'id="kontakt-formular-zweit"'
        ).replace(
            'data-config="biocoContactFormConfig"',
            'data-config="biocoContactFormConfigCustom"',
        ))
    if preenqueued_turnstile:
        # The bioco-forms mu-plugin pre-enqueues the Turnstile script before
        # any form mounts (bioco_forms_localize_block -> wp_enqueue_script):
        # this variant ships that element in the page itself, ahead of the
        # runtime, so the loader must adopt an existing element.
        parts.append(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>'
        )
    if not omit_runtime:
        parts.append(f'<script src="{RUNTIME_REL}"></script>')
    for rel in VIEW_RELS.values():
        parts.append(f'<script src="{rel}"></script>')
    parts.append("</body></html>")
    return "".join(parts)


# ---------------------------------------------------------------------------
# Browser context: one Chromium context per test; every URL is routed through
# an explicit handler, everything unmatched is aborted (proves no real network).
# ---------------------------------------------------------------------------

STUBS_JS = r"""
window.__formTraffic = [];
window.__formResponder = null;
window.__turnstileNext = 0;
window.__turnstileWidgets = {};
window.__installTurnstile = function () {
    if (window.turnstile) return;
    window.turnstile = {
        render: function (container, opts) {
            const id = 'widget-' + (++window.__turnstileNext);
            const containers = Array.prototype.slice.call(
                document.querySelectorAll('[data-form-captcha]')
            );
            window.__turnstileWidgets[id] = Object.assign({}, opts, {
                containerIndex: containers.indexOf(container),
            });
            return id;
        },
        reset: function (id) {
            window.__turnstileWidgets[id + '::resets'] =
                (window.__turnstileWidgets[id + '::resets'] || 0) + 1;
        },
    };
};
window.__turnstileEmit = function (kind, containerIndex, token) {
    const id = Object.keys(window.__turnstileWidgets).find(
        (k) => window.__turnstileWidgets[k].containerIndex === containerIndex
    );
    if (!id) throw new Error('no widget for container ' + containerIndex);
    const opts = window.__turnstileWidgets[id];
    if (kind === 'token') opts.callback(token);
    else if (kind === 'expired') opts['expired-callback']();
    else if (kind === 'error' && opts['error-callback']) opts['error-callback']();
    return id;
};
window.__turnstileResets = function (containerIndex) {
    return Object.keys(window.__turnstileWidgets)
        .filter((k) => window.__turnstileWidgets[k].containerIndex === containerIndex)
        .reduce((sum, k) => sum + (window.__turnstileWidgets[k + '::resets'] || 0), 0);
};
if (window.__deferTurnstile !== true) window.__installTurnstile();
window.fetch = function (url, init) {
    let body = null;
    const raw = init && init.body ? String(init.body) : null;
    if (raw) { try { body = JSON.parse(raw); } catch (error) { body = raw; } }
    const entry = {
        url: String(url),
        method: (init && init.method) || 'GET',
        headers: Object.assign({}, init && init.headers),
        body: body,
        response: null,
    };
    const archive = function () {
        window.__formTraffic.push(entry);
        try {
            const archived = JSON.parse(sessionStorage.getItem('__formArchive') || '[]');
            archived.push(entry);
            sessionStorage.setItem('__formArchive', JSON.stringify(archived));
        } catch (error) { /* archive is best effort; traffic stays authoritative */ }
    };
    const result = window.__formResponder
        ? window.__formResponder(entry)
        : { status: 200, body: { success: true } };
    if (result && result.hang) { archive(); return new Promise(function () {}); }
    entry.response = { status: result.status || 200, body: result.body };
    archive();
    return Promise.resolve(new Response(JSON.stringify(result.body), {
        status: result.status || 200,
        headers: { 'Content-Type': 'application/json' },
    }));
};
"""


@pytest.fixture()
def browser_ctx():
    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        pytest.fail(
            f"{OPT_IN_ENV}=1 is set but playwright is not installed; "
            "install it (pip install playwright && playwright install chromium)"
        )
    with sync_playwright() as playwright:
        try:
            browser = playwright.chromium.launch(headless=True)
        except Exception as error:
            pytest.fail(
                f"{OPT_IN_ENV}=1 is set but Chromium is not available ({error}); "
                "run: playwright install chromium"
            )
        yield browser
        browser.close()


@pytest.fixture()
def make_page(browser_ctx, rendered_fixture):
    """Factory for fresh pages with the full fixture page served from memory.

    defer_turnstile=True starts without a Turnstile global: the runtime must
    append its own script element, which the router fails once and then
    serves — exercising the shared loader's error path and its recovery.

    preenqueued_no_global=True ships the mu-plugin's pre-enqueued Turnstile
    element ahead of the runtime and serves that first request a body that
    loads without defining the global (spent load event before any listener
    could exist). The loader bound is shortened for the test host.

    hang_turnstile=True never settles the first Turnstile script request at
    all: no load, no error, no response — the appended-script case the
    loader bound must recover from.
    """
    fixture_html = build_fixture_page(rendered_fixture)
    preenqueued_fixture_html = build_fixture_page(
        rendered_fixture, preenqueued_turnstile=True
    )
    omit_runtime_fixture_html = build_fixture_page(
        rendered_fixture, omit_runtime=True
    )
    turnstile_api_requests = []
    held_routes = []  # routes deliberately left pending (hung-request scenarios)

    def make(defer_turnstile=False, fail_runtime=False,
             preenqueued_no_global=False, hang_turnstile=False,
             omit_runtime=False):
        context = browser_ctx.new_context()
        # Init scripts run in order before any page script: the defer flag
        # must be in place BEFORE STUBS_JS decides whether to pre-install the
        # stub global, and the loader-bound override before the runtime reads
        # it when it evaluates.
        init_scripts = []
        if defer_turnstile or preenqueued_no_global or hang_turnstile:
            init_scripts.append("window.__deferTurnstile = true;")
        if preenqueued_no_global or hang_turnstile:
            init_scripts.append(
                f"window.__deferTurnstile = true; "
                f"window.BIOCO_FORMS_TURNSTILE_TIMEOUT_MS = "
                f"{BIOCO_FORMS_TURNSTILE_TEST_TIMEOUT_MS};"
            )
        init_scripts.append(STUBS_JS)
        for script in init_scripts:
            context.add_init_script(script)

        turnstile_stub_body = "window.__installTurnstile && window.__installTurnstile();"

        def handle_route(route):
            url = urlparse(route.request.url)
            path = url.path
            if path == "/bioco-forms/":
                route.fulfill(
                    status=200, content_type="text/html; charset=utf-8",
                    body=(omit_runtime_fixture_html if omit_runtime else
                          preenqueued_fixture_html if preenqueued_no_global
                          else fixture_html),
                )
            elif path == THANK_YOU_PATH:
                route.fulfill(
                    status=200,
                    content_type="text/html; charset=utf-8",
                    body="<!doctype html><html lang=\"de\"><head><meta charset=\"utf-8\">"
                    "<title>Danke</title></head><body><h1 id=\"thanks\">Anmeldung danke</h1></body></html>",
                )
            elif path.startswith("/wp-content/mu-plugins/"):
                if fail_runtime and path == RUNTIME_REL:
                    route.abort()  # shared runtime failed to load
                    return
                real = REPO / "wordpress/web/app/mu-plugins" / path[
                    len("/wp-content/mu-plugins/"):]
                if real.is_file():
                    route.fulfill(
                        status=200,
                        content_type="text/javascript; charset=utf-8",
                        body=real.read_text(encoding="utf-8"),
                    )
                else:
                    route.abort()  # a missing real source must behave like a 404
            elif path == "/turnstile/v0/api.js" or "challenges.cloudflare.com" in url.netloc:
                turnstile_api_requests.append(path)
                if len(turnstile_api_requests) > 1:
                    # Any retry after the first attempt gets a working script.
                    route.fulfill(
                        status=200,
                        content_type="text/javascript; charset=utf-8",
                        body=turnstile_stub_body,
                    )
                elif hang_turnstile:
                    # Deliberately never settles: no load, no error event.
                    # Tracked so teardown can abort it BEFORE the context
                    # closes — an unhandled pending route at context close
                    # surfaces as asyncio cancellation noise that can
                    # masquerade as a failure.
                    held_routes.append(route)
                    return
                elif preenqueued_no_global:
                    route.fulfill(
                        status=200,
                        content_type="text/javascript; charset=utf-8",
                        body="window.__biocoNoGlobalScriptRan = true;"
                        " /* script executes but never defines the global */",
                    )
                elif defer_turnstile:
                    route.abort()  # first load attempt fails (script error path)
                else:
                    route.fulfill(
                        status=200,
                        content_type="text/javascript; charset=utf-8",
                        body=turnstile_stub_body,
                    )
            else:
                route.abort()  # proves no real mail/submission network ever happens

        context.route("**/*", handle_route)
        page = context.new_page()
        # In hang mode the appended script request never settles and would
        # block the window load event forever; DOMContentLoaded still fires
        # (async scripts do not block parsing), so observe from there.
        page.goto(
            f"{TEST_ORIGIN}/bioco-forms/",
            wait_until="domcontentloaded" if hang_turnstile else "load",
        )
        page.wait_for_timeout(100)  # DOMContentLoaded wiring after script execution
        return page

    def settle_held_routes():
        # Abort routes the scenarios deliberately left pending, in the
        # Playwright thread, before the context closes.
        for route in held_routes:
            try:
                route.abort()
            except Exception:
                pass  # already settled or context gone
        del held_routes[:]

    try:
        yield make
    finally:
        settle_held_routes()


@pytest.fixture()
def page(make_page):
    return make_page()


def contact_section(page, anchor="kontakt-formular"):
    return page.locator(f"#{anchor}")


def submit_button(form):
    return form.locator('button[type="submit"]')


# ---------------------------------------------------------------------------
# Tracer: real DOM contact submission through the real scripts.
# ---------------------------------------------------------------------------

CONTACT_SUCCESS = (
    "Vielen Dank für deine Nachricht! Wir melden uns so schnell wie möglich bei dir."
)
CONTACT_VALUES = {
    "name": "Anna Muster",
    "email": "anna@example.com",
    "phone": "+41 79 000 00 00",
    "subject": "Depotfrage",
    "message": "Guten Tag, ich habe eine Frage zum Depot.",
}


def fill_contact(page, anchor="kontakt-formular", **overrides):
    values = dict(CONTACT_VALUES, **overrides)
    form = contact_section(page, anchor).locator("form[data-form=\"contact\"]")
    for name, value in values.items():
        form.locator(f'[name="{name}"]').fill(value)
    return form


def test_contact_tracer_real_dom_submit(page):
    """Real click → real submit event → shared engine → expected payload.

    RED against the pre-#181 engines: they carry no native-validity gate (an
    invalid form with a captcha token still POSTs) and no in-flight guard (a
    second dispatched submit duplicates the request).
    """
    form = contact_section(page).locator("form[data-form=\"contact\"]")

    # Native validity: required fields empty, captcha token present.
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-contact-1')")
    submit_button(form).click()
    assert page.evaluate("window.__formTraffic") == []

    # Valid values: exactly one JSON POST to the localized endpoint.
    fill_contact(page)
    submit_button(form).click()
    traffic = page.evaluate("window.__formTraffic")
    assert traffic == [
        {
            "url": "https://forms.test/wp-json/bioco/v1/contact",
            "method": "POST",
            "headers": {"Content-Type": "application/json"},
            "body": dict(CONTACT_VALUES, captchaToken="tkn-contact-1"),
            "response": {"status": 200, "body": {"success": True}},
        }
    ]

    # Success: only this form hides, exact adapter copy, aria-live sibling.
    message = contact_section(page).locator(".form-message")
    page.wait_for_function(
        "document.querySelector('#kontakt-formular form').hidden === true"
    )
    assert message.text_content() == CONTACT_SUCCESS
    assert message.get_attribute("class") == "form-message bento-card form-success"
    assert submit_button(form).is_disabled()


def test_contact_duplicate_submit_sends_one_request(page):
    """Second dispatched submit while pending must not duplicate the request."""
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    fill_contact(page)
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-contact-1')")
    # Braced arrow: assigning a function as the last expression would make
    # Playwright invoke the completion value (without arguments).
    page.evaluate("() => { window.__formResponder = function () { return { hang: true }; }; }")
    submit_button(form).click()
    page.evaluate(
        "document.querySelector('#kontakt-formular form')"
        ".dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert len(traffic) == 1, traffic
    assert traffic[0]["body"]["name"] == "Anna Muster"


CONTACT_FALLBACK_ERROR = (
    "Deine Nachricht konnte nicht gesendet werden. Bitte versuche es erneut "
    "oder sende uns eine E-Mail direkt an info@bioco.ch"
)
CAPTCHA_ERROR = "Bitte bestätige, dass du kein Roboter bist."
TURNSTILE_LOAD_FAILED_MESSAGE = (
    "Die Sicherheitsprüfung konnte nicht geladen werden. "
    "Bitte lade die Seite neu und versuche es nochmals."
)
CANONICAL_TURNSTILE_SRC = "https://challenges.cloudflare.com/turnstile/v0/api.js"
CONTACT_SERVER_ERROR = "Bitte prüfe deine Eingaben und versuche es noch einmal."


def test_contact_error_retry_keeps_values_restores_controls(page):
    """HTTP JSON failure: values survive, controls restore, token invalidated.

    The submit button renders without a usable data-submit-label here, so the
    restore must fall back to the label captured at mount, not to whatever the
    button currently shows.
    """
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    submit = submit_button(form)
    message = contact_section(page).locator(".form-message")
    fill_contact(page)
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-old-1')")
    page.evaluate(
        "document.querySelector('#kontakt-formular button[type=submit]')"
        ".removeAttribute('data-submit-label');"
    )
    page.evaluate(
        "() => { window.__formResponder = function () { return { status: 400, body: "
        "{ success: false, error: '%s' } }; }; }" % CONTACT_SERVER_ERROR
    )
    submit.click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular .form-message').hidden === false"
    )
    assert message.text_content() == CONTACT_SERVER_ERROR
    assert message.get_attribute("class") == "form-message bento-card form-error"
    # Values retained after a fixable error.
    assert form.locator('[name="name"]').input_value() == "Anna Muster"
    assert form.locator('[name="email"]').input_value() == "anna@example.com"
    # Controls restored; label back to the mount-time caption, not the
    # pending caption ("Wird gesendet").
    assert submit.is_enabled()
    assert submit.text_content() == "Nachricht senden"
    # Old token invalidated; only this form's widget resets.
    assert page.evaluate("window.__turnstileResets(0)") == 1
    assert page.evaluate("window.__turnstileResets(1)") == 0

    # Retry requires a fresh captcha token: without one the request is blocked.
    submit.click()
    assert len(page.evaluate("window.__formTraffic")) == 1
    assert message.text_content() == CAPTCHA_ERROR

    # Fresh token: retry succeeds with the retained values.
    page.evaluate("window.__formResponder = null;")
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-fresh-1')")
    submit.click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert len(traffic) == 2
    assert traffic[1]["body"] == dict(CONTACT_VALUES, captchaToken="tkn-fresh-1")


def test_contact_malformed_json_and_network_failure_are_errors(page):
    """HTTP 200 non-JSON must not turn into success; network failure keeps
    the form usable with the adapter's own fallback copy."""
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    message = contact_section(page).locator(".form-message")
    fill_contact(page)
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-m-1')")
    page.evaluate(
        "() => { window.__formResponder = function () { return { malformed: true }; }; }"
    )
    # Malformed-JSON responder: a 200 with a non-JSON body. Braced arrow so
    # the assigned function is not the completion value (Playwright would
    # auto-invoke it without arguments).
    page.evaluate(
        "() => { window.fetch = function (url, init) { "
        "const entry = { url: String(url), method: (init && init.method) || 'POST', "
        "headers: Object.assign({}, init && init.headers), "
        "body: init && init.body ? JSON.parse(init.body) : null };"
        "window.__formTraffic.push(entry);"
        "return Promise.resolve(new Response('<!doctype html><p>oops</p>', { status: 200 })); }; }"
    )
    submit_button(form).click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular .form-message').hidden === false"
    )
    assert message.text_content() == CONTACT_FALLBACK_ERROR
    assert submit_button(form).is_enabled()

    # Network rejection (fetch rejects): adapter fallback copy.
    page.evaluate(
        "() => { window.fetch = function () { return Promise.reject(new TypeError('Failed to fetch')); }; }"
    )
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-m-2')")
    submit_button(form).click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular button[type=submit]').disabled === false"
    )
    assert message.text_content() == CONTACT_FALLBACK_ERROR
    assert submit_button(form).is_enabled()
    assert submit_button(form).text_content() == "Nachricht senden"


def test_contact_captcha_expiry_and_widget_error_fail_closed(page):
    """Expired token or widget error clears the token: the next submit sends
    nothing and shows the captcha copy instead."""
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    message = contact_section(page).locator(".form-message")
    fill_contact(page)
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-exp-1')")
    page.evaluate("window.__turnstileEmit('expired', 0)")
    submit_button(form).click()
    assert page.evaluate("window.__formTraffic") == []
    assert message.text_content() == CAPTCHA_ERROR

    # Widget error path: token granted, then the widget errors. The token is
    # invalidated and the ERRORING WIDGET resets (per-widget recovery); other
    # widgets must be untouched.
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-exp-2')")
    page.evaluate("window.__turnstileEmit('error', 0)")
    assert page.evaluate("window.__turnstileResets(0)") == 1
    assert page.evaluate("window.__turnstileResets(1)") == 0
    submit_button(form).click()
    assert page.evaluate("window.__formTraffic") == []
    assert message.text_content() == CAPTCHA_ERROR
    assert submit_button(form).is_enabled()

    # Recovery with a fresh token works.
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-exp-3')")
    submit_button(form).click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular form').hidden === true"
    )
    assert page.evaluate("window.__formTraffic[0].body.captchaToken") == "tkn-exp-3"


def _submit_all_forms_attempting_native_leak(page):
    """Fills every form with abuse-marker values and clicks submit, then
    asserts no navigation and no request happened (fail-closed check)."""
    anchors = [
        ("kontakt-formular", "contact"),
        ("kontakt-formular-zweit", "contact"),
        ("newsletter-anmeldung", "subscribe"),
        ("schnuppertag-anmeldung", "visit-day"),
        ("warteliste-anmeldung", "waiting-list"),
        ("event-anmeldung", "event-signup"),
        ("mitgliedschaft-anmeldung", "membership"),
    ]
    for anchor, endpoint in anchors:
        form = page.locator(f"#{anchor} form[data-form=\"{endpoint}\"]")
        for field in form.locator("input[name], textarea[name]").all():
            field_type = field.get_attribute("type")
            if field_type in ("hidden", "checkbox", "radio", "number", "date", "email"):
                continue
            field.fill("Missbrauchsversuch")
        form.locator('button[type="submit"]').click()

    page.wait_for_timeout(200)
    assert urlparse(page.url).query == ""
    assert urlparse(page.url).path == "/bioco-forms/"
    assert page.evaluate("window.__formTraffic") == []
    # Every form is still visible and interactive after the blocked submit.
    for anchor, endpoint in anchors:
        form = page.locator(f"#{anchor} form[data-form=\"{endpoint}\"]")
        assert form.evaluate("el => !el.hidden")
    return anchors


def test_failed_runtime_blocks_native_submission(make_page):
    """If the shared runtime script FAILS TO LOAD over the network but a thin
    adapter still loads, the forms must not leak personal data through a
    native GET submission: the adapter blocks the submit event (root
    reproduced the pre-#181 native GET with actual PHP markup:
    forms-missing-runtime-red.json)."""
    page = make_page(fail_runtime=True)

    # The runtime is really absent.
    assert page.evaluate("typeof window.BiocoForms") == "undefined"
    _submit_all_forms_attempting_native_leak(page)


def test_missing_runtime_file_leaves_adapters_enqueued_and_fail_closed(make_page):
    """CodeRabbit blocker proof, browser half: bioco-core registers the six
    view handles with a CONDITIONAL dependency, so a missing runtime FILE
    (the runtime script is never emitted — WordPress silently omits scripts
    whose dependency handle is not registered) still ships every adapter,
    whose own no-runtime listeners block the native GET for all six forms.
    The fixture omits the runtime script tag entirely, which is stricter
    than aborting a registered URL."""
    page = make_page(omit_runtime=True)

    # The runtime handle never registered: no runtime script in the document.
    assert page.evaluate("typeof window.BiocoForms") == "undefined"
    assert page.evaluate(
        "document.querySelector('script[src*=\"bioco-forms-lifecycle\"]')"
    ) is None
    _submit_all_forms_attempting_native_leak(page)


def test_captcha_script_error_recovers_on_later_attempt(make_page):
    """Shared loader: first script load fails -> form stays inert and usable;
    a later submit retries the load once and recovers with a fresh widget."""
    page = make_page(defer_turnstile=True)
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    message = contact_section(page).locator(".form-message")
    fill_contact(page)

    # First load attempt aborted by the router: no widget, submit fails closed.
    submit_button(form).click()
    assert page.evaluate("window.__formTraffic") == []
    assert message.text_content() == CAPTCHA_ERROR
    assert submit_button(form).is_enabled()

    # The retry (submit while no widget rendered) re-appends the canonical
    # script and the router now serves it: a widget appears and a fresh
    # token submits. A completed ERROR stays retryable with the canonical
    # URL (the request is finished — nothing coalesces with it).
    submit_button(form).click()  # still blocked; the retry load runs in background
    page.wait_for_function(
        "window.turnstile && typeof window.turnstile.render === 'function'"
    )
    assert page.evaluate(
        "document.getElementById('bioco-cf-turnstile-js').getAttribute('src')"
    ) == CANONICAL_TURNSTILE_SRC
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-rec-1')")
    submit_button(form).click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic[0]["body"] == dict(CONTACT_VALUES, captchaToken="tkn-rec-1")


def test_preenqueued_turnstile_spent_load_recovers(make_page):
    """Regression: the mu-plugin pre-enqueues the Turnstile element before
    any form mounts, and its load event may fire before the loader can
    listen. When that pre-enqueued script also loads WITHOUT defining the
    global, the bounded wait must settle, the spent element must be removed,
    no request may leak, and a later submit must retry with a fresh element."""
    page = make_page(preenqueued_no_global=True)
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    message = contact_section(page).locator(".form-message")

    # The pre-enqueued element really was served and executed before the
    # loader could listen (spent load event), and never defined the global.
    assert page.evaluate("window.__biocoNoGlobalScriptRan === true")
    assert page.evaluate("typeof window.turnstile") == "undefined"
    # The bounded wait settles and the spent element is removed.
    page.wait_for_function(
        "document.getElementById('bioco-cf-turnstile-js') === null"
    )
    assert page.evaluate("typeof window.turnstile") == "undefined"

    # First submit is blocked (fail closed), stays usable.
    fill_contact(page)
    submit_button(form).click()
    assert page.evaluate("window.__formTraffic") == []
    assert message.text_content() == CAPTCHA_ERROR
    assert submit_button(form).is_enabled()

    # Retry: the loader appends a FRESH script element from the canonical
    # URL (the spent one is gone), the router serves a working stub and a
    # widget renders. A completed (executed-without-global) attempt stays
    # retryable with the canonical URL — no URL workaround is involved.
    submit_button(form).click()
    page.wait_for_function(
        "Object.keys(window.__turnstileWidgets)"
        ".filter(k => k.indexOf('::') === -1).length === 1"
    )
    assert page.evaluate("document.getElementById('bioco-cf-turnstile-js') !== null")
    assert page.evaluate(
        "document.getElementById('bioco-cf-turnstile-js').getAttribute('src')"
    ) == CANONICAL_TURNSTILE_SRC
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-pre-1')")
    submit_button(form).click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic[0]["body"] == dict(CONTACT_VALUES, captchaToken="tkn-pre-1")


def test_hung_turnstile_fails_closed_to_manual_reload_contract(make_page):
    """Bounded timeout + explicit manual-reload contract for an indefinitely
    hung Turnstile transport (root decision; root's HTTP302 proof shows a
    distinct entry URL does NOT guarantee a fresh download — Cloudflare
    redirects every entry variant onto one shared final resource, so no
    URL workaround is claimed). The bound must latch the failure, keep
    values/caption intact, never navigate automatically, and further
    submits must stay bounded: no new script elements, no widgets, no API
    submit. Success after a real settled ERROR stays covered by the
    script-error and load-without-global tests."""
    page = make_page(hang_turnstile=True)
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    message = contact_section(page).locator(".form-message")
    fill_contact(page)

    # The loader appended its own script with the canonical URL (no invented
    # query bypass); the request is deliberately held pending.
    script = page.locator("#bioco-cf-turnstile-js")
    assert script.count() == 1
    assert script.get_attribute("src") == "https://challenges.cloudflare.com/turnstile/v0/api.js"

    # The bound settles the hung attempt, removes the dead element and
    # latches the transport failure.
    page.wait_for_function(
        "document.getElementById('bioco-cf-turnstile-js') === null"
    )

    # From then on every submit shows the actionable German copy — no
    # captcha-required promise, no fresh attempt, no endless dead scripts.
    for _ in range(3):
        submit_button(form).click()
        page.wait_for_timeout(50)
    assert message.text_content() == TURNSTILE_LOAD_FAILED_MESSAGE
    assert page.evaluate("document.getElementById('bioco-cf-turnstile-js') === null")
    assert page.evaluate(
        "Object.keys(window.__turnstileWidgets).length"
    ) == 0

    # Values and caption intact; controls usable; no navigation, no API
    # submit, no automatic reload.
    assert form.locator('[name="name"]').input_value() == "Anna Muster"
    assert form.locator('[name="email"]').input_value() == "anna@example.com"
    assert submit_button(form).is_enabled()
    assert submit_button(form).text_content() == "Nachricht senden"
    assert urlparse(page.url).path == "/bioco-forms/"
    assert urlparse(page.url).query == ""
    assert page.evaluate("window.__formTraffic") == []


def test_late_turnstile_global_recovers_without_new_transport(make_page):
    """The latched hung transport must not brick the form: if the Turnstile
    API appears later from any other source, the next submit renders a
    widget again WITHOUT a new script element (the loader never touches the
    network once latched), and the form submits normally with a fresh token."""
    page = make_page(hang_turnstile=True)
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    message = contact_section(page).locator(".form-message")
    fill_contact(page)

    # Latch the hung transport first.
    page.wait_for_function(
        "document.getElementById('bioco-cf-turnstile-js') === null"
    )
    submit_button(form).click()
    page.wait_for_timeout(50)
    assert message.text_content() == TURNSTILE_LOAD_FAILED_MESSAGE

    # The API appears later from another source (another integration,
    # service worker cache — anything that defines the global).
    page.evaluate("window.__installTurnstile && window.__installTurnstile()")
    page.wait_for_function(
        "window.turnstile && typeof window.turnstile.render === 'function'"
    )

    # Next submit: the loader creates NO new script element; the late
    # global renders the widget. The synchronous submit handler still runs
    # before the async render assigns the widget, so the truthful
    # transitional copy is the captcha-required one (a widget is rendering
    # and a token is awaited), never the stale "reload" copy.
    submit_button(form).click()
    page.wait_for_function(
        "Object.keys(window.__turnstileWidgets)"
        ".filter(k => k.indexOf('::') === -1).length === 1"
    )
    assert page.evaluate("document.getElementById('bioco-cf-turnstile-js') === null")
    assert message.text_content() == CAPTCHA_ERROR
    assert page.evaluate("window.__formTraffic") == []

    # A fresh token submits normally.
    page.evaluate("window.__turnstileEmit('token', 0, 'tkn-late-1')")
    submit_button(form).click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic[0]["body"] == dict(CONTACT_VALUES, captchaToken="tkn-late-1")


# ---------------------------------------------------------------------------
# Six-adapter contract matrix: payloads, endpoints, per-adapter copy.
# ---------------------------------------------------------------------------

SUBSCRIBE_SUCCESS = (
    "Vielen Dank! Bitte bestätige deine Anmeldung über den Link in der "
    "E-Mail, die wir dir gesendet haben."
)
SIGNUP_SUCCESS = (
    "Vielen Dank für deine Anmeldung! Wir melden uns so schnell wie möglich bei dir."
)
EVENT_SUCCESS = (
    "Anmeldung erfolgreich! Vielen Dank für deine Anmeldung. Wir melden uns bei dir."
)
GENERIC_ERROR = "Es ist ein Fehler aufgetreten. Bitte versuche es erneut."
EVENT_ERROR = (
    "Die Anmeldung konnte nicht gesendet werden. Bitte versuche es erneut "
    "oder kontaktiere uns direkt."
)

# Widget container order follows DOM order across the page: contact(1)=0,
# subscribe=1, visit=2, waiting=3, event=4, membership=5, contact(2)=6.
CONTAINER_INDEX = {
    "contact": 0, "subscribe": 1, "visit-day": 2,
    "waiting-list": 3, "event-signup": 4, "membership": 5,
}

SECTION_ANCHORS = {
    "subscribe": "newsletter-anmeldung",
    "visit-day": "schnuppertag-anmeldung",
    "waiting-list": "warteliste-anmeldung",
    "event-signup": "event-anmeldung",
    "membership": "mitgliedschaft-anmeldung",
}


def adapter_form(page, endpoint, anchor=None):
    return page.locator(
        f"#{anchor or SECTION_ANCHORS[endpoint]} form[data-form=\"{endpoint}\"]"
    )


def emit_token(page, endpoint, token):
    page.evaluate(
        f"window.__turnstileEmit('token', {CONTAINER_INDEX[endpoint]}, {json.dumps(token)})"
    )


def test_subscribe_newsletter_doi_contract(page):
    """Newsletter payload (scalar privacy boolean, optional name) and the
    double-opt-in wording that only the newsletter flow may use."""
    form = adapter_form(page, "subscribe")
    # Native required gate: privacy unchecked (and email empty) sends nothing.
    emit_token(page, "subscribe", "tkn-sub-0")
    form.locator('[name="email"]').fill("newsletter@example.com")
    form.locator('button[type="submit"]').click()
    assert page.evaluate("window.__formTraffic") == []
    assert not form.get_attribute("hidden")

    form.locator('[name="privacy_accept"]').check()
    form.locator('[name="name"]').fill("Nina Neu")
    form.locator('button[type="submit"]').click()
    page.wait_for_function(
        "document.querySelector('#newsletter-anmeldung form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic == [{
        "url": "https://forms.test/wp-json/bioco/v1/subscribe",
        "method": "POST",
        "headers": {"Content-Type": "application/json"},
        "body": {
            "email": "newsletter@example.com",
            "name": "Nina Neu",
            "privacy_accept": True,
            "captchaToken": "tkn-sub-0",
        },
        "response": {"status": 200, "body": {"success": True}},
    }]
    message = page.locator("#newsletter-anmeldung .form-message")
    assert message.text_content() == SUBSCRIBE_SUCCESS


def test_visit_day_and_waiting_list_signups(page):
    """Visit-day participants serialize as a number and honour min=1;
    waiting-list keeps the CMS interest value unchanged; both use the
    immediate-mail success copy (no DOI wording)."""
    visit = adapter_form(page, "visit-day")
    # Native min gate: participants below 1 blocks the request entirely.
    emit_token(page, "visit-day", "tkn-visit-0")
    visit.locator('[name="name"]').fill("Vera Besuch")
    visit.locator('[name="email"]').fill("vera@example.com")
    visit.locator('[name="phone"]').fill("+41 56 000 00 00")
    visit.locator('[name="visit_date"]').fill("2026-10-01")
    visit.locator('[name="participants"]').fill("0")
    visit.locator('[name="privacy_accept"]').check()
    visit.locator('button[type="submit"]').click()
    assert page.evaluate("window.__formTraffic") == []

    visit.locator('[name="participants"]').fill("3")
    visit.locator('[name="notes"]').fill("Bringt Kinder mit")
    visit.locator('button[type="submit"]').click()
    page.wait_for_function(
        "document.querySelector('#schnuppertag-anmeldung form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic == [{
        "url": "https://forms.test/wp-json/bioco/v1/visit-day",
        "method": "POST",
        "headers": {"Content-Type": "application/json"},
        "body": {
            "name": "Vera Besuch",
            "email": "vera@example.com",
            "phone": "+41 56 000 00 00",
            "visit_date": "2026-10-01",
            "participants": 3,
            "notes": "Bringt Kinder mit",
            "privacy_accept": True,
            "captchaToken": "tkn-visit-0",
        },
        "response": {"status": 200, "body": {"success": True}},
    }]
    visit_message = page.locator("#schnuppertag-anmeldung .form-message")
    assert visit_message.text_content() == SIGNUP_SUCCESS

    waiting = adapter_form(page, "waiting-list")
    waiting.locator('[name="name"]').fill("Walter Wartet")
    waiting.locator('[name="email"]').fill("walter@example.com")
    waiting.locator('[name="phone"]').fill("+41 61 000 00 00")
    waiting.locator('[name="interest"]').select_option("eier")  # value, not label
    waiting.locator('[name="notes"]').fill("Abosieren")
    waiting.locator('[name="privacy_accept"]').check()
    emit_token(page, "waiting-list", "tkn-wait-1")
    waiting.locator('button[type="submit"]').click()
    page.wait_for_function(
        "document.querySelector('#warteliste-anmeldung form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic[1]["url"] == "https://forms.test/wp-json/bioco/v1/waiting-list"
    assert traffic[1]["body"] == {
        "name": "Walter Wartet",
        "email": "walter@example.com",
        "phone": "+41 61 000 00 00",
        "interest": "eier",
        "notes": "Abosieren",
        "privacy_accept": True,
        "captchaToken": "tkn-wait-1",
    }
    assert page.locator("#warteliste-anmeldung .form-message").text_content() == SIGNUP_SUCCESS


def test_event_signup_hidden_metadata_and_informal_copy(page):
    """Event hidden metadata (eventId/eventTitle) stay strings and ride along;
    success and error copy is the informal event wording."""
    form = adapter_form(page, "event-signup")
    form.locator('[name="name"]').fill("Eva Event")
    form.locator('[name="email"]').fill("eva@example.com")
    form.locator('[name="notes"]').fill("Plus Begleitung")
    emit_token(page, "event-signup", "tkn-event-1")
    form.locator('button[type="submit"]').click()
    page.wait_for_function(
        "document.querySelector('#event-anmeldung form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic == [{
        "url": "https://forms.test/wp-json/bioco/v1/event-signup",
        "method": "POST",
        "headers": {"Content-Type": "application/json"},
        "body": {
            "eventId": "77",  # get_the_ID() stub, string in the payload
            "eventTitle": "Jubiläumsfest",
            "name": "Eva Event",
            "email": "eva@example.com",
            "phone": "",
            "notes": "Plus Begleitung",
            "captchaToken": "tkn-event-1",
        },
        "response": {"status": 200, "body": {"success": True}},
    }]
    assert page.locator("#event-anmeldung .form-message").text_content() == EVENT_SUCCESS


def wait_for_message_shown(page, anchor):
    page.wait_for_function(
        f"document.querySelector('#{anchor} .form-message').hidden === false"
    )
    return page.locator(f"#{anchor} .form-message")


def test_server_error_text_displayed_verbatim_per_adapter(page):
    """HTTP 400 JSON with a server-provided error: the text is displayed
    verbatim through textContent (no HTML insertion) in each adapter's own
    message box. Subscription wording must never replace the event's
    informal wording or vice versa."""
    page.evaluate(
        "() => { window.__formResponder = function (entry) {"
        "  const isEvent = entry.url.indexOf('event-signup') !== -1;"
        "  return { status: 400, body: { success: false, error: isEvent"
        "    ? 'Serverfehler Event' : 'Serverfehler allgemein' } }; }; }"
    )

    subscribe = adapter_form(page, "subscribe")
    subscribe.locator('[name="email"]').fill("newsletter@example.com")
    subscribe.locator('[name="privacy_accept"]').check()
    emit_token(page, "subscribe", "tkn-err-sub")
    subscribe.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "newsletter-anmeldung").text_content() == (
        "Serverfehler allgemein"
    )

    event = adapter_form(page, "event-signup")
    event.locator('[name="name"]').fill("Eva Event")
    event.locator('[name="email"]').fill("eva@example.com")
    emit_token(page, "event-signup", "tkn-err-event")
    event.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "event-anmeldung").text_content() == (
        "Serverfehler Event"
    )

    waiting = adapter_form(page, "waiting-list")
    waiting.locator('[name="name"]').fill("Walter Wartet")
    waiting.locator('[name="email"]').fill("walter@example.com")
    waiting.locator('[name="phone"]').fill("+41 61 000 00 00")
    waiting.locator('[name="interest"]').select_option("gemuese")
    waiting.locator('[name="notes"]').fill("Warten")
    waiting.locator('[name="privacy_accept"]').check()
    emit_token(page, "waiting-list", "tkn-err-wait")
    waiting.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "warteliste-anmeldung").text_content() == (
        "Serverfehler allgemein"
    )


def test_network_failure_falls_back_to_each_adapters_own_copy(page):
    """Network rejection (fetch rejects): every one of the six adapters
    falls back to its OWN fallback copy — the long info@bioco.ch wording for
    contact, the informal wording for event, the shared generic wording for
    subscribe/visit-day/waiting-list/membership. No unified message, no
    success replacement."""
    page.evaluate(
        "() => { window.fetch = function () { return Promise.reject(new TypeError('x')); }; }"
    )

    # 1) contact: info@bioco.ch fallback (plus distinct server-error copy).
    contact = contact_section(page).locator("form[data-form=\"contact\"]")
    fill_contact(page)
    emit_token(page, "contact", "tkn-net-contact")
    contact.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "kontakt-formular").text_content() == (
        CONTACT_FALLBACK_ERROR
    )

    # 2) subscribe: shared generic wording (not the DOI success copy).
    subscribe = adapter_form(page, "subscribe")
    subscribe.locator('[name="email"]').fill("newsletter@example.com")
    subscribe.locator('[name="privacy_accept"]').check()
    emit_token(page, "subscribe", "tkn-net-sub")
    subscribe.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "newsletter-anmeldung").text_content() == (
        GENERIC_ERROR
    )

    # 3) visit-day: shared generic wording.
    visit = adapter_form(page, "visit-day")
    visit.locator('[name="name"]').fill("Vera Besuch")
    visit.locator('[name="email"]').fill("vera@example.com")
    visit.locator('[name="phone"]').fill("+41 56 000 00 00")
    visit.locator('[name="visit_date"]').fill("2026-10-01")
    visit.locator('[name="participants"]').fill("1")
    visit.locator('[name="privacy_accept"]').check()
    emit_token(page, "visit-day", "tkn-net-visit")
    visit.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "schnuppertag-anmeldung").text_content() == (
        GENERIC_ERROR
    )

    # 4) waiting-list: shared generic wording.
    waiting = adapter_form(page, "waiting-list")
    waiting.locator('[name="name"]').fill("Walter Wartet")
    waiting.locator('[name="email"]').fill("walter@example.com")
    waiting.locator('[name="phone"]').fill("+41 61 000 00 00")
    waiting.locator('[name="interest"]').select_option("eier")
    waiting.locator('[name="privacy_accept"]').check()
    emit_token(page, "waiting-list", "tkn-net-wait")
    waiting.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "warteliste-anmeldung").text_content() == (
        GENERIC_ERROR
    )

    # 5) event-signup: informal event wording.
    event = adapter_form(page, "event-signup")
    event.locator('[name="name"]').fill("Eva Event")
    event.locator('[name="email"]').fill("eva@example.com")
    emit_token(page, "event-signup", "tkn-net-event")
    event.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "event-anmeldung").text_content() == EVENT_ERROR

    # 6) membership: shared generic wording — a network failure carries no
    # fieldErrors, so nothing is flattened onto the fallback line; values
    # and controls survive for a retry.
    membership = fill_membership(page)
    emit_token(page, "membership", "tkn-net-mem")
    membership.locator('button[type="submit"]').click()
    assert wait_for_message_shown(page, "mitgliedschaft-anmeldung").text_content() == (
        GENERIC_ERROR
    )
    assert urlparse(page.url).path == "/bioco-forms/"  # no redirect on failure
    assert membership.locator('[name="lastName"]').input_value() == "Muster"
    assert membership.locator('button[type="submit"]').is_enabled()


def test_mount_widget_and_token_isolation(page):
    """Two different forms plus two instances of the same form: one widget
    per form, per-form tokens, expiry/reset/error touch only their own
    widget, and a repeated mount adds nothing."""
    section = page.locator("#kontakt-formular-zweit")
    second_contact = section.locator("form[data-form=\"contact\"]")

    # Seven mounted forms -> seven widgets (no duplicate mounts).
    page.wait_for_function(
        "Object.keys(window.__turnstileWidgets).filter(k => k.indexOf('::') === -1).length === 7"
    )

    # Distinct tokens per form; expire only the first contact's token.
    emit_token(page, "contact", "tkn-iso-c1")
    emit_token(page, "subscribe", "tkn-iso-s")
    emit_token(page, "event-signup", "tkn-iso-e")
    page.evaluate(
        "window.__turnstileEmit('token', 6, 'tkn-iso-c2')"
    )
    page.evaluate("window.__turnstileEmit('expired', 0)")

    # Expired first instance is blocked; the sibling instance still submits
    # with its own token to the same endpoint.
    first_contact = page.locator("#kontakt-formular form[data-form=\"contact\"]")
    fill_contact(page)
    first_contact.locator('button[type="submit"]').click()
    assert page.evaluate("window.__formTraffic") == []

    section.locator('[name="name"]').fill("Zora Zweite")
    section.locator('[name="email"]').fill("zora@example.com")
    section.locator('[name="subject"]').fill("Zweitfrage")
    section.locator('[name="message"]').fill("Zweite Nachricht")
    second_contact.locator('button[type="submit"]').click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular-zweit form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert len(traffic) == 1
    assert traffic[0]["body"]["captchaToken"] == "tkn-iso-c2"
    assert traffic[0]["body"]["name"] == "Zora Zweite"

    # A widget error on the (already expired) first instance resets only
    # that widget.
    page.evaluate("window.__turnstileEmit('error', 0)")
    assert page.evaluate("window.__turnstileResets(0)") == 1
    assert page.evaluate("window.__turnstileResets(6)") == 0

    # Repeated mount (what a double script execution would do) must not add
    # listeners, widgets or requests.
    page.evaluate(
        "() => { window.BiocoForms.mount({ scope: '.cms-contact-form',"
        " form: 'form[data-form=\"contact\"]', config: 'biocoContactFormConfig',"
        " success: 'X', error: 'Y', captcha: 'Z' }); }"
    )
    assert page.evaluate(
        "Object.keys(window.__turnstileWidgets).filter(k => k.indexOf('::') === -1).length"
    ) == 7


def test_data_config_override_routes_to_custom_endpoint(page):
    """data-config may point at a different localized object (WordPress
    F:164–175); the adapter must honour the override per form instance."""
    form = page.locator("#kontakt-formular-zweit form[data-form=\"contact\"]")
    assert form.get_attribute("data-config") == "biocoContactFormConfigCustom"
    form.locator('[name="name"]').fill("Ove Override")
    form.locator('[name="email"]').fill("ove@example.com")
    form.locator('[name="subject"]').fill("Override")
    form.locator('[name="message"]').fill("Anderer Endpoint")
    page.evaluate("window.__turnstileEmit('token', 6, 'tkn-ovr-1')")
    form.locator('button[type="submit"]').click()
    page.wait_for_function(
        "document.querySelector('#kontakt-formular-zweit form').hidden === true"
    )
    traffic = page.evaluate("window.__formTraffic")
    assert traffic[0]["url"] == "https://forms.test/wp-json/bioco/v1/contact-alt"
    assert traffic[0]["body"]["captchaToken"] == "tkn-ovr-1"


def test_accepted_response_stays_terminal_when_success_handling_throws(page):
    """An accepted server response must never fall into the error path: even
    if the success display throws (here: a form.hidden setter that throws),
    the signup is terminal — no retry, no error copy, controls stay down."""
    form = contact_section(page).locator("form[data-form=\"contact\"]")
    message = contact_section(page).locator(".form-message")
    fill_contact(page)
    emit_token(page, "contact", "tkn-term-1")
    page.evaluate(
        "() => { const form = document.querySelector('#kontakt-formular form');"
        " Object.defineProperty(form, 'hidden', { set() { throw new Error('UI kaputt'); }, get() { return false; }, configurable: true }); }"
    )
    form.locator('button[type="submit"]').click()
    page.wait_for_timeout(150)

    # Terminal: the one request stands; no error message, no re-enabled
    # button, no widget reset churn; a second dispatched submit sends nothing.
    assert len(page.evaluate("window.__formTraffic")) == 1
    assert message.evaluate("el => el.hidden") is True
    assert submit_button(form).is_disabled()
    assert page.evaluate("window.__turnstileResets(0)") == 0
    page.evaluate(
        "document.querySelector('#kontakt-formular form')"
        ".dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))"
    )
    assert len(page.evaluate("window.__formTraffic")) == 1


# ---------------------------------------------------------------------------
# Membership: serialization contract, redirect policy, fieldErrors.
# ---------------------------------------------------------------------------

MEMBERSHIP_PERSONAL = {
    "firstName": "Milena",
    "lastName": "Muster",
    "address": "Gartenweg 12",
    "zip": "08004",  # leading zero must survive as a string
    "city": "Zürich",
    "phone": "+41 62 000 00 00",
    "email": "milena@example.com",
}


def fill_membership(page, anchor="mitgliedschaft-anmeldung"):
    form = adapter_form(page, "membership", anchor)
    for name, value in MEMBERSHIP_PERSONAL.items():
        form.locator(f'[name="{name}"]').fill(value)
    form.locator('input[name="commitmentAccepted[]"]').first.check()  # second stays unchecked
    form.locator('[name="privacyAccept"]').check()
    return form


def submit_membership(page, token="tkn-mem-1", wait_url=True):
    form = adapter_form(page, "membership")
    emit_token(page, "membership", token)
    form.locator('button[type="submit"]').click()
    # The success redirect replaces the document and the init scripts
    # re-run there, re-initialising window.__formTraffic to empty — reading
    # it after the click is a race against that navigation. The fetch stub
    # archives every request into same-origin sessionStorage, which survives
    # the navigation, so the observation must read the archive in whichever
    # document the wait lands. Never suppress the redirect, never weaken
    # this to a payload guess.
    page.wait_for_function(
        "JSON.parse(sessionStorage.getItem('__formArchive') || '[]')"
        ".some(e => e.url.indexOf('membership') !== -1)"
    )
    if wait_url:
        page.wait_for_url(f"{TEST_ORIGIN}{THANK_YOU_PATH}")
    return form


def membership_traffic(page):
    # sessionStorage archive: the success redirect replaces the document,
    # and the per-document window.__formTraffic would be re-initialised.
    return json.loads(page.evaluate("sessionStorage.getItem('__formArchive') || '[]'"))


def test_membership_redirect_payload_with_optional_depot(page):
    """Abo signup with an EMPTY depot is accepted by the backend and still
    redirects exactly once. The response is explicitly the intranet-degraded
    {success:true,forwarded:false}: PHP sends the administrator mail first
    and treats intranet forwarding as best effort, so this is a COMPLETED
    signup and must redirect exactly once. The payload proves the
    serialization contract: camelCase fields, boolean array including
    unchecked entries, empty value arrays preserved, ZIP string, hidden
    counts as strings, scalar privacy boolean."""
    page.evaluate(
        "() => { window.__formResponder = function () { return { status: 200, body: "
        "{ success: true, forwarded: false } }; }; }"
    )
    form = fill_membership(page)
    assert form.locator('[name="depot"]').input_value() == ""  # depot optional
    submit_membership(page, token="tkn-mem-1")

    entries = membership_traffic(page)
    assert len(entries) == 1
    assert entries[0]["url"] == "https://forms.test/wp-json/bioco/v1/membership"
    assert entries[0]["body"] == {
        "membershipType": "abo",
        "aboType": "standard",
        "additionalShares": "0",
        "sharesOnly": "0",
        "commitmentAccepted": [True, False],
        "firstName": "Milena",
        "lastName": "Muster",
        "address": "Gartenweg 12",
        "zip": "08004",
        "city": "Zürich",
        "phone": "+41 62 000 00 00",
        "email": "milena@example.com",
        "depot": "",
        "paymentType": "yearly",
        "preferredDays": [],
        "preferredTimes": [],
        "activityAreas": [],
        "zusatzabos": [],
        "otherActivity": "",
        "weitereProdukte": "",
        "privacyAccept": True,
        "captchaToken": "tkn-mem-1",
    }
    # The archived body records exactly what was sent for the response the
    # server gave: success with forwarded:false, yet a real redirect.
    assert entries[0]["response"]["body"] == {"success": True, "forwarded": False}
    # Redirect happened despite forwarded:false, and only once.
    assert urlparse(page.url).path == THANK_YOU_PATH
    assert len(membership_traffic(page)) == 1


def test_membership_shares_only_with_empty_depot_redirects(page):
    """shares-only (pricing calculator: kein Abo) with no depot selection is
    a valid signup — introducing native validity must not make the optional
    depot mandatory. Ordinary checkbox arrays are submitted with their
    SELECTED values in DOM order (the abo test above proves the empty-array
    contract)."""
    page.goto(f"{TEST_ORIGIN}/bioco-forms/?abo=kein&shares=3", wait_until="load")
    page.wait_for_timeout(100)
    form = fill_membership(page)
    form.locator('input[name="preferredDays[]"][value="Dienstag"]').check()
    form.locator('input[name="preferredDays[]"][value="Freitag"]').check()
    form.locator('input[name="activityAreas[]"][value="Ernte"]').check()
    form.locator('input[name="activityAreas[]"][value="Packerei"]').check()
    form.locator('input[name="zusatzabos[]"][value="Eier-Abo"]').check()
    submit_membership(page, token="tkn-mem-2")

    entries = membership_traffic(page)
    assert entries[0]["body"]["membershipType"] == "shares-only"
    assert entries[0]["body"]["aboType"] == "none"
    assert entries[0]["body"]["additionalShares"] == "0"
    assert entries[0]["body"]["sharesOnly"] == "3"
    assert entries[0]["body"]["depot"] == ""
    # Selected ordinary checkbox arrays keep their VALUES in DOM order —
    # the empty-array contract is proven in the abo test above; here the
    # selections ride along verbatim (participation + Zusatzabos).
    assert entries[0]["body"]["preferredDays"] == ["Dienstag", "Freitag"]
    assert entries[0]["body"]["activityAreas"] == ["Ernte", "Packerei"]
    assert entries[0]["body"]["zusatzabos"] == ["Eier-Abo"]
    assert urlparse(page.url).path == THANK_YOU_PATH


def test_membership_field_errors_flatten_and_values_survive(page):
    """Only the membership adapter flattens fieldErrors into the error line
    (own property values, space separated, after the error text)."""
    page.goto(f"{TEST_ORIGIN}/bioco-forms/?abo=standard-2-3-personen&additional=7", wait_until="load")
    page.wait_for_timeout(100)
    page.evaluate(
        "() => { window.__formResponder = function () { return { status: 400, body: "
        "{ success: false, error: 'Bitte korrigiere deine Angaben.', fieldErrors: "
        "{ firstName: 'Dieses Feld ist erforderlich.', privacyAccept: 'Bitte akzeptiere die Datenschutzerklärung.' } } }; }; }"
    )
    form = fill_membership(page)
    submit_membership(page, token="tkn-mem-3", wait_url=False)
    page.wait_for_function(
        "document.querySelector('#mitgliedschaft-anmeldung .form-message').hidden === false"
    )
    message = page.locator("#mitgliedschaft-anmeldung .form-message")
    assert message.text_content() == (
        "Bitte korrigiere deine Angaben. Dieses Feld ist erforderlich. "
        "Bitte akzeptiere die Datenschutzerklärung."
    )
    assert message.get_attribute("class") == "form-message bento-card form-error"
    # No navigation happened; values and controls are ready for a retry.
    assert urlparse(page.url).path == "/bioco-forms/"
    assert form.locator('[name="lastName"]').input_value() == "Muster"
    assert form.locator('button[type="submit"]').is_enabled()


def test_membership_calculator_selection_matrix(page):
    """Query-derived hidden selection rides along as strings; clamping and
    unknown selections follow the pricing-calculator contract."""
    cases = [
        ("?abo=halb-1-person&shares=9&additional=4",
         ("abo", "halb", "4", "0")),
        ("?abo=doppel-4-6-personen&shares=6&additional=2",
         ("abo", "doppel", "2", "0")),
        ("?abo=kein&shares=999",  # clamp to 100
         ("shares-only", "none", "0", "100")),
        ("?abo=kein&shares=2.5",  # decimal -> 0 -> shares-only minimum 1
         ("shares-only", "none", "0", "1")),
        ("?abo=gibberish&shares=5",  # unknown selection: rendered defaults
         ("abo", "standard", "0", "0")),
    ]
    for query, expected in cases:
        page.goto(f"{TEST_ORIGIN}/bioco-forms/{query}", wait_until="load")
        page.wait_for_timeout(100)
        page.evaluate("window.__formResponder = null;")
        fill_membership(page)
        submit_membership(page, token="tkn-mem-matrix")
        entries = membership_traffic(page)
        body = entries[-1]["body"]
        assert (body["membershipType"], body["aboType"],
                body["additionalShares"], body["sharesOnly"]) == expected, query


# ---------------------------------------------------------------------------
# Real local WordPress runtime proof (read-only). The runtime at
# 127.0.0.1:8770 must carry THIS worktree's code (swap:
# /tmp/bioco-divi-editor-runtime/bin/swap-worktree.sh). All browser traffic
# is intercepted: the runtime itself only ever receives plain read-only
# GETs; form POSTs never leave the fetch stub; a hung Turnstile script
# request stays local. This is the emitted/dependency-order proof the
# hand-built fixture order cannot provide.
# ---------------------------------------------------------------------------

WP_RUNTIME_BASE = os.environ.get("BIOCO_WP_RUNTIME_URL", "http://127.0.0.1:8770")

WP_ENDPOINT_SLUGS = {
    "contact": "contact-form",
    "subscribe": "subscribe-form",
    "visit-day": "visit-day-form",
    "waiting-list": "waiting-list-form",
    "event-signup": "event-signup-form",
    "membership": "membership-form",
}

# Seeded pages of the local runtime that render each form block.
WP_FORM_PAGES = {
    "contact": "/kontakt/",
    "subscribe": "/newsletter/",
    "waiting-list": "/warteliste/",
    "event-signup": "/event-anmeldung/",
    "membership": "/anmeldung/",
}


def _wp_get(path):
    """Plain read-only GET against the local runtime (no POST, no mail)."""
    with urllib.request.urlopen(WP_RUNTIME_BASE + path, timeout=10) as response:
        return response.read().decode("utf-8")


def test_wordpress_runtime_emits_runtime_before_adapters_without_blocking_turnstile():
    """Emitted-asset contract of the REAL WordPress runtime: for every
    seeded form page the shared runtime script prints before the block's
    view script, the localized config arrives as the view script's inline
    extra, and PHP never emits the parser-blocking Turnstile tag (#181
    root review: a blocking api.js tag delayed DOMContentLoaded so unmounted
    adapters leaked contact fields via native GET)."""
    for endpoint, page_path in WP_FORM_PAGES.items():
        html = _wp_get(page_path)
        slug = WP_ENDPOINT_SLUGS[endpoint]
        handle = f"bioco-{slug}-view-script"

        assert f'id="bioco-cf-turnstile-js"' not in html, page_path
        runtime_pos = html.find("bioco-forms-lifecycle")
        assert runtime_pos != -1, page_path
        view_pos = html.find(handle)
        assert view_pos != -1, page_path
        assert runtime_pos < view_pos, page_path
        # wp_localize_script must have localized onto the registered handle.
        assert f'id="{handle}-js-extra"' in html, page_path


def _open_real_wp_page(browser_ctx, html_body, defer_turnstile=False):
    """Fresh browser page for a REAL local-WP page: same-origin requests
    are fulfilled from cached read-only GETs against the runtime; the
    Turnstile host and every other origin are abortable by the caller."""
    context = browser_ctx.new_context()
    init_scripts = []
    if defer_turnstile:
        init_scripts.append("window.__deferTurnstile = true;")
    init_scripts.append(STUBS_JS)
    for script in init_scripts:
        context.add_init_script(script)

    cache = {}

    def passthrough(url):
        if url not in cache:
            with urllib.request.urlopen(url, timeout=10) as response:
                cache[url] = (
                    response.read(),
                    response.headers.get("Content-Type", "application/octet-stream"),
                )
        return cache[url]

    held_routes = []

    def handle_route(route):
        url = urlparse(route.request.url)
        if "challenges.cloudflare.com" in url.netloc:
            held_routes.append(route)  # hung by the caller; settled in teardown
            return
        if url.netloc != urlparse(WP_RUNTIME_BASE).netloc:
            route.abort()  # external assets (fonts etc.) stay local-off
            return
        if route.request.resource_type == "document":
            if url.query:
                route.abort()  # a document GET with a query is a native-GET leak
            else:
                route.fulfill(
                    status=200, content_type="text/html; charset=utf-8", body=html_body
                )
            return
        try:
            body, content_type = passthrough(route.request.url)
        except Exception:
            route.abort()  # runtime hiccup behaves like a network error
            return
        route.fulfill(status=200, content_type=content_type, body=body)

    context.route("**/*", handle_route)
    page = context.new_page()

    def settle_held_routes():
        for route in held_routes:
            try:
                route.abort()
            except Exception:
                pass  # already settled or context gone
        del held_routes[:]

    return page, context, settle_held_routes


def test_wordpress_real_page_mounts_runtime_and_adapters(browser_ctx):
    """In the REAL local WordPress runtime the emitted order actually
    executes: the shared runtime is present before every adapter mounts, so
    the contact form mounts through window.BiocoForms (pre-#181 the
    blocking Turnstile tag left the runtime loaded but unmounted)."""
    try:
        html = _wp_get("/kontakt/")
    except Exception as error:
        pytest.fail(
            "the local WordPress runtime is not reachable at "
            f"{WP_RUNTIME_BASE} ({error}); start it and swap this worktree: "
            "/tmp/bioco-divi-editor-runtime/bin/swap-worktree.sh <worktree>"
        )
    page, context, settle = _open_real_wp_page(browser_ctx, html)
    try:
        page.goto(f"{WP_RUNTIME_BASE}/kontakt/", wait_until="load")
        page.wait_for_function(
            "typeof window.BiocoForms === 'object' && !!window.BiocoForms.mount"
        )
        page.wait_for_function(
            "(() => { const f = document.querySelector("
            "'.cms-contact-form form[data-form=\"contact\"]');"
            " return !!f && f.biocoFormsMounted === true; })()"
        )
        # The localized config arrived from the REAL mu-plugin (real REST
        # URL of the runtime, empty site key when unconfigured).
        config = page.evaluate("window.biocoContactFormConfig || null")
        assert config is not None
        assert config["restUrl"] == f"{WP_RUNTIME_BASE}/wp-json/bioco/v1/contact"
    finally:
        settle()
        context.close()


def test_wordpress_hung_turnstile_blocks_native_get_and_api_submit(browser_ctx):
    """Root RED scenario on the REAL WordPress page: a hung Turnstile script
    request must never produce a native GET or an API submit — the bounded
    timeout fails closed to the explicit German manual-reload copy, keeps
    the entered values and creates no further script elements. The contact
    page's localized site key is rewritten to a test key so the captcha gate
    is actually armed (the local runtime ships an empty key); the emitted
    HTML itself carries no Turnstile tag."""
    try:
        html = _wp_get("/kontakt/")
    except Exception as error:
        pytest.fail(
            "the local WordPress runtime is not reachable at "
            f"{WP_RUNTIME_BASE} ({error}); start it and swap this worktree: "
            "/tmp/bioco-divi-editor-runtime/bin/swap-worktree.sh <worktree>"
        )
    assert 'id="bioco-cf-turnstile-js"' not in html  # PHP does not pre-enqueue
    armed = html.replace('"turnstileSiteKey":""', '"turnstileSiteKey":"test-site-key"')
    assert 'biocoContactFormConfig = {"restUrl":"' in armed  # real localized shape
    assert '"turnstileSiteKey":"test-site-key"' in armed

    page, context, settle = _open_real_wp_page(
        browser_ctx, armed, defer_turnstile=True
    )
    try:
        # The held Turnstile request would block the window load event
        # forever; DOMContentLoaded still fires (async scripts do not block
        # parsing), so observe from there.
        page.goto(f"{WP_RUNTIME_BASE}/kontakt/", wait_until="domcontentloaded")
        page.wait_for_timeout(300)  # adapter mount + loader appended its script

        form = page.locator('.cms-contact-form form[data-form="contact"]')
        page.wait_for_function(
            "(() => { const f = document.querySelector("
            "'.cms-contact-form form[data-form=\"contact\"]');"
            " return !!f && f.biocoFormsMounted === true; })()"
        )
        # The LOADER appended the element (the served HTML did not contain
        # it) and its request is deliberately held pending by the router.
        assert page.evaluate(
            "document.getElementById('bioco-cf-turnstile-js') !== null"
        )

        for name, value in CONTACT_VALUES.items():
            form.locator(f'[name="{name}"]').fill(value)

        # Bounded: the real default bound (4s) settles the hung transport,
        # removes the dead element and latches the failure.
        page.wait_for_function(
            "document.getElementById('bioco-cf-turnstile-js') === null"
        )

        # From then on every submit shows the explicit German reload copy:
        # no API submit, no native GET, no automatic reload, values intact,
        # and no further script elements (bounded, not endless retries).
        for _ in range(2):
            form.locator('button[type="submit"]').click()
            page.wait_for_timeout(100)
        message = page.locator(".cms-contact-form .form-message")
        assert message.text_content() == TURNSTILE_LOAD_FAILED_MESSAGE
        assert page.evaluate(
            "document.getElementById('bioco-cf-turnstile-js') === null"
        )
        assert urlparse(page.url).path == "/kontakt/"
        assert urlparse(page.url).query == ""  # no native GET, no reload
        assert page.evaluate("window.__formTraffic") == []
        assert form.locator('button[type="submit"]').is_enabled()
        assert form.evaluate("el => !el.hidden")
        assert form.locator('[name="name"]').input_value() == CONTACT_VALUES["name"]
    finally:
        settle()
        context.close()


def test_empty_captcha_copy_clears_a_previous_message(page):
    form = fill_contact(page)
    message = contact_section(page).locator('.form-message')
    submit_button(form).click()
    assert message.text_content() == CAPTCHA_ERROR
    page.evaluate("window.biocoContactFormConfig.captchaError = ''")
    submit_button(form).click()
    assert message.text_content() == ''
    assert message.is_hidden()
    assert page.evaluate('window.__formTraffic') == []


def test_editor_copy_controls_captcha_error_and_success(page):
    form = fill_contact(page)
    message = contact_section(page).locator('.form-message')
    page.evaluate("""() => Object.assign(window.biocoContactFormConfig, {
        captchaError: 'Redaktion: Sicherheitsprüfung nötig.',
        fallbackError: 'Redaktion: Bitte erneut versuchen.',
        successMessage: 'Redaktion: Deine Nachricht ist angekommen.'
    })""")
    submit_button(form).click()
    assert message.text_content() == 'Redaktion: Sicherheitsprüfung nötig.'
    page.evaluate("""() => {
        window.fetch = () => Promise.resolve(new Response(JSON.stringify({success:false}), {status:500}));
        window.__turnstileEmit('token', 0, 'editor-test-1');
    }""")
    submit_button(form).click()
    page.wait_for_function("document.querySelector('#kontakt-formular button[type=submit]').disabled === false")
    assert message.text_content() == 'Redaktion: Bitte erneut versuchen.'
    page.evaluate("""() => {
        window.fetch = () => Promise.resolve(new Response(JSON.stringify({success:true}), {status:200}));
        window.__turnstileEmit('token', 0, 'editor-test-2');
    }""")
    submit_button(form).click()
    page.wait_for_function("document.querySelector('#kontakt-formular form').hidden")
    assert message.text_content() == 'Redaktion: Deine Nachricht ist angekommen.'
