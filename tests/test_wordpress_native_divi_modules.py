"""Native Divi contracts: tests/README.md, Keep/Replace/Remove map."""
import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
CORE_DIR = "wordpress/web/app/mu-plugins/bioco-core"
NATIVE_MODULES = f"{CORE_DIR}/includes/native-modules.php"
DYNAMIC_SECTIONS = f"{CORE_DIR}/includes/dynamic-sections.php"
BIOCO_FORMS = "wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php"


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


def _php_error(code: str) -> str:
    return _run_php(code, check=False).stderr


def _preamble(
    *,
    stubs: str = "",
    rest: bool = False,
    doing_ajax: bool = False,
    admin: bool = False,
    vb: bool = False,
    with_seam: bool = False,
) -> str:
    """Harness preamble. When `with_seam` is set the caller supplies the real
    dynamic-sections.php + production bioco-forms via `stubs`; otherwise a
    minimal component-map stub is defined so validation can run standalone."""
    env = (
        f"if ({'true' if rest else 'false'}) define('REST_REQUEST', true);\n"
        f"function wp_doing_ajax() {{ return {'true' if doing_ajax else 'false'}; }}\n"
        f"function is_admin() {{ return {'true' if admin else 'false'}; }}\n"
        "function et_core_is_fb_enabled() { return " + ("true" if vb else "false") + "; }\n"
    )
    seam = "" if with_seam else (
        "function bioco_dynamic_components() {\n"
        "    return ['contact_form' => 'bioco/contact-form', 'gallery' => 'bioco/gallery'];\n"
        "}\n"
    )
    return (
        "define('ABSPATH', __DIR__);\n"
        "$GLOBALS['BIOCO_FILTERS'] = [];\n"
        "$GLOBALS['BIOCO_USERS'] = [];\n"
        "$GLOBALS['BIOCO_NONCES'] = [];\n"
        "$GLOBALS['BIOCO_POSTS'] = [];\n"
        "$GLOBALS['BIOCO_POSTDATA'] = [];\n"
        "$GLOBALS['BIOCO_ROUTES'] = [];\n"
        "$GLOBALS['BIOCO_CALLS'] = [];\n"
        "function add_filter($hook, $callback, $priority = 10, $args = 1) {\n"
        "    $GLOBALS['BIOCO_FILTERS'][] = [$hook, $callback, $priority, $args];\n"
        "}\n"
        "function add_action($hook, $callback, $priority = 10, $args = 1) {\n"
        "    $GLOBALS['BIOCO_FILTERS'][] = [$hook, $callback, $priority, $args];\n"
        "}\n"
        "function register_rest_route($namespace, $route, $args) {\n"
        "    $GLOBALS['BIOCO_ROUTES'][] = [$namespace, $route, $args];\n"
        "}\n"
        "function wp_verify_nonce($nonce, $action = -1) {\n"
        "    return in_array([$nonce, $action], $GLOBALS['BIOCO_NONCES'], true) ? 1 : false;\n"
        "}\n"
        "function current_user_can($cap, ...$args) {\n"
        "    $user = $GLOBALS['BIOCO_USERS']['current'] ?? null;\n"
        "    if (!$user) return false;\n"
        "    if (str_starts_with($cap, 'edit_post')) {\n"
        "        $post_id = $args[0] ?? 0;\n"
        "        return in_array($post_id, $user['editable_posts'] ?? [], true);\n"
        "    }\n"
        "    return in_array($cap, $user['caps'] ?? [], true);\n"
        "}\n"
        "class WP_Error {\n"
        "    public $code; public $message; public $status;\n"
        "    public function __construct($code = '', $message = '', $data = []) {\n"
        "        $this->code = $code; $this->message = $message;\n"
        "        $this->status = $data['status'] ?? null;\n"
        "    }\n"
        "    public function get_error_code() { return $this->code; }\n"
        "    public function get_error_message() { return $this->message; }\n"
        "}\n"
        "function get_post($post_id) {\n"
        "    $GLOBALS['BIOCO_CALLS'][] = 'get_post:' . $post_id;\n"
        "    if (isset($GLOBALS['BIOCO_POSTS'][$post_id])) {\n"
        "        $post = new stdClass();\n"
        "        $post->ID = $post_id;\n"
        "        $post->post_type = 'page';\n"
        "        return $post;\n"
        "    }\n"
        "    return null;\n"
        "}\n"
        "function setup_postdata($post) {\n"
        "    $GLOBALS['BIOCO_CALLS'][] = 'setup_postdata:' . $post->ID;\n"
        "    $GLOBALS['id'] = $post->ID;\n"
        "}\n"
        "function wp_reset_postdata() {\n"
        "    $GLOBALS['BIOCO_CALLS'][] = 'wp_reset_postdata';\n"
        "    unset($GLOBALS['id']);\n"
        "}\n"
        "function get_the_ID() {\n"
        "    $post = $GLOBALS['post'] ?? null;\n"
        "    $id = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;\n"
        "    $GLOBALS['BIOCO_CALLS'][] = 'get_the_ID:' . $id;\n"
        "    return $id;\n"
        "}\n"
        "class WP_REST_Request {}\n"
        + env
        + seam
        + f"require '{NATIVE_MODULES}';\n"
        + stubs
    )


def _request_class(name: str) -> str:
    return (
        f"class {name} extends WP_REST_Request {{\n"
        "    public $headers = []; public $params = []; public $json_params = [];\n"
        "    public function get_header($name) { return $this->headers[$name] ?? ''; }\n"
        "    public function get_param($name) { return $this->params[$name] ?? null; }\n"
        "    public function get_json_params() { return $this->json_params; }\n"
        "}\n"
    )


