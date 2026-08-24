import base64
import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
DYNAMIC_SECTIONS = "wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php"

COMPONENTS = {
    "contact_form": "bioco/contact-form",
    "membership_form": "bioco/membership-form",
    "subscribe_form": "bioco/subscribe-form",
    "visit_day_form": "bioco/visit-day-form",
    "waiting_list_form": "bioco/waiting-list-form",
    "event_signup_form": "bioco/event-signup-form",
    "doi_confirm": "bioco/doi-confirm",
    "gallery": "bioco/gallery",
    "pricing_calculator": "bioco/pricing-calculator",
    "events_feed": "bioco/events-feed",
    "schnuppertage": "bioco/schnuppertage",
    "group_cards": "bioco/group-cards",
    "saisonkalender": "bioco/saisonkalender",
    "depot_map": "bioco/depot-map",
    "geisshof_map": "bioco/geisshof-map",
}


def _run_php(code: str, *, check: bool = True) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["php", "-r", code],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=check,
    )


def _json_php(code: str):
    return json.loads(_run_php(code).stdout)


def _runtime_preamble() -> str:
    return (
        "define('ABSPATH', __DIR__);\n"
        "$GLOBALS['BIOCO_FILTERS'] = [];\n"
        "function add_filter($hook, $callback, $priority = 10, $args = 1) {\n"
        "    $GLOBALS['BIOCO_FILTERS'][] = [$hook, $callback, $priority, $args];\n"
        "}\n"
        f"require '{DYNAMIC_SECTIONS}';\n"
    )


def _render_preamble() -> str:
    return (
        "define('ABSPATH', __DIR__);\n"
        "$GLOBALS['BIOCO_FILTERS'] = [];\n"
        "$GLOBALS['BIOCO_ENQUEUED'] = [];\n"
        "$GLOBALS['BIOCO_ENQUEUED_STYLES'] = [];\n"
        "$GLOBALS['BIOCO_LOCALIZED'] = [];\n"
        "$GLOBALS['BIOCO_ACF_CALLS'] = [];\n"
        "$GLOBALS['BIOCO_NEST_COMPONENT'] = null;\n"
        "$GLOBALS['BIOCO_THROW_LOCALIZE'] = false;\n"
        "function add_filter($hook, $callback, $priority = 10, $args = 1) {\n"
        "    $GLOBALS['BIOCO_FILTERS'][] = [$hook, $callback, $priority, $args];\n"
        "}\n"
        "function wp_enqueue_script($handle, ...$args) { $GLOBALS['BIOCO_ENQUEUED'][] = $handle; }\n"
        "function wp_enqueue_style($handle, ...$args) { $GLOBALS['BIOCO_ENQUEUED_STYLES'][] = $handle; }\n"
        "function wp_localize_script($handle, $object, $values) {\n"
        "    $GLOBALS['BIOCO_LOCALIZED'][] = [$handle, $object, $values];\n"
        "}\n"
        "function bioco_forms_view_script_handle($block_name) { return 'shared::' . $block_name; }\n"
        "function bioco_forms_localize_block($block_name, $object, $endpoint) {\n"
        "    if ($GLOBALS['BIOCO_THROW_LOCALIZE']) throw new RuntimeException('template failed');\n"
        "    wp_localize_script(bioco_forms_view_script_handle($block_name), $object, ['endpoint' => $endpoint]);\n"
        "    if ($GLOBALS['BIOCO_NEST_COMPONENT']) {\n"
        "        $nested = $GLOBALS['BIOCO_NEST_COMPONENT'];\n"
        "        $GLOBALS['BIOCO_NEST_COMPONENT'] = null;\n"
        "        echo bioco_render_dynamic_component($nested, ['empty_message' => 'NESTED']);\n"
        "        echo '<i data-outer-title>' . bioco_field('title', 'missing') . '</i>';\n"
        "    }\n"
        "}\n"
        "function get_field($name, ...$args) {\n"
        "    $GLOBALS['BIOCO_ACF_CALLS'][] = [$name, $args];\n"
        "    return 'acf:' . $name;\n"
        "}\n"
        "function get_the_ID() { return 77; }\n"
        "function get_the_title() { return 'Testevent'; }\n"
        "function is_singular($type) { return false; }\n"
        "function __($value, $domain = null) { return $value; }\n"
        "function sanitize_text_field($value) { return trim((string)$value); }\n"
        "function wp_unslash($value) { return $value; }\n"
        "function get_permalink($post_id = 0) { return 'https://example.test/current/'; }\n"
        "function home_url($path = '/') { return 'https://example.test' . $path; }\n"
        "function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }\n"
        "function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }\n"
        "function esc_url($value) { return (string)$value; }\n"
        "function sanitize_title($value) { return strtolower(str_replace(' ', '-', (string)$value)); }\n"
        "function number_format_i18n($value) { return number_format((float)$value, 0, '.', \"'\"); }\n"
        "function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }\n"
        "function bioco_text_has_heading_html($html) { return false; }\n"
        "function bioco_kses_rich_text($html) { return (string)$html; }\n"
        "function bioco_navigation_url($url) { return (string)$url; }\n"
        "function bioco_render_events_list($query, $empty) { if ($empty) echo '<p>' . $empty . '</p>'; }\n"
        "function bioco_render_map_block($locations, $lat, $lng, $zoom, $heading, $route, $empty) {\n"
        "    echo '<div class=map-stub>' . $empty . '</div>';\n"
        "}\n"
        "class WP_Query {\n"
        "    public function __construct($args = []) {}\n"
        "    public function have_posts() { return false; }\n"
        "    public function the_post() {}\n"
        "}\n"
        "function bioco_query_events($status, $limit, $type = null) { return new WP_Query(); }\n"
        "function wp_reset_postdata() {}\n"
        f"require '{DYNAMIC_SECTIONS}';\n"
    )


