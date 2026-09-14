"""#181 asset wiring: the six form blocks ship their view scripts as
pre-registered WordPress handles with the shared lifecycle runtime as a
hard dependency (same pattern as the map blocks), and the bioco-forms
mu-plugin localizes onto those handles WITHOUT pre-enqueuing the
parser-blocking Turnstile script (#181 root review: a blocking api.js tag
delayed DOMContentLoaded so unmounted adapters leaked contact fields via a
native GET; forms-blocking-turnstile-red.json).

The real emitted dependency/order proof in a browser against the actual
local WordPress runtime lives in test_wordpress_forms_lifecycle.py
(opt-in, BIOCO_FORMS_BROWSER_TESTS=1).
"""

import base64
import json
import re
import shutil
import subprocess
from pathlib import Path


REPO = Path(__file__).resolve().parents[1]
CORE = REPO / "wordpress/web/app/mu-plugins/bioco-core"
FORM_SLUGS = (
    "contact-form", "subscribe-form", "visit-day-form",
    "waiting-list-form", "event-signup-form", "membership-form",
)


def test_six_form_blocks_use_registered_handles_with_runtime_dependency():
    core_text = (CORE / "bioco-core.php").read_text(encoding="utf-8")
    runtime_path = CORE / "assets/bioco-forms-lifecycle.js"
    assert runtime_path.is_file()

    assert re.search(
        r"wp_register_script\(\s*'bioco-forms-lifecycle'"
        r".*?assets/bioco-forms-lifecycle\.js.*?\[\]",
        core_text,
        re.DOTALL,
    )
    # The dependency list is conditional (see the resolver tests below): the
    # runtime handle goes into $runtime_deps only when it was registered.
    assert re.search(
        r"\$runtime_deps = \[\];", core_text
    ) and re.search(
        r"\$runtime_deps = \['bioco-forms-lifecycle'\];", core_text
    )
    # The six view handles are registered in one loop with that conditional
    # dependency (map-block pattern).
    assert re.search(
        r"wp_register_script\(\s*'bioco-' \. \$slug \. '-view-script'"
        r".*?blocks/' \. \$slug \. '/view\.js.*?\$runtime_deps",
        core_text,
        re.DOTALL,
    )
    slug_list = re.search(r"\$form_slugs = \[(.*?)\];", core_text, re.DOTALL)
    assert slug_list is not None
    for slug in FORM_SLUGS:
        assert f"'{slug}'" in slug_list.group(1), slug
        metadata = json.loads(
            (CORE / "blocks" / slug / "block.json").read_text(encoding="utf-8")
        )
        assert metadata["viewScript"] == f"bioco-{slug}-view-script", slug


def test_adapters_stay_thin_over_the_shared_runtime():
    runtime_text = (CORE / "assets/bioco-forms-lifecycle.js").read_text(encoding="utf-8")
    assert "window.BiocoForms" in runtime_text
    for slug in FORM_SLUGS:
        adapter = (CORE / "blocks" / slug / "view.js").read_text(encoding="utf-8")
        # The adapter mounts through the shared engine and carries no second
        # submit/fetch/serialization implementation of its own.
        assert "window.BiocoForms" in adapter, slug
        assert "fetch(" not in adapter, slug
        assert "JSON.stringify" not in adapter, slug