def _render_stubs() -> str:
    """Spies plus the REAL production bioco-forms localizer and the real seam.
    No fake guard: removing the production inert guard must fail the tests."""
    return (
        "$GLOBALS['BIOCO_ENQUEUED'] = [];\n"
        "$GLOBALS['BIOCO_ENQUEUED_STYLES'] = [];\n"
        "$GLOBALS['BIOCO_LOCALIZED'] = [];\n"
        "function wp_enqueue_script($handle, ...$args) { $GLOBALS['BIOCO_ENQUEUED'][] = $handle; }\n"
        "function wp_enqueue_style($handle, ...$args) { $GLOBALS['BIOCO_ENQUEUED_STYLES'][] = $handle; }\n"
        "function wp_localize_script($handle, $object, $values) {\n"
        "    $GLOBALS['BIOCO_LOCALIZED'][] = [$handle, $object, $values];\n"
        "}\n"
        "function wp_get_environment_type() { return 'production'; }\n"
        "function esc_url_raw($value) { return (string)$value; }\n"
        "function rest_url($path = '/') { return 'https://example.test/wp-json/' . ltrim((string)$path, '/'); }\n"
        "function get_field($name, ...$args) { return 'acf:' . $name; }\n"
        "function bioco_text_has_heading_html($html) {\n"
        "    return isset($GLOBALS['BIOCO_HEADING_CB']) ? call_user_func($GLOBALS['BIOCO_HEADING_CB'], $html) : false;\n"
        "}\n"
        "function bioco_kses_rich_text($html) {\n"
        "    if (isset($GLOBALS['BIOCO_KSES_THROW'])) { throw $GLOBALS['BIOCO_KSES_THROW']; }\n"
        "    return (string)$html;\n"
        "}\n"
        "function wp_kses($html, $allowed) { return (string)$html; }\n"
        "function wp_get_attachment_image_url($attachment_id, $size = 'large') {\n"
        "    return 'https://example.test/img-' . $attachment_id . '.jpg';\n"
        "}\n"
        "function get_post_meta($post_id, $key, $single = false) { return ''; }\n"
        "function get_the_title() { return 'Testseite'; }\n"
        "function is_singular($type) { return false; }\n"
        "function __($value, $domain = null) { return $value; }\n"
        "function sanitize_text_field($value) { return trim((string)$value); }\n"
        "function wp_unslash($value) { return $value; }\n"
        "function get_permalink($post_id = 0) { return 'https://example.test/current/'; }\n"
        "function home_url($path = '/') { return 'https://example.test' . $path; }\n"
        "function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }\n"
        "function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }\n"
        "function esc_url($value) { return (string)$value; }\n"
        f"require '{BIOCO_FORMS}';\n"
        f"require '{CORE_DIR}/includes/dynamic-sections.php';\n"
    )


class TestRegistry:
    def test_native_components_map_is_canonical(self):
        payload = _json_php(
            _preamble()
            + "echo json_encode(bioco_native_components());"
        )
        assert len(payload) == 15
        assert payload["bioco-divi/contact-form"] == "contact_form"
        assert payload["bioco-divi/gallery"] == "gallery"

    def test_registration_uses_sdk_with_render_callback(self):
        stubs = (
            "class ET_Builder_Test_ModuleRegistration {\n"
            "    public static $calls = [];\n"
            "    public static function register_module($metadata_folder, array $args = []) {\n"
            "        self::$calls[] = [$metadata_folder, $args];\n"
            "        return new WP_Block_Type_Stub();\n"
            "    }\n"
            "}\n"
            "class_alias('ET_Builder_Test_ModuleRegistration', 'ET\\\\Builder\\\\Packages\\\\ModuleLibrary\\\\ModuleRegistration');\n"
            "class WP_Block_Type_Stub {}\n"
        )
        payload = _json_php(
            _preamble(stubs=stubs)
            + "$GLOBALS['BIOCO_FILTERS'] = [];\n"
            + "bioco_native_register_modules();\n"
            + "echo json_encode([\n"
            + "    'calls' => ET_Builder_Test_ModuleRegistration::$calls,\n"
            + "    'hooks' => $GLOBALS['BIOCO_FILTERS'],\n"
            + "]);"
        )
        calls = payload["calls"]
        assert len(calls) == 15
        folders = [folder for folder, _args in calls]
        assert {Path(folder).name for folder in folders} == {p.parent.name for p in (ROOT / CORE_DIR / "native-modules").glob("*/module.json")}
        for _folder, args in calls:
            assert args["render_callback"] == "bioco_native_render_block"

    def test_registration_is_skipped_without_divi_sdk(self):
        payload = _json_php(
            _preamble()
            + "$before = $GLOBALS['BIOCO_FILTERS']; bioco_native_register_modules();\n"
            + "echo json_encode(['before' => $before, 'after' => $GLOBALS['BIOCO_FILTERS']]);"
        )
        assert payload["before"] == payload["after"]


class TestExtraction:
    def test_contact_values_extract_from_divi_attr_shape(self):
        payload = _json_php(
            _preamble()
            + "$attrs = [\n"
            + "  'title' => ['innerContent' => ['desktop' => ['value' => 'Kontakt aus VB']]],\n"
            + "  'text' => ['innerContent' => ['desktop' => ['value' => '<p>Zeile 1<br>Zeile 2</p>']]],\n"
            + "  'phone_label' => ['innerContent' => ['desktop' => ['value' => 'Telefon privat']]],\n"
            + "  'submit_label' => ['innerContent' => ['desktop' => ['value' => 'Nachricht senden']]],\n"
            + "];\n"
            + "echo json_encode((object) bioco_native_extract_values('contact_form', $attrs));"
        )
        assert payload == {
            "title": "Kontakt aus VB",
            "text": "<p>Zeile 1<br>Zeile 2</p>",
            "phone_label": "Telefon privat",
            "submit_label": "Nachricht senden",
        }

    def test_contact_values_absent_fields_are_dropped(self):
        payload = _json_php(
            _preamble()
            + "echo json_encode((object) bioco_native_extract_values('contact_form', []));"
        )
        assert payload == {}

    def test_empty_zero_false_values_are_preserved(self):
        payload = _json_php(
            _preamble()
            + "$attrs = [\n"
            + "  'title' => ['innerContent' => ['desktop' => ['value' => '']]],\n"
            + "];\n"
            + "$values = bioco_native_extract_values('contact_form', $attrs);\n"
            + "echo json_encode(['title_kept' => array_key_exists('title', $values) && $values['title'] === '']);"
        )
        assert payload == {"title_kept": True}

    def test_anchor_and_class_extract_from_module_advanced_html_attributes(self):
        payload = _json_php(
            _preamble()
            + "$attrs = ['module' => ['advanced' => ['htmlAttributes' => [\n"
            + "  'desktop' => ['value' => ['id' => 'kontakt-formular', 'class' => 'extra-class']],\n"
            + "]]]];\n"
            + "echo json_encode((object) bioco_native_extract_wrapper($attrs));"
        )
        assert payload == {"anchor": "kontakt-formular", "className": "extra-class"}


