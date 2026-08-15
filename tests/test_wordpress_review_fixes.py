import json
import os
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def test_doi_confirmation_has_no_public_rest_route():
    php = r'''
    define('ABSPATH', __DIR__);
    $ACTIONS = [];
    $ROUTES = [];

    function add_action($hook, $callback) { $GLOBALS['ACTIONS'][$hook][] = $callback; }
    function register_rest_route($namespace, $route, $args) {
        $GLOBALS['ROUTES'][] = $namespace . $route;
    }

    require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';
    foreach ($ACTIONS['rest_api_init'] as $callback) {
        $callback();
    }
    echo json_encode($ROUTES);
    '''
    routes = json.loads(
        subprocess.run(
            ["php", "-r", php],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=True,
        ).stdout
    )

    assert "bioco/v1/doi-confirm" not in routes


def test_gallery_filter_validation_enforces_supported_unique_keys_and_one_all():
    php = r'''
    define('ABSPATH', __DIR__);
    $FILTERS = [];

    function add_filter($hook, $callback) { $GLOBALS['FILTERS'][$hook] = $callback; }
    function add_action($hook, $callback) {}
    function __($text, $domain = null) { return $text; }

    require 'wordpress/web/app/mu-plugins/bioco-core/bioco-core.php';

    $validate = $FILTERS['acf/validate_value/key=field_bioco_gallery_filters'];
    $cases = [
        'valid_field_keys' => [
            ['field_bioco_gallery_filter_key' => 'all'],
            ['field_bioco_gallery_filter_key' => 'feld'],
        ],
        'valid_names' => [
            ['key' => 'all'],
            ['key' => 'portraits'],
        ],
        'empty' => [],
        'missing_all' => [['key' => 'feld']],
        'duplicate' => [['key' => 'all'], ['key' => 'all']],
        'unsupported' => [['key' => 'all'], ['key' => 'custom']],
        'non_canonical' => [['key' => 'all'], ['key' => 'por traits']],
        'invalid_row' => [['key' => 'all'], 'not-a-row'],
    ];
    $results = [];
    foreach ($cases as $name => $value) {
        $results[$name] = $validate(true, $value);
    }
    echo json_encode($results);
    '''
    results = json.loads(
        subprocess.run(
            ["php", "-r", php],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=True,
        ).stdout
    )

    assert results["valid_field_keys"] is True
    assert results["valid_names"] is True
    expected_errors = {
        "empty": 'Es muss genau ein Filter mit dem Schlüssel "all" vorhanden sein.',
        "missing_all": 'Es muss genau ein Filter mit dem Schlüssel "all" vorhanden sein.',
        "duplicate": "Jeder Filter-Schlüssel darf nur einmal vorkommen.",
        "unsupported": "Filter-Schlüssel müssen all, koerbe, feld oder portraits sein.",
        "non_canonical": "Filter-Schlüssel müssen all, koerbe, feld oder portraits sein.",
        "invalid_row": "Die Filterkonfiguration ist ungültig.",
    }
    for case, message in expected_errors.items():
        assert isinstance(results[case], str)
        assert message in results[case]


def test_doi_get_never_consumes_and_post_requires_a_valid_nonce():
    php = r'''
    define('ABSPATH', __DIR__);
    $consume_calls = 0;
    $method = getenv('TEST_METHOD');
    $nonce = getenv('TEST_NONCE');
    $token = str_repeat('a', 64);
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $method === 'GET' ? ['token' => $token] : [];
    $_POST = $method === 'POST'
        ? ['bioco_doi_token' => $token, 'bioco_doi_nonce' => $nonce]
        : [];

    function get_field($name) {
        $values = [
            'missing_token_message' => 'Missing token',
            'confirmation_prompt' => 'Confirm now',
            'confirmation_button_label' => 'Confirm',
            'success_title' => 'Success',
            'success_text' => 'Confirmed',
            'subscribe_text' => 'Subscribed',
            'visit_text' => '',
            'waiting_list_text' => '',
            'contact_text' => '',
            'error_title' => 'Failed',
            'home_link_label' => 'Home',
        ];
        return $values[$name] ?? '';
    }
    function sanitize_text_field($value) { return trim((string) $value); }
    function wp_unslash($value) { return $value; }
    function nocache_headers() {}
    function __($text, $domain = null) { return $text; }
    function get_permalink($post_id) { return 'https://staging.bioco.ch/newsletter-bestaetigen/'; }
    function home_url($path = '/') { return 'https://staging.bioco.ch' . $path; }
    function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
    function wp_nonce_field($action, $name) { echo '<input name="' . $name . '" />'; }
    function wp_verify_nonce($nonce, $action) {
        return $nonce === 'valid' && $action === 'bioco_doi_confirm_' . str_repeat('a', 64);
    }
    function bioco_forms_doi_confirm_token($token) {
        $GLOBALS['consume_calls']++;
        return ['success' => true, 'form_type' => 'subscribe', 'error' => ''];
    }

    $block = [];
    $post_id = 42;
    $is_preview = false;
    ob_start();
    require 'wordpress/web/app/mu-plugins/bioco-core/blocks/doi-confirm/render.php';
    $html = ob_get_clean();
    echo json_encode(['consume_calls' => $consume_calls, 'html' => $html]);
    '''

    def render(method, nonce=""):
        env = os.environ.copy()
        env.update({"TEST_METHOD": method, "TEST_NONCE": nonce})
        return json.loads(
            subprocess.run(
                ["php", "-r", php],
                cwd=ROOT,
                env=env,
                text=True,
                capture_output=True,
                check=True,
            ).stdout
        )

    get_result = render("GET")
    assert get_result["consume_calls"] == 0
    assert '<form method="post"' in get_result["html"]
    assert 'action="https://staging.bioco.ch/newsletter-bestaetigen/"' in get_result["html"]

    invalid_post = render("POST", "invalid")
    assert invalid_post["consume_calls"] == 0
    assert '<form method="post"' not in invalid_post["html"]

    valid_post = render("POST", "valid")
    assert valid_post["consume_calls"] == 1
    assert "Subscribed" in valid_post["html"]