def test_localize_block_targets_registered_handle_and_never_pre_enqueues_turnstile():
    """The real bioco_forms_localize_block() contract: localization lands on
    the pre-registered view-script handle; the Turnstile script is NOT
    enqueued by PHP (the shared runtime owns loading/retry)."""
    php = r"""
define('ABSPATH', __DIR__);
$GLOBALS['BIOCO_ENQUEUED'] = [];
$GLOBALS['BIOCO_LOCALIZED'] = [];
function add_action($hook, $callback) {}
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function wp_enqueue_script(...$args) { $GLOBALS['BIOCO_ENQUEUED'][] = $args; }
function wp_enqueue_style(...$args) {}
function wp_localize_script($handle, $object, $values) {
    $GLOBALS['BIOCO_LOCALIZED'][$object] = [$handle, $values];
}
function rest_url($path = '') { return 'https://forms.test/wp-json/' . $path; }
function esc_url_raw($value) { return (string) $value; }
function wp_get_environment_type() { return 'staging'; }
putenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY=test-site-key');
putenv('TURNSTILE_SECRET_KEY=test-secret-only-server-side');
require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';
bioco_forms_localize_block('bioco/contact-form', 'biocoContactFormConfig', 'contact');
echo base64_encode(json_encode([
    'enqueued' => $GLOBALS['BIOCO_ENQUEUED'],
    'localized' => $GLOBALS['BIOCO_LOCALIZED'],
]));
"""
    result = subprocess.run(
        ["php", "-r", php],
        cwd=REPO,
        text=True,
        capture_output=True,
        check=False,
    )
    assert result.returncode == 0, result.stderr
    out = json.loads(base64.b64decode(result.stdout))

    # No parallel Turnstile pre-enqueue: a parser-blocking api.js tag delays
    # DOMContentLoaded and left the pre-#181 adapters unmounted (RED).
    assert out["enqueued"] == []

    handle, values = out["localized"]["biocoContactFormConfig"]
    assert handle == "bioco-contact-form-view-script"
    assert values["restUrl"] == "https://forms.test/wp-json/bioco/v1/contact"
    assert values["turnstileSiteKey"] == "test-site-key"


def test_localize_block_reports_empty_site_key_when_unconfigured():
    php = r"""
define('ABSPATH', __DIR__);
function add_action($hook, $callback) {}
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function wp_enqueue_script(...$args) {}
function wp_localize_script($handle, $object, $values) {
    $GLOBALS['LOCALIZED'][$object] = [$handle, $values];
}
function rest_url($path = '') { return 'https://forms.test/wp-json/' . $path; }
function esc_url_raw($value) { return (string) $value; }
function wp_get_environment_type() { return 'production'; }
putenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY=');
putenv('TURNSTILE_SECRET_KEY=');
require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';
bioco_forms_localize_block('bioco/membership-form', 'biocoMembershipFormConfig', 'membership');
echo base64_encode(json_encode($GLOBALS['LOCALIZED']));
"""
    result = subprocess.run(
        ["php", "-r", php],
        cwd=REPO,
        text=True,
        capture_output=True,
        check=False,
    )
    assert result.returncode == 0, result.stderr
    out = json.loads(base64.b64decode(result.stdout))
    handle, values = out["biocoMembershipFormConfig"]
    assert handle == "bioco-membership-form-view-script"
    # Without a configured key the browser gets an empty site key: the
    # adapter skips captcha rendering entirely (no key = no captcha gate on
    # the client; the server still fails closed on every POST).
    assert values["turnstileSiteKey"] == ""


_FORM_ASSETS_PHP = r"""
define('ABSPATH', __DIR__);
function add_filter($hook, $callback, $priority = 10) { return true; }
function add_action($hook, $callback, $priority = 10) {
    $GLOBALS['actions'][$hook][] = [$callback];
    return true;
}
function wp_register_script($handle, $src = '', $deps = [], $ver = false, $footer = false) {
    $GLOBALS['registered'][$handle] = ['src' => $src, 'deps' => $deps, 'ver' => $ver];
}
function plugin_dir_url($file) { return 'https://fixture.example/wp-content/mu-plugins/bioco-core/'; }
function __($text, $domain = 'default') { return $text; }
require 'FIXTURE_DIR/bioco-core.php';
// Real WordPress dependency resolution (vendored core classes,
// byte-for-byte upstream, see tests/fixtures/wp-dependencies/ATTRIBUTION.md).
require 'tests/fixtures/wp-dependencies/class-wp-dependency.php';
require 'tests/fixtures/wp-dependencies/class-wp-dependencies.php';

// Execute the real init callback exactly as WordPress would.
foreach ($GLOBALS['actions']['init'] ?? [] as [$callback]) {
    if (is_string($callback) && $callback === 'bioco_core_register_form_assets') {
        call_user_func($callback);
    }
}

// Real WordPress dependency resolution (vendored core classes, byte-for-byte
// upstream, see tests/fixtures/wp-dependencies/ATTRIBUTION.md), as
// wp_print_scripts() does it for the handles the block render and the
// dynamic-marker seam enqueue.
$scripts = new WP_Dependencies();
foreach ($GLOBALS['registered'] as $handle => $meta) {
    $scripts->add($handle, $meta['src'], $meta['deps'], $meta['ver']);
}
foreach ([
    'bioco-contact-form-view-script',
    'bioco-subscribe-form-view-script',
    'bioco-visit-day-form-view-script',
    'bioco-waiting-list-form-view-script',
    'bioco-event-signup-form-view-script',
    'bioco-membership-form-view-script',
] as $handle) {
    if ($scripts->query($handle)) {
        $scripts->enqueue($handle);
    }
}
$scripts->all_deps($scripts->queue);

echo base64_encode(json_encode([
    'resolved' => $scripts->to_do,
    'deps' => array_map(
        static fn(array $meta): array => $meta['deps'],
        $GLOBALS['registered']
    ),
]));
"""