class TestGalleryExtraction:
    def _gallery_attrs(self, rows: str, filters: str) -> str:
        return (
            "$attrs = [\n"
            f"  'items' => ['innerContent' => ['desktop' => ['value' => {rows}]]],\n"
            f"  'filters' => ['innerContent' => ['desktop' => ['value' => {filters}]]],\n"
            "];\n"
        )

    def test_typed_rows_ids_categories_and_order_survive_extraction(self):
        payload = _json_php(
            _preamble()
            + self._gallery_attrs(
                "[['image' => 7, 'category' => 'koerbe'], ['image' => 12, 'category' => 'feld'], ['image' => 3, 'category' => 'portraits']]",
                "[['key' => 'all', 'label' => 'Alle'], ['key' => 'koerbe', 'label' => 'Körbe']]",
            )
            + "$values = bioco_native_extract_values('gallery', $attrs);\n"
            + "echo json_encode([\n"
            + "  'rows' => $values['items'],\n"
            + "  'filter_count' => count($values['filters']),\n"
            + "  'first_filter_key' => $values['filters'][0]['key'],\n"
            + "  'ids' => array_column($values['items'], 'image'),\n"
            + "  'order' => array_column($values['items'], 'category'),\n"
            + "]);"
        )
        assert payload["ids"] == [7, 12, 3]
        assert payload["order"] == ["koerbe", "feld", "portraits"]
        assert payload["filter_count"] == 2
        assert payload["first_filter_key"] == "all"

    def test_no_rows_beyond_saved_content_are_dropped_or_truncated(self):
        payload = _json_php(
            _preamble()
            + self._gallery_attrs(
                "[" + ",".join(f"['image' => {i}, 'category' => 'feld']" for i in range(1, 21)) + "]",
                "[['key' => 'all', 'label' => 'Alle']]",
            )
            + "$values = bioco_native_extract_values('gallery', $attrs);\n"
            + "echo json_encode(['item_count' => count($values['items']), 'first' => $values['items'][0]['image'], 'last' => $values['items'][19]['image']]);"
        )
        assert payload == {"item_count": 20, "first": 1, "last": 20}

    def test_empty_rows_lists_are_preserved(self):
        payload = _json_php(
            _preamble()
            + self._gallery_attrs("[]", "[]")
            + "$values = bioco_native_extract_values('gallery', $attrs);\n"
            + "echo json_encode([\n"
            + "  'items_kept' => array_key_exists('items', $values) && $values['items'] === [],\n"
            + "  'filters_kept' => array_key_exists('filters', $values) && $values['filters'] === [],\n"
            + "]);"
        )
        assert payload == {"items_kept": True, "filters_kept": True}


class TestSchema:
    def test_valid_contact_values_pass_normalized(self):
        payload = _json_php(
            _preamble()
            + "$values = ['title' => 'Kontakt', 'text' => '<p>Hi</p>', 'submit_label' => 'Senden'];\n"
            + "$result = bioco_native_validate_values('contact_form', $values);\n"
            + "echo json_encode($result instanceof WP_Error ? ['error' => $result->get_error_code()] : ['ok' => (object) $result]);"
        )
        assert payload == {"ok": {
            "title": "Kontakt",
            "text": "<p>Hi</p>",
            "submit_label": "Senden",
        }}

    def test_unknown_component_and_keys_are_rejected(self):
        payload = _json_php(
            _preamble()
            + "$unknown = bioco_native_validate_values('not_real', []);\n"
            + "$extra = bioco_native_validate_values('contact_form', ['raw_html' => '<script>']);\n"
            + "echo json_encode([\n"
            + "  'unknown' => $unknown instanceof WP_Error,\n"
            + "  'unknown_status' => $unknown->status,\n"
            + "  'extra' => $extra instanceof WP_Error,\n"
            + "  'extra_status' => $extra->status,\n"
            + "]);"
        )
        assert payload["unknown"] is True
        assert payload["extra"] is True
        assert payload["unknown_status"] == 400
        assert payload["extra_status"] == 400

    def test_native_names_are_not_valid_request_components(self):
        # The native registry domain (bioco-divi/...) is deliberately distinct
        # from the request component domain (contact_form/gallery).
        payload = _json_php(
            _preamble()
            + "$by_native_name = bioco_native_validate_values('bioco-divi/contact-form', []);\n"
            + "echo json_encode(['native_name_rejected' => $by_native_name instanceof WP_Error]);"
        )
        assert payload == {"native_name_rejected": True}

    def test_type_mismatches_are_rejected_not_coerced(self):
        payload = _json_php(
            _preamble()
            + "$obj_text = bioco_native_validate_values('contact_form', ['text' => ['object' => 'not string']]);\n"
            + "$obj_rows = bioco_native_validate_values('gallery', ['items' => 'not-a-list']);\n"
            + "echo json_encode([\n"
            + "  'obj' => $obj_text instanceof WP_Error,\n"
            + "  'obj_rows' => $obj_rows instanceof WP_Error,\n"
            + "]);"
        )
        assert payload == {"obj": True, "obj_rows": True}

    def test_long_editorial_values_are_not_silently_lost(self):
        payload = _json_php(
            _preamble()
            + "$long = bioco_native_validate_values('contact_form', ['title' => str_repeat('x', 301)]);\n"
            + "echo json_encode([\n"
            + "  'valid' => !($long instanceof WP_Error),\n"
            + "  'length' => $long instanceof WP_Error ? null : strlen($long['title']),\n"
            + "]);"
        )
        assert payload == {"valid": True, "length": 301}