def test_component_registry_and_marker_format_are_canonical():
    values = {"title": 'Äpfel "&" Birnen', "nested": {"enabled": True}}
    values_json = json.dumps(values, ensure_ascii=True, separators=(",", ":"))
    values_b64 = base64.b64encode(values_json.encode()).decode()
    payload = _json_php(
        _runtime_preamble()
        + f"$values = json_decode(base64_decode('{values_b64}'), true);\n"
        + "echo json_encode([\n"
        + "    'components' => bioco_dynamic_components(),\n"
        + "    'marker' => bioco_dynamic_marker_html('contact_form', $values),\n"
        + "]);"
    )

    assert payload["components"] == COMPONENTS
    assert payload["marker"] == (
        '<div class="bioco-dynamic" data-bioco-component="contact_form" '
        f'data-bioco-props="{values_b64}"></div>'
    )


def test_marker_builder_rejects_unknown_component():
    result = _json_php(
        _runtime_preamble()
        + "try {\n"
        + "    bioco_dynamic_marker_html('not_real', []);\n"
        + "    echo json_encode(['threw' => false]);\n"
        + "} catch (InvalidArgumentException $e) {\n"
        + "    echo json_encode(['threw' => true, 'message' => $e->getMessage()]);\n"
        + "}"
    )

    assert result["threw"] is True
    assert "not_real" in result["message"]


def test_every_component_marker_round_trips_through_real_render_template():
    components_b64 = base64.b64encode(json.dumps(list(COMPONENTS)).encode()).decode()
    payload = _json_php(
        _render_preamble()
        + f"$components = json_decode(base64_decode('{components_b64}'), true);\n"
        + "$result = [];\n"
        + "foreach ($components as $component) {\n"
        + "    $marker = bioco_dynamic_marker_html($component, ['empty_message' => 'EMPTY-' . $component]);\n"
        + "    $expanded = bioco_dynamic_expand_markers('before' . $marker . 'after');\n"
        + "    $result[$component] = [\n"
        + "        'consumed' => strpos($expanded, 'bioco-dynamic') === false,\n"
        + "        'wrapped' => str_starts_with($expanded, 'before') && str_ends_with($expanded, 'after'),\n"
        + "        'section' => strpos($expanded, 'cms-' . str_replace('_', '-', $component)) !== false,\n"
        + "    ];\n"
        + "}\n"
        + "echo json_encode($result);"
    )

    assert set(payload) == set(COMPONENTS)
    assert all(result == {"consumed": True, "wrapped": True, "section": True} for result in payload.values())


