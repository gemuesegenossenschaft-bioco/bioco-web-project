"""Issue #158: Formular-Erfolgs- und Bestätigungstexte.

Keine Erfolg-, Fehler- oder Captcha-Meldung ist mehr in JS oder PHP hart
kodiert: alle kommen aus editierbaren ACF-Feldern, die Standardwerte liegen
im redaktionellen Inhaltsseed (defaults.json), durchgehend in der
Du-Anrede wie auf der Live-Site. Der Editor kann jeden Text ohne Deploy
aendern.
"""

import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
FORMS_PHP = ROOT / "wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php"

FORMS = ["contact-form", "subscribe-form", "visit-day-form", "waiting-list-form", "event-signup-form", "membership-form"]


def test_defaults_carry_du_form_strings_per_block():
    wp = json.loads((ROOT / "wordpress/content-seed/block-content/defaults.json").read_text())
    cms = json.loads((ROOT / "cms/content-seed/block-content/defaults.json").read_text())
    assert wp["blocks"] == cms["blocks"]

    blocks = wp["blocks"]
    assert blocks["contact-form"]["success_message"] == (
        "Vielen Dank für deine Nachricht! Wir melden uns so schnell wie möglich bei dir."
    )
    assert blocks["contact-form"]["fallback_error"].startswith("Deine Nachricht konnte nicht gesendet werden")
    assert blocks["subscribe-form"]["success_message"] == (
        "Vielen Dank! Bitte bestätige deine Anmeldung über den Link in der E-Mail, die wir dir gesendet haben."
    )
    for form in ("visit-day-form", "waiting-list-form"):
        assert "Vielen Dank für deine Anmeldung!" in blocks[form]["success_message"]
    assert blocks["event-signup-form"]["success_message"].startswith("Anmeldung erfolgreich!")
    assert "kontaktiere uns direkt" in blocks["event-signup-form"]["fallback_error"]
    assert blocks["membership-form"].get("success_message") is None

    for form in FORMS:
        assert blocks[form]["captcha_error"] == "Bitte bestätige, dass du kein Roboter bist."


def test_acf_groups_have_the_editable_string_fields_without_hard_defaults():
    for form in FORMS:
        group = json.loads((CORE / f"acf-json/group_bioco_block_{form.replace('-', '_')}.json").read_text())
        names = {f["name"] for f in group["fields"]}
        expected = {"fallback_error", "captcha_error"}
        if form != "membership-form":
            expected |= {"success_message"}
        assert expected <= names, (form, expected - names)
        for f in group["fields"]:
            if f["name"] in expected:
                assert not f.get("default_value"), (form, f["name"], f["default_value"])


def test_no_sie_form_or_success_string_is_hardcoded_anywhere():
    for form in FORMS:
        js = (CORE / f"blocks/{form}/view.js").read_text()
        for forbidden in (
            "Vielen Dank für Ihre",
            "Ihre Nachricht",
            "Bitte bestätigen Sie",
            "versuchen Sie",
            "Sie haben",
            "Ihre Anmeldung",
            "Vielen Dank für deine",
            "Anmeldung erfolgreich!",
        ):
            assert f"var {'SUCCESS_MESSAGE'}" not in js or form == "x"
            assert forbidden not in js, (form, forbidden)
        assert "config.successMessage" in js, form
        assert "config.fallbackError" in js, form
        assert "config.captchaError" in js, form

    php = FORMS_PHP.read_text()
    for forbidden in (
        "bestätigen Sie", "Bitte geben Sie", "akzeptieren Sie",
        "wählen Sie", "füllen Sie", "versuchen Sie es",
        "Vielen Dank",
    ):
        assert forbidden not in php, forbidden


def test_render_templates_pass_the_strings_to_the_view_script():
    expected_success = {
        "contact-form", "subscribe-form", "visit-day-form", "waiting-list-form", "event-signup-form",
    }
    for form in FORMS:
        render = (CORE / f"blocks/{form}/render.php").read_text()
        assert "bioco_field('fallback_error')" in render, form
        assert "bioco_field('captcha_error')" in render, form
        if form in expected_success:
            assert "bioco_field('success_message')" in render, form
        else:
            assert "success_message" not in render, form

    helper = FORMS_PHP.read_text()
    assert re.search(r"function bioco_forms_localize_block\([^)]*array \$strings = \[\]", helper)
    assert "] + $strings" in helper


def test_localized_config_reaches_the_form_via_marker_expansion():
    """End-to-end through the dynamic marker path: the contact form's
    localized config must carry the du success text from the seed."""
    php = r"""
    define('ABSPATH', __DIR__);
    function wp_enqueue_script($h, ...$a) {} function wp_enqueue_style($h, ...$a) {}
    function wp_localize_script($h, $o, $v) { $GLOBALS['LOCALIZED'][] = [$o, $v]; }
    function bioco_forms_view_script_handle($b) { return 'shared::' . $b; }
    function bioco_forms_localize_block($b, $o, $e, $s = []) {
        $config = ['configured' => false];
        $GLOBALS['LOCALIZED'][] = [$o, [
            'restUrl' => 'https://staging.test/wp-json/bioco/v1/' . $e,
            'turnstileSiteKey' => '',
        ] + $s];
    }
    function bioco_text_has_heading_html($h) { return false; }
    function get_the_ID() { return 77; }
    function is_singular($t = '') { return false; }
    function bioco_kses_rich_text($h) { return (string) $h; }
    function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
    function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
    function esc_url($v) { return (string)$v; }
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/seeds.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';

    $seed = json_decode(file_get_contents('wordpress/content-seed/kontakt.json'), true);
    $seed['_bioco_seed_dir'] = 'wordpress/content-seed';
    $GLOBALS['LOCALIZED'] = [];
    foreach (bioco_import_build_page_plan($seed) as $item) {
        if (($item['block'] ?? '') !== 'contact-form') continue;
        $serialized = serialize_marker($item);
        bioco_dynamic_expand_markers($serialized);
    }
    function serialize_marker($item) {
        $props = base64_encode(json_encode(array_diff_key($item['values'], ['_section_heading' => 1])));
        return '<div class="bioco-dynamic" data-bioco-component="contact_form" data-bioco-props="' . $props . '"></div>';
    }
    echo json_encode($GLOBALS['LOCALIZED']);
    """
    result = subprocess.run(["php", "-r", php], cwd=ROOT, text=True, capture_output=True, check=True).stdout
    configs = json.loads(result)
    contact = next(cfg for obj, cfg in configs if obj == "biocoContactFormConfig")
    assert contact["successMessage"] == (
        "Vielen Dank für deine Nachricht! Wir melden uns so schnell wie möglich bei dir."
    )
    assert contact["fallbackError"].startswith("Deine Nachricht konnte nicht gesendet werden")
    assert contact["captchaError"] == "Bitte bestätige, dass du kein Roboter bist."