class TestGalleryValidation:
    def _rows(self, n: int, category: str = "feld") -> str:
        return "[" + ",".join(f"['image' => {i + 1}, 'category' => '{category}']" for i in range(n)) + "]"

    def test_valid_gallery_values_pass_with_order(self):
        payload = _json_php(
            _preamble()
            + "$values = ['items' => " + self._rows(2, "koerbe") + ",\n"
            + "  'filters' => [['key' => 'all', 'label' => 'Alle'], ['key' => 'koerbe', 'label' => 'Körbe']]];\n"
            + "$result = bioco_native_validate_values('gallery', $values);\n"
            + "echo json_encode([\n"
            + "  'ok' => !($result instanceof WP_Error),\n"
            + "  'first_category' => $result instanceof WP_Error ? null : $result['items'][0]['category'],\n"
            + "  'filter_keys' => $result instanceof WP_Error ? null : array_column($result['filters'], 'key'),\n"
            + "]);"
        )
        assert payload == {"ok": True, "first_category": "koerbe", "filter_keys": ["all", "koerbe"]}

    def test_invalid_category_is_rejected(self):
        payload = _json_php(
            _preamble()
            + "$values = ['items' => [['image' => 7, 'category' => 'hacked']],\n"
            + "  'filters' => [['key' => 'all', 'label' => 'Alle']]];\n"
            + "$result = bioco_native_validate_values('gallery', $values);\n"
            + "echo json_encode(['rejected' => $result instanceof WP_Error, 'status' => $result->status]);"
        )
        assert payload == {"rejected": True, "status": 400}

    def test_filter_rules_mirror_acf_validation(self):
        cases = {
            "no_all": "[['key' => 'koerbe', 'label' => 'K']]",
            "two_all": "[['key' => 'all', 'label' => 'A1'], ['key' => 'all', 'label' => 'A2']]",
            "duplicate_key": "[['key' => 'all', 'label' => 'A'], ['key' => 'koerbe', 'label' => 'K'], ['key' => 'koerbe', 'label' => 'K2']]",
            "unknown_key": "[['key' => 'all', 'label' => 'A'], ['key' => 'sonst', 'label' => 'S']]",
        }
        for label, filters in cases.items():
            payload = _json_php(
                _preamble()
                + "$values = ['items' => " + self._rows(1) + f", 'filters' => {filters}];\n"
                + "$result = bioco_native_validate_values('gallery', $values);\n"
                + "echo json_encode(['rejected' => $result instanceof WP_Error]);"
            )
            assert payload == {"rejected": True}, label

    def test_empty_filters_list_is_valid_without_rule_check(self):
        payload = _json_php(
            _preamble()
            + "$values = ['items' => [], 'filters' => []];\n"
            + "$result = bioco_native_validate_values('gallery', $values);\n"
            + "echo json_encode(['ok' => !($result instanceof WP_Error)]);"
        )
        assert payload == {"ok": True}

    def test_image_id_must_be_integer(self):
        for image in ("abc", 1.5, ["7"], None):
            payload = _json_php(
                _preamble()
                + f"$values = ['items' => [['image' => {json.dumps(image)}, 'category' => 'feld']],\n"
                + "  'filters' => [['key' => 'all', 'label' => 'Alle']]];\n"
                + "$result = bioco_native_validate_values('gallery', $values);\n"
                + "echo json_encode(['rejected' => $result instanceof WP_Error]);"
            )
            assert payload == {"rejected": True}, image
        # ints of any value are type-valid: attachment resolution (including a
        # not-yet-completed row with 0) is the renderer's concern — it skips
        # rows whose attachment resolves to no URL.
        for image in (0, -3, 44, "7"):
            payload = _json_php(
                _preamble()
                + f"$values = ['items' => [['image' => {json.dumps(image)}, 'category' => 'feld']],\n"
                + "  'filters' => [['key' => 'all', 'label' => 'Alle']]];\n"
                + "$result = bioco_native_validate_values('gallery', $values);\n"
                + "echo json_encode(['accepted' => !($result instanceof WP_Error), 'image' => $result instanceof WP_Error ? null : $result['items'][0]['image']]);"
            )
            assert payload["accepted"] is True, image
            if isinstance(image, str):
                assert payload["image"] == 7, image
            else:
                assert payload["image"] == image, image

    def test_string_attachment_id_is_converted(self):
        payload = _json_php(
            _preamble()
            + "$values = ['items' => [['image' => '7', 'category' => 'feld']],\n"
            + "  'filters' => [['key' => 'all', 'label' => 'Alle']]];\n"
            + "$result = bioco_native_validate_values('gallery', $values);\n"
            + "echo json_encode(['ok' => !($result instanceof WP_Error), 'image' => $result instanceof WP_Error ? null : $result['items'][0]['image']]);"
        )
        assert payload == {"ok": True, "image": 7}

    def test_empty_rows_and_empty_labels_are_preserved(self):
        payload = _json_php(
            _preamble()
            + "$values = ['items' => [], 'filters' => [['key' => 'all', 'label' => '']]];\n"
            + "$result = bioco_native_validate_values('gallery', $values);\n"
            + "echo json_encode([\n"
            + "  'ok' => !($result instanceof WP_Error),\n"
            + "  'empty_label_kept' => $result instanceof WP_Error ? null : ($result['filters'][0]['label'] === ''),\n"
            + "]);"
        )
        assert payload == {"ok": True, "empty_label_kept": True}