def test_expansion_matches_real_contact_template_and_is_idempotent():
    payload = _json_php(
        _render_preamble()
        + "$values = ['title' => 'Kontakt aus Marker', 'phone_label' => 'Telefon privat'];\n"
        + "$marker = bioco_dynamic_marker_html('contact_form', $values);\n"
        + "$once = bioco_dynamic_expand_markers($marker);\n"
        + "$twice = bioco_dynamic_expand_markers($once);\n"
        + "echo json_encode(['once' => $once, 'same' => $once === $twice]);"
    )

    assert 'class="cms-section cms-contact-form"' in payload["once"]
    assert "Kontakt aus Marker" in payload["once"]
    assert "Telefon privat" in payload["once"]
    assert "bioco-dynamic" not in payload["once"]
    assert payload["same"] is True


def test_expansion_leaves_plain_html_and_unknown_marker_untouched():
    unknown = '<div class="bioco-dynamic" data-bioco-component="not_real" data-bioco-props="W10="></div>'
    payload = _json_php(
        _render_preamble()
        + f"$unknown = {json.dumps(unknown)};\n"
        + "$plain = '<p>Already rendered</p>';\n"
        + "echo json_encode([\n"
        + "    'plain' => bioco_dynamic_expand_markers($plain),\n"
        + "    'unknown' => bioco_dynamic_expand_markers($unknown),\n"
        + "]);"
    )

    assert payload == {"plain": "<p>Already rendered</p>", "unknown": unknown}


def test_view_script_enqueue_and_form_localization_share_existing_handle_helper():
    payload = _json_php(
        _render_preamble()
        + "bioco_render_dynamic_component('contact_form', ['title' => 'Contact']);\n"
        + "$contact_enqueued = $GLOBALS['BIOCO_ENQUEUED'];\n"
        + "$contact_localized = $GLOBALS['BIOCO_LOCALIZED'];\n"
        + "$GLOBALS['BIOCO_ENQUEUED'] = [];\n"
        + "bioco_render_dynamic_component('group_cards', ['empty_message' => 'None']);\n"
        + "echo json_encode([\n"
        + "    'contact_enqueued' => $contact_enqueued,\n"
        + "    'contact_localized' => $contact_localized,\n"
        + "    'group_enqueued' => $GLOBALS['BIOCO_ENQUEUED'],\n"
        + "]);"
    )

    expected_handle = "shared::bioco/contact-form"
    assert payload["contact_enqueued"] == [expected_handle]
    assert payload["contact_localized"] == [
        [expected_handle, "biocoContactFormConfig", {"endpoint": "contact"}]
    ]
    assert payload["group_enqueued"] == []