def _resolve_form_assets(core_dir):
    """Runs the real bioco-core registration + the real vendored WordPress
    dependency resolver against the given bioco-core directory."""
    php = _FORM_ASSETS_PHP.replace(
        "FIXTURE_DIR", str(core_dir).replace("\\", "/")
    )
    result = subprocess.run(
        ["php", "-r", php],
        cwd=REPO,
        text=True,
        capture_output=True,
        check=False,
    )
    assert result.returncode == 0, result.stderr
    return json.loads(base64.b64decode(result.stdout))


def _form_core_fixture_copy(missing_runtime=False):
    """Temporary copy of bioco-core (red evidence lives outside wordpress/).
    missing_runtime=True removes assets/bioco-forms-lifecycle.js to model the
    deployed runtime file being absent."""
    fixture = Path(subprocess.run(
        ["mktemp", "-d", "/tmp/bioco-forms-core.XXXXXX"],
        text=True, capture_output=True, check=True,
    ).stdout.strip())
    shutil.copytree(
        CORE, fixture / "bioco-core",
        ignore=shutil.ignore_patterns("__pycache__"),
    )
    runtime = fixture / "bioco-core/assets/bioco-forms-lifecycle.js"
    if missing_runtime:
        runtime.unlink()
    return fixture


def test_resolver_prints_runtime_before_every_adapter():
    resolved = _resolve_form_assets(CORE)["resolved"]
    assert resolved[0] == "bioco-forms-lifecycle"
    assert resolved[1:] == [
        "bioco-contact-form-view-script",
        "bioco-subscribe-form-view-script",
        "bioco-visit-day-form-view-script",
        "bioco-waiting-list-form-view-script",
        "bioco-event-signup-form-view-script",
        "bioco-membership-form-view-script",
    ], resolved


def test_resolver_prints_adapters_even_when_runtime_file_is_missing():
    """CodeRabbit blocker, resolver half: with the runtime FILE missing, the
    conditional dependency must not let WordPress silently omit the six
    adapters — they are the only owners of the no-runtime fail-closed
    listeners, so they must still print (without the dependency)."""
    fixture = _form_core_fixture_copy(missing_runtime=True)
    try:
        out = _resolve_form_assets(fixture / "bioco-core")
        resolved = out["resolved"]
        assert "bioco-forms-lifecycle" not in resolved
        assert resolved == [
            "bioco-contact-form-view-script",
            "bioco-subscribe-form-view-script",
            "bioco-visit-day-form-view-script",
            "bioco-waiting-list-form-view-script",
            "bioco-event-signup-form-view-script",
            "bioco-membership-form-view-script",
        ], resolved
        for slug in FORM_SLUGS:
            assert out["deps"][f"bioco-{slug}-view-script"] == [], slug
    finally:
        shutil.rmtree(fixture, ignore_errors=True)