class TestPreviewPermission:
    def _permission_case(self, code: str) -> str:
        return _json_php(
            _preamble()
            + "$GLOBALS['BIOCO_USERS']['current'] = ['editable_posts' => [55, 77], 'caps' => ['edit_posts']];\n"
            "$GLOBALS['BIOCO_POSTS'] = [55 => true, 77 => true, 99 => true];\n"
            "$GLOBALS['BIOCO_NONCES'][] = ['valid-nonce', 'wp_rest'];\n"
            "function bioco_native_preview_request($nonce, $post_id, $component) {\n"
            "    $request = new Bioco_Test_Request();\n"
            "    $request->headers = $nonce;\n"
            "    $request->params = ['post_id' => $post_id, 'component' => $component];\n"
            "    $result = bioco_native_preview_permission($request);\n"
            "    return is_bool($result) ? $result : ($result === true);\n"
            "}\n"
            + _request_class("Bioco_Test_Request")
            + code
        )

    def test_valid_nonce_and_editable_target_is_allowed(self):
        payload = self._permission_case(
            "echo json_encode(bioco_native_preview_request(['X-WP-Nonce' => 'valid-nonce'], 55, 'contact_form'));"
        )
        assert payload is True

    def test_missing_or_wrong_nonce_is_denied(self):
        payload = self._permission_case(
            "echo json_encode([\n"
            "  'missing' => bioco_native_preview_request([], 55, 'contact_form'),\n"
            "  'wrong' => bioco_native_preview_request(['X-WP-Nonce' => 'bad-nonce'], 55, 'contact_form'),\n"
            "]);"
        )
        assert payload == {"missing": False, "wrong": False}

    def test_existing_forbidden_nonexistent_anonymous_and_subscriber_are_denied(self):
        # 99 exists but is NOT editable by the current user; 1234 does not
        # exist; the last two cases run with a subscriber/anonymous user.
        payload = self._permission_case(
            "echo json_encode([\n"
            "  'existing_forbidden' => bioco_native_preview_request(['X-WP-Nonce' => 'valid-nonce'], 99, 'contact_form'),\n"
            "  'nonexistent' => bioco_native_preview_request(['X-WP-Nonce' => 'valid-nonce'], 1234, 'contact_form'),\n"
            "]);"
        )
        assert payload == {"existing_forbidden": False, "nonexistent": False}

    def test_subscriber_and_anonymous_contexts_are_denied(self):
        payload = _json_php(
            _preamble()
            + "$GLOBALS['BIOCO_POSTS'] = [55 => true, 77 => true];\n"
            "$GLOBALS['BIOCO_NONCES'][] = ['valid-nonce', 'wp_rest'];\n"
            "function bioco_native_preview_request($nonce, $post_id, $component, $user) {\n"
            "    $GLOBALS['BIOCO_USERS']['current'] = $user;\n"
            "    $request = new Bioco_Test_Request();\n"
            "    $request->headers = $nonce;\n"
            "    $request->params = ['post_id' => $post_id, 'component' => $component];\n"
            "    $result = bioco_native_preview_permission($request);\n"
            "    return is_bool($result) ? $result : ($result === true);\n"
            "}\n"
            + _request_class("Bioco_Test_Request")
            + "echo json_encode([\n"
            "  'subscriber' => bioco_native_preview_request(['X-WP-Nonce' => 'valid-nonce'], 55, 'contact_form', ['editable_posts' => [], 'caps' => []]),\n"
            "  'anonymous' => bioco_native_preview_request(['X-WP-Nonce' => 'valid-nonce'], 55, 'contact_form', null),\n"
            "]);"
        )
        assert payload == {"subscriber": False, "anonymous": False}

    def test_nonce_under_other_header_name_is_denied(self):
        # A nonce carried in a non-X-WP-Nonce header must not authenticate the
        # request: the permission check is keyed on the actual WP REST header.
        payload = self._permission_case(
            "echo json_encode(bioco_native_preview_request(['X-Bioco-Nonce' => 'valid-nonce'], 55, 'contact_form'));"
        )
        assert payload is False

    def test_denial_precedes_render_and_disclosing_lookups(self):
        # A denied request must never reach the render callback; the only
        # lookup allowed before the capability gate is the post existence
        # check, and no render-time helpers may run at all.
        payload = _json_php(
            _preamble()
            + _request_class("Bioco_Test_Request")
            + "$GLOBALS['BIOCO_POSTS'] = [55 => true];\n"
            + "$GLOBALS['BIOCO_NONCES'][] = ['valid-nonce', 'wp_rest'];\n"
            + "$GLOBALS['BIOCO_USERS']['current'] = null;\n"
            + "$r = new Bioco_Test_Request();\n"
            + "$r->headers = ['X-WP-Nonce' => 'valid-nonce'];\n"
            + "$r->params = ['post_id' => 55, 'component' => 'contact_form'];\n"
            + "bioco_native_preview_permission($r);\n"
            + "$anonymous_calls = $GLOBALS['BIOCO_CALLS'];\n"
            + "$GLOBALS['BIOCO_CALLS'] = [];\n"
            + "$GLOBALS['BIOCO_USERS']['current'] = ['editable_posts' => [], 'caps' => []];\n"
            + "bioco_native_preview_permission($r);\n"
            + "$subscriber_calls = $GLOBALS['BIOCO_CALLS'];\n"
            + "echo json_encode([\n"
            + "  'anonymous_calls' => $anonymous_calls,\n"
            + "  'subscriber_calls' => $subscriber_calls,\n"
            + "]);"
        )
        assert payload["anonymous_calls"] == ["get_post:55"]  # existence only, no render helpers
        assert payload["subscriber_calls"] == ["get_post:55"]  # existence only, no render helpers