def test_dynamic_map_markers_enqueue_registered_leaflet_style():
    payload = _json_php(
        _render_preamble()
        + "$depot = bioco_dynamic_marker_html('depot_map', ['empty_message' => 'DEPOT']);\n"
        + "bioco_dynamic_expand_markers($depot);\n"
        + "$depot_styles = $GLOBALS['BIOCO_ENQUEUED_STYLES'];\n"
        + "$GLOBALS['BIOCO_ENQUEUED_STYLES'] = [];\n"
        + "$geisshof = bioco_dynamic_marker_html('geisshof_map', ['empty_message' => 'GEISSHOF']);\n"
        + "bioco_dynamic_expand_markers($geisshof);\n"
        + "$geisshof_styles = $GLOBALS['BIOCO_ENQUEUED_STYLES'];\n"
        + "$GLOBALS['BIOCO_ENQUEUED_STYLES'] = [];\n"
        + "bioco_render_dynamic_component('group_cards', ['empty_message' => 'NONE']);\n"
        + "echo json_encode([\n"
        + "    'depot' => $depot_styles,\n"
        + "    'geisshof' => $geisshof_styles,\n"
        + "    'without_view_style' => $GLOBALS['BIOCO_ENQUEUED_STYLES'],\n"
        + "    'file_style' => bioco_dynamic_view_style_handle('bioco/example', 'file:./style.css'),\n"
        + "]);"
    )

    assert payload == {
        "depot": ["bioco-leaflet"],
        "geisshof": ["bioco-leaflet"],
        "without_view_style": [],
        "file_style": "bioco-example-view-style",
    }


def test_context_stack_restores_outer_context_and_never_leaks():
    payload = _json_php(
        _render_preamble()
        + "$GLOBALS['BIOCO_NEST_COMPONENT'] = 'saisonkalender';\n"
        + "$outer = bioco_render_dynamic_component('contact_form', ['title' => 'OUTER']);\n"
        + "$after_nested = bioco_field('title', 'fallback');\n"
        + "$second = bioco_render_dynamic_component('contact_form', ['phone_label' => 'SECOND']);\n"
        + "$after_second = bioco_field('missing', 'fallback');\n"
        + "echo json_encode(compact('outer', 'after_nested', 'second', 'after_second'));"
    )

    assert "NESTED" in payload["outer"]
    assert "<i data-outer-title>OUTER</i>" in payload["outer"]
    assert payload["after_nested"] == "acf:title"
    assert "OUTER" not in payload["second"]
    assert "SECOND" in payload["second"]
    assert payload["after_second"] == "acf:missing"


def test_throwing_template_cannot_corrupt_context_stack():
    payload = _json_php(
        _render_preamble()
        + "$GLOBALS['BIOCO_THROW_LOCALIZE'] = true;\n"
        + "try { bioco_render_dynamic_component('contact_form', ['title' => 'LEAK']); } catch (RuntimeException $e) {}\n"
        + "$GLOBALS['BIOCO_THROW_LOCALIZE'] = false;\n"
        + "echo json_encode(['field' => bioco_field('title', 'fallback'), 'buffer_level' => ob_get_level()]);"
    )

    assert payload == {"field": "acf:title", "buffer_level": 0}


def test_filters_are_guarded_and_use_one_expander_callback():
    registered = _json_php(
        _runtime_preamble()
        + "echo json_encode($GLOBALS['BIOCO_FILTERS']);"
    )
    plain_php = _run_php(
        "define('ABSPATH', __DIR__);\n"
        f"require '{DYNAMIC_SECTIONS}';\n"
        "echo bioco_dynamic_expand_markers('plain');"
    ).stdout

    assert registered == [
        ["render_block", "bioco_dynamic_expand_markers", 10, 2],
        ["the_content", "bioco_dynamic_expand_markers", 99, 1],
    ]
    assert plain_php == "plain"


def test_core_wires_runtime_and_only_target_templates_use_dynamic_fields():
    core = (ROOT / "wordpress/web/app/mu-plugins/bioco-core/bioco-core.php").read_text()
    assert "includes/dynamic-sections.php" in core

    target_dirs = {block_name.split("/", 1)[1] for block_name in COMPONENTS.values()}
    blocks_root = ROOT / "wordpress/web/app/mu-plugins/bioco-core/blocks"
    for render_path in blocks_root.glob("*/render.php"):
        source = render_path.read_text()
        if render_path.parent.name in target_dirs:
            assert "get_field('" not in source or render_path.parent.name == "group-cards"
            if render_path.parent.name == "group-cards":
                assert "$limit = (int) (bioco_field('limit') ?: -1);" in source
                assert "$text = get_field('group_text', $post_id);" in source
        else:
            assert "bioco_field(" not in source