class TestPreviewRender:
    def _preview_fixture(self) -> str:
        return (
            "$GLOBALS['BIOCO_USERS']['current'] = ['editable_posts' => [55], 'caps' => ['edit_posts']];\n"
            "$GLOBALS['BIOCO_NONCES'][] = ['valid-nonce', 'wp_rest'];\n"
            "$GLOBALS['BIOCO_POSTS'] = [55 => true];\n"
            + _request_class("Bioco_Test_Request")
        )

    def test_preview_forces_inert_and_restores_post_context(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._preview_fixture()
            + "$request = new Bioco_Test_Request();\n"
            + "$request->headers = ['X-WP-Nonce' => 'valid-nonce'];\n"
            + "$request->params = ['component' => 'contact_form', 'post_id' => 55, 'values' => ['title' => 'VB Titel']];\n"
            + "$GLOBALS['post'] = (object) ['ID' => 1];\n"
            + "$GLOBALS['more'] = 77;\n"
            + "$GLOBALS['id'] = 1;\n"
            + "$response = bioco_native_preview_render($request);\n"
            + "$html = is_array($response) ? ($response['data'] ?? '') : '';\n"
            + "$render_happened = in_array('get_the_ID:55', $GLOBALS['BIOCO_CALLS'], true);\n"
            + "$post_render_id = get_the_ID();\n"
            + "echo json_encode([\n"
            + "  'html' => $html,\n"
            + "  'target_context' => $render_happened,\n"
            + "  'post_restored' => $GLOBALS['post']->ID === 1,\n"
            + "  'more_restored' => $GLOBALS['more'] === 77,\n"
            + "  'id_restored' => $GLOBALS['id'] === 1,\n"
            + "  'post_render_id' => $post_render_id,\n"
            + "  'view_scripts' => $GLOBALS['BIOCO_ENQUEUED'],\n"
            + "  'localized' => $GLOBALS['BIOCO_LOCALIZED'],\n"
            + "]);",
        )
        html = payload["html"]
        assert "VB Titel" in html
        assert 'class="cms-section cms-contact-form"' in html
        assert 'data-bioco-preview="1"' in html
        assert " inert" in html
        assert "<form" not in html
        assert "</form>" not in html
        assert 'type="submit"' not in html
        assert 'type="button"' in html
        assert "bioco-dynamic" not in html
        assert payload["view_scripts"] == []
        assert payload["localized"] == []
        assert payload["target_context"] is True
        assert payload["post_restored"] is True
        assert payload["more_restored"] is True
        assert payload["id_restored"] is True
        assert payload["post_render_id"] == 1

    def test_public_render_keeps_real_form_and_localization(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + "$GLOBALS['BIOCO_ENQUEUED'] = [];\n"
            + "$GLOBALS['BIOCO_LOCALIZED'] = [];\n"
            + "$html = bioco_render_dynamic_component('contact_form', ['title' => 'Publik'], ['mode' => 'public']);\n"
            + "echo json_encode([\n"
            + "  'html' => $html,\n"
            + "  'enqueued' => $GLOBALS['BIOCO_ENQUEUED'],\n"
            + "  'localized' => $GLOBALS['BIOCO_LOCALIZED'],\n"
            + "]);"
        )
        html = payload["html"]
        assert 'class="contact-form bioco-form"' in html  # real form intact
        assert 'data-bioco-preview' not in html
        assert 'type="submit"' in html
        assert "</form>" in html
        assert payload["enqueued"] == ["bioco-contact-form-view-script"]
        assert payload["localized"] == [[
            "bioco-contact-form-view-script",
            "biocoContactFormConfig",
            {
                "restUrl": "https://example.test/wp-json/bioco/v1/contact",
                "turnstileSiteKey": "",
                "successMessage": "", "fallbackError": "", "captchaError": "", "transportError": "",
            },
        ]]

    def test_nested_public_render_inside_inert_render_restores_inert(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + "$GLOBALS['BIOCO_HEADING_CB'] = function ($html) {\n"
            + "    if (isset($GLOBALS['BIOCO_NEST_RUNNING'])) { return false; }\n"
            + "    $GLOBALS['BIOCO_NEST_RUNNING'] = true;\n"
            + "    $GLOBALS['BIOCO_OUTER_INERT'] = bioco_dynamic_render_is_inert();\n"
            + "    $inner = bioco_render_dynamic_component('contact_form', ['title' => 'inner'], ['mode' => 'public']);\n"
            + "    $GLOBALS['BIOCO_INNER_WAS_PUBLIC'] = strpos($inner, 'data-bioco-preview') === false && strpos($inner, '<form') !== false;\n"
            + "    $GLOBALS['BIOCO_AFTER_INNER'] = bioco_dynamic_render_is_inert();\n"
            + "    unset($GLOBALS['BIOCO_NEST_RUNNING']);\n"
            + "    return false;\n"
            + "};\n"
            + "bioco_render_dynamic_component('contact_form', ['title' => 'outer', 'text' => 'NEST'], ['mode' => 'inert']);\n"
            + "echo json_encode([\n"
            + "  'outer_inert' => $GLOBALS['BIOCO_OUTER_INERT'],\n"
            + "  'inner_was_public' => $GLOBALS['BIOCO_INNER_WAS_PUBLIC'],\n"
            + "  'after_inner_still_inert' => $GLOBALS['BIOCO_AFTER_INNER'],\n"
            + "  'flag_cleared_after_outer' => empty($GLOBALS['bioco_dynamic_render_inert']),\n"
            + "  'stack_empty' => empty($GLOBALS['bioco_dynamic_context_stack']),\n"
            + "]);"
        )
        assert payload == {
            "outer_inert": True,
            "inner_was_public": True,
            "after_inner_still_inert": True,
            "flag_cleared_after_outer": True,
            "stack_empty": True,
        }

    def test_throw_during_render_restores_caller_context_and_stack(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._preview_fixture()
            + "$GLOBALS['post'] = (object) ['ID' => 1];\n"
            + "$GLOBALS['id'] = 1;\n"
            + "$GLOBALS['more'] = 77;\n"
            + "$GLOBALS['BIOCO_KSES_THROW'] = new RuntimeException('renderer exploded');\n"
            + "$caught = null;\n"
            + "try { bioco_render_dynamic_component('contact_form', ['title' => 'X', 'text' => 'Y'], ['mode' => 'inert']); } catch (RuntimeException $e) { $caught = $e->getMessage(); }\n"
            + "echo json_encode([\n"
            + "  'caught' => $caught,\n"
            + "  'post_restored' => $GLOBALS['post']->ID === 1,\n"
            + "  'id_restored' => $GLOBALS['id'] === 1,\n"
            + "  'more_restored' => $GLOBALS['more'] === 77,\n"
            + "  'stack_empty' => empty($GLOBALS['bioco_dynamic_context_stack']),\n"
            + "  'inert_cleared' => empty($GLOBALS['bioco_dynamic_render_inert']),\n"
            + "]);"
        )
        assert payload == {
            "caught": "renderer exploded",
            "post_restored": True,
            "id_restored": True,
            "more_restored": True,
            "stack_empty": True,
            "inert_cleared": True,
        }

    def test_unknown_component_in_preview_route_is_rejected(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._preview_fixture()
            + "$request = new Bioco_Test_Request();\n"
            + "$request->headers = ['X-WP-Nonce' => 'valid-nonce'];\n"
            + "$request->params = ['component' => 'not_real', 'post_id' => 55];\n"
            + "$result = bioco_native_preview_render($request);\n"
            + "echo json_encode(['error' => $result instanceof WP_Error, 'status' => $result instanceof WP_Error ? $result->status : null]);"
        )
        assert payload == {"error": True, "status": 400}

    def test_native_name_is_rejected_as_request_component(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._preview_fixture()
            + "$request = new Bioco_Test_Request();\n"
            + "$request->headers = ['X-WP-Nonce' => 'valid-nonce'];\n"
            + "$request->params = ['component' => 'bioco-divi/contact-form', 'post_id' => 55];\n"
            + "$result = bioco_native_preview_render($request);\n"
            + "echo json_encode(['error' => $result instanceof WP_Error]);"
        )
        assert payload == {"error": True}

    def test_preview_route_registered_with_permission_and_post_args(self):
        payload = _json_php(
            _preamble()
            + "bioco_native_register_rest_route();\n"
            + "echo json_encode($GLOBALS['BIOCO_ROUTES']);"
        )
        assert payload == [
            [
                "bioco/v1",
                "/native-preview",
                {
                    "methods": "POST",
                    "permission_callback": "bioco_native_preview_permission",
                    "callback": "bioco_native_preview_render",
                    "args": {
                        "component": {"type": "string", "required": True},
                        "post_id": {"type": "integer", "required": True},
                        "values": {"type": "object", "required": False, "default": []},
                    },
                },
            ]
        ]


class TestRenderMode:
    def test_environment_detection_forces_inert_contexts(self):
        for label, kwargs in {
            "rest": {"rest": True},
            "ajax": {"doing_ajax": True},
            "admin": {"admin": True},
            "vb": {"vb": True},
        }.items():
            payload = _json_php(
                _preamble(stubs=_render_stubs(), with_seam=True, **kwargs)
                + "echo json_encode(bioco_native_render_mode());"
            )
            assert payload == "inert", label

    def test_plain_frontend_is_public_mode(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + "echo json_encode(bioco_native_render_mode());"
        )
        assert payload == "public"


class TestNativeRenderBlock:
    def _block(self, name: str, class_name: str) -> str:
        return (
            f"class {class_name} {{ public $block_type; public function __construct() {{ $t = new stdClass(); $t->name = '{name}'; $this->block_type = $t; }} }}\n"
        )

    def test_render_block_runs_existing_renderer_with_extracted_values(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._block("bioco-divi/contact-form", "Bioco_Test_Block")
            + "$attrs = [\n"
            + "  'module' => ['advanced' => ['htmlAttributes' => ['desktop' => ['value' => ['id' => 'kontakt-x']]]]],\n"
            + "  'title' => ['innerContent' => ['desktop' => ['value' => 'Native Kontakt']]],\n"
            + "];\n"
            + "$html = bioco_native_render_block($attrs, '', new Bioco_Test_Block(), null);\n"
            + "echo json_encode(['html' => $html, 'mode' => bioco_native_render_mode()]);"
        )
        assert payload["mode"] == "public"
        assert "Native Kontakt" in payload["html"]
        assert 'id="kontakt-x"' in payload["html"]
        assert 'class="cms-section cms-contact-form"' in payload["html"]

    def test_public_mode_enqueues_view_script_and_localizes(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._block("bioco-divi/contact-form", "Bioco_Test_Block2")
            + "$attrs = ['title' => ['innerContent' => ['desktop' => ['value' => 'Kontakt']]]];\n"
            + "bioco_native_render_block($attrs, '', new Bioco_Test_Block2(), null);\n"
            + "echo json_encode(['enqueued' => $GLOBALS['BIOCO_ENQUEUED'], 'localized' => $GLOBALS['BIOCO_LOCALIZED']]);"
        )
        assert payload["enqueued"] == ["bioco-contact-form-view-script"]
        assert payload["localized"] == [[
            "bioco-contact-form-view-script",
            "biocoContactFormConfig",
            {
                "restUrl": "https://example.test/wp-json/bioco/v1/contact",
                "turnstileSiteKey": "",
                "successMessage": "", "fallbackError": "", "captchaError": "", "transportError": "",
            },
        ]]

    def test_native_callback_is_inert_in_rest_mode(self):
        # The actual native block callback — not only the mode detector — must
        # stay inert in a REST/VB context: no view scripts, no localization.
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True, rest=True)
            + self._block("bioco-divi/contact-form", "Bioco_Test_Block3")
            + "$attrs = ['title' => ['innerContent' => ['desktop' => ['value' => 'VB Kontakt']]]];\n"
            + "$html = bioco_native_render_block($attrs, '', new Bioco_Test_Block3(), null);\n"
            + "echo json_encode([\n"
            + "  'html' => $html,\n"
            + "  'enqueued' => $GLOBALS['BIOCO_ENQUEUED'],\n"
            + "  'localized' => $GLOBALS['BIOCO_LOCALIZED'],\n"
            + "  'inert_container' => strpos($html, 'data-bioco-preview=\"1\"') !== false && strpos($html, ' inert') !== false,\n"
            + "  'no_form' => strpos($html, '<form') === false,\n"
            + "]);"
        )
        assert payload["enqueued"] == []
        assert payload["localized"] == []
        assert payload["inert_container"] is True
        assert payload["no_form"] is True
        assert "VB" not in payload["html"] or "Kontakt" in payload["html"]

    def test_invalid_saved_native_content_fails_closed(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._block("bioco-divi/gallery", "Bioco_Test_Block4")
            + "$attrs = ['items' => ['innerContent' => ['desktop' => ['value' => [['image' => 'not-an-id', 'category' => 'feld']]]]]];\n"
            + "$html = bioco_native_render_block($attrs, '', new Bioco_Test_Block4(), null);\n"
            + "echo json_encode(['html' => $html, 'localized' => $GLOBALS['BIOCO_LOCALIZED']]);"
        )
        assert payload["html"] == ""
        assert payload["localized"] == []

    def test_valid_saved_gallery_renders_through_seam(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._block("bioco-divi/gallery", "Bioco_Test_Block5")
            + "$attrs = [\n"
            + "  'items' => ['innerContent' => ['desktop' => ['value' => [['image' => 7, 'category' => 'koerbe']]]]],\n"
            + "  'filters' => ['innerContent' => ['desktop' => ['value' => [['key' => 'all', 'label' => 'Alle']]]]],\n"
            + "];\n"
            + "$html = bioco_native_render_block($attrs, '', new Bioco_Test_Block5(), null);\n"
            + "echo json_encode(['html' => $html]);"
        )
        html = payload["html"]
        assert 'class="cms-section cms-gallery"' in html
        assert "https://example.test/img-7.jpg" in html
        assert 'data-gallery-category="koerbe"' in html
        assert 'data-gallery-filter="all"' in html

    def test_unknown_native_block_renders_empty(self):
        payload = _json_php(
            _preamble(stubs=_render_stubs(), with_seam=True)
            + self._block("bioco-divi/other", "Bioco_Test_Block6")
            + "echo json_encode(bioco_native_render_block([], '', new Bioco_Test_Block6(), null));"
        )
        assert payload == ""


class TestMetadata:
    def test_contact_module_json_declares_editor_settings(self):
        metadata_path = Path(ROOT, CORE_DIR, "native-modules/contact-form/module.json")
        assert metadata_path.exists()
        metadata = json.loads(metadata_path.read_text())
        assert metadata["name"] == "bioco-divi/contact-form"
        assert metadata["category"] == "module"
        assert metadata["settings"]["content"] == "auto"

        attrs = metadata["attributes"]
        for field in ("title", "text", "phone_label", "submit_label", "submitting_label"):
            item = attrs[field]["settings"]["innerContent"]["item"]
            assert item["attrName"] == f"{field}.innerContent"
            assert item["component"]["type"] == "field"
        assert attrs["text"]["settings"]["innerContent"]["item"]["component"]["name"] == "divi/richtext"
        assert attrs["title"]["settings"]["innerContent"]["item"]["component"]["name"] == "divi/text"

    def test_gallery_module_json_declares_row_and_filter_attrs(self):
        metadata_path = Path(ROOT, CORE_DIR, "native-modules/gallery/module.json")
        assert metadata_path.exists()
        metadata = json.loads(metadata_path.read_text())
        assert metadata["name"] == "bioco-divi/gallery"
        attrs = metadata["attributes"]
        for field in ("items", "filters"):
            item = attrs[field]["settings"]["innerContent"]["item"]
            assert item["attrName"] == f"{field}.innerContent"
        assert attrs["items"]["settings"]["innerContent"]["item"]["component"]["name"] == "bioco/gallery-items"
        assert attrs["filters"]["settings"]["innerContent"]["item"]["component"]["name"] == "bioco/gallery-filters"


def test_core_wires_native_modules_include():
    core = Path(ROOT, CORE_DIR, "bioco-core.php").read_text()
    assert "includes/native-modules.php" in core


def test_empty_array_attrs_of_native_modules_survive_save_sanitization():
    payload = _json_php(
        _preamble()
        + "$GLOBALS['BIOCO_FILTERS'] = [];\n"
        + "add_filter('divi_module_utils_remove_empty_array_attributes_pre_filter', 'bioco_native_preserve_empty_array_attrs', 10, 4);\n"
        + "$kept = $GLOBALS['BIOCO_FILTERS'][count($GLOBALS['BIOCO_FILTERS']) - 1][1];\n"
        + "echo json_encode([\n"
        + "  'items_kept' => call_user_func($kept, null, [], 'value', ['items', 'innerContent', 'desktop']) === true,\n"
        + "  'filters_kept' => call_user_func($kept, null, [], 'value', ['filters', 'innerContent', 'mobile']) === true,\n"
        + "  'module_untouched' => call_user_func($kept, null, [], 'value', ['module', 'decoration', 'sizing']) !== true,\n"
        + "  'nonempty_passthrough' => call_user_func($kept, 'sentinel', ['image' => 7], 'value', ['items', 'innerContent', 'desktop']) === 'sentinel',\n"
        + "  'scalar_value_untouched' => call_user_func($kept, null, [], 'value', ['title', 'innerContent', 'desktop']) !== true,\n"
        + "]);"
    )
    assert payload == {
        "items_kept": True,
        "filters_kept": True,
        "module_untouched": True,
        "nonempty_passthrough": True,
        "scalar_value_untouched": True,
    }


def test_all_fifteen_native_modules_have_editorial_fields():
    """Native editor contract: tests/README.md, Keep/Replace/Remove map."""
    payload = _json_php(_preamble() + "echo json_encode(array_map('bioco_native_field_map', array_values(bioco_native_components())));")
    assert len(payload) == 15
    assert all(fields for fields in payload)


def test_season_month_selection_preserves_typed_multiple_values():
    result = _json_php(_preamble() + "echo json_encode(bioco_native_convert_value(['1', '12'], ['type'=>'choices', 'choices'=>['1'=>'Jan','12'=>'Dez']]));")
    assert result == ['1', '12']


def test_builder_saved_decoration_attributes_override_legacy_wrapper():
    attrs = {"module": {"advanced": {"htmlAttributes": {"desktop": {"value": {"id": "stale", "class": "stale"}}}}, "decoration": {"attributes": {"desktop": {"value": {"attributes": [{"name": "id", "value": "edited-anchor"}, {"name": "class", "value": "edited-class"}]}}}}}}
    payload = _json_php(_preamble() + "$attrs = json_decode('" + json.dumps(attrs) + "', true); echo json_encode(bioco_native_extract_wrapper($attrs));")
    assert payload == {"anchor": "edited-anchor", "className": "edited-class"}
