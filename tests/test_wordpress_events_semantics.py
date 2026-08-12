"""Events/Schnuppertage correctness for the WordPress port.

Four contracts, all exercised against the real PHP with WP stubs:

  1. import preserves the SOURCE wall-clock (14:00 stays 14:00) regardless of
     the WordPress site timezone — event_date is a local naive ACF datetime,
     not an instant;
  2. bioco_query_events() decides upcoming/past by DATE (current time), while
     still honouring an explicit manual event_status=past on a future event;
  3. bioco_event_date_parts() reads the naive stored value in wp_timezone()
     with no shift;
  4. the events-feed block asks for general events only, schnuppertage for
     event_type=schnuppertag.
"""

import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"

WP_STUBS = r'''
date_default_timezone_set('UTC');
define('ABSPATH', __DIR__);
function wp_timezone() { return new DateTimeZone(getenv('BIOCO_TEST_TZ') ?: 'UTC'); }
function wp_date($format, $ts = null, $tz = null) {
    $dt = new DateTimeImmutable('@' . $ts);
    return $dt->setTimezone($tz ?: wp_timezone())->format($format);
}
function date_i18n($format, $ts = null) { return wp_date($format, $ts); }
function current_time($type) {
    $now = new DateTimeImmutable(getenv('BIOCO_TEST_NOW') ?: 'now', wp_timezone());
    return $type === 'timestamp' ? $now->getTimestamp() : $now->format('Y-m-d H:i:s');
}
'''


def run_php(php, env_extra=None):
    return run_php_raw(WP_STUBS + php, env_extra)


def run_php_raw(php, env_extra=None):
    """Run PHP verbatim, without the WP stub preamble.

    Needed for the CLI test: it declares a namespaced stub for
    WP_CLI\\Utils\\get_flag_value(), and a `namespace` statement may not be
    preceded by any other code.
    """
    import os

    env = dict(os.environ)
    env.update(env_extra or {})
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
        env=env,
    )
    return json.loads(result.stdout)


# --- 1. import: source wall-clock is preserved -----------------------------

IMPORT_PHP = r'''
function wp_remote_get() {}
function is_wp_error() { return false; }
class WP_Error { public function __construct() {} }
require 'wordpress/web/app/mu-plugins/bioco-import/includes/collections.php';
echo json_encode(bioco_import_event_field_plan([
    'startDate' => '2026-05-29T14:00:00+02:00',
    'eventType' => 'schnuppertag',
    'status' => 'upcoming',
]));
'''


def test_import_keeps_source_wall_clock_under_utc_site_timezone():
    plan = run_php(IMPORT_PHP, {"BIOCO_TEST_TZ": "UTC"})
    assert plan["event_date"] == "2026-05-29 14:00:00"


def test_import_keeps_source_wall_clock_under_zurich_site_timezone():
    plan = run_php(IMPORT_PHP, {"BIOCO_TEST_TZ": "Europe/Zurich"})
    assert plan["event_date"] == "2026-05-29 14:00:00"
    assert plan["event_type"] == "schnuppertag"


# --- 2. query args: current by date, manual past still wins ----------------

QUERY_PHP = r'''
class WP_Query { public $args; public function __construct($args) { $this->args = $args; } }
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
echo json_encode([
    'upcoming' => bioco_query_events('upcoming', 3)->args,
    'past' => bioco_query_events('past', 4, 'general')->args,
    'schnuppertag' => bioco_query_events('upcoming', 3, 'schnuppertag')->args,
]);
'''

NOW = "2026-05-29 09:00:00"


def query_args():
    return run_php(
        QUERY_PHP, {"BIOCO_TEST_TZ": "Europe/Zurich", "BIOCO_TEST_NOW": NOW}
    )


def find_clauses(node, key):
    """Every leaf clause for `key`, at any nesting depth.

    PHP arrays with a 'relation' string key json_encode to objects, so a
    meta_query arrives as either a list or a dict depending on nesting.
    """
    if isinstance(node, dict):
        if node.get("key") == key:
            return [node]
        node = list(node.values())
    if not isinstance(node, list):
        return []
    return [c for child in node for c in find_clauses(child, key)]


def test_upcoming_is_filtered_by_current_date_not_only_by_status():
    upcoming = query_args()["upcoming"]["meta_query"]
    date_clauses = find_clauses(upcoming, "event_date")

    assert date_clauses, "upcoming must constrain event_date, not just event_status"
    assert any(
        c["compare"] == ">=" and c["value"] == NOW and c.get("type") == "DATETIME"
        for c in date_clauses
    ), date_clauses


def test_upcoming_still_excludes_an_explicitly_past_future_event():
    upcoming = query_args()["upcoming"]["meta_query"]
    status_clauses = find_clauses(upcoming, "event_status")

    assert any(
        c["compare"] == "!=" and c["value"] == "past" for c in status_clauses
    ), status_clauses
    assert upcoming["relation"] == "AND"


def test_past_matches_elapsed_dates_or_an_explicit_past_status():
    past = query_args()["past"]["meta_query"]

    assert any(
        c["compare"] == "<" and c["value"] == NOW and c.get("type") == "DATETIME"
        for c in find_clauses(past, "event_date")
    ), past
    assert any(
        c["compare"] == "=" and c["value"] == "past"
        for c in find_clauses(past, "event_status")
    ), past
    # elapsed OR explicitly-past — an OR relation must exist somewhere.
    assert "OR" in json.dumps(past)


def test_event_type_filter_is_passed_through():
    args = query_args()
    assert any(
        c["value"] == "schnuppertag" and c["compare"] == "="
        for c in find_clauses(args["schnuppertag"]["meta_query"], "event_type")
    )
    # 'general' also accepts events imported before event_type existed.
    general = find_clauses(args["past"]["meta_query"], "event_type")
    assert any(c["compare"] == "=" and c["value"] == "general" for c in general)
    assert any(c["compare"] == "NOT EXISTS" for c in general)


def test_ordering_and_post_type_are_unchanged():
    args = query_args()
    assert args["upcoming"]["post_type"] == "event"
    assert args["upcoming"]["order"] == "ASC"
    assert args["past"]["order"] == "DESC"
    assert args["upcoming"]["meta_key"] == "event_date"


# --- 3. display: naive stored value read in wp_timezone(), no shift --------

DISPLAY_PHP = r'''
function get_field($field, $post_id = null) { return '2026-05-29 14:00:00'; }
class WP_Query { public function __construct($args) {} }
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
echo json_encode(bioco_event_date_parts(1));
'''


def test_event_date_display_does_not_shift_the_stored_wall_clock():
    for tz in ("UTC", "Europe/Zurich", "America/New_York"):
        parts = run_php(DISPLAY_PHP, {"BIOCO_TEST_TZ": tz})
        assert parts["dateLabel"] == "29.05.2026", tz
        assert parts["timeLabel"] == "14:00 Uhr", tz


# --- 4. render call sites: correct type filters ----------------------------


def test_events_feed_block_requests_general_events_only():
    render = (CORE / "blocks/events-feed/render.php").read_text()
    calls = [
        line.strip()
        for line in render.splitlines()
        if "bioco_query_events(" in line
    ]

    assert len(calls) == 2, calls
    assert "'upcoming', $limit, 'general'" in calls[0]
    assert "'past', 4" in calls[1]
    assert "'general'" not in calls[1]


def test_schnuppertage_block_still_requests_schnuppertag_events():
    render = (CORE / "blocks/schnuppertage/render.php").read_text()
    assert "bioco_query_events('upcoming', $limit, 'schnuppertag')" in render


def test_site_wiring_sets_zurich_timezone_idempotently():
    php = r'''
    $options = ['timezone_string' => 'UTC'];
    $updates = [];
    function get_option($key) { global $options; return $options[$key] ?? null; }
    function update_option($key, $value) { global $options, $updates; $options[$key] = $value; $updates[] = [$key, $value]; }
    function bioco_import_report_row(&$report, $page, $section, $field, $status, $detail) { $report[] = compact('page', 'section', 'field', 'status', 'detail'); }
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/site-wiring.php';
    $report = [];
    bioco_import_wire_timezone('apply', $report);
    bioco_import_wire_timezone('apply', $report);
    echo json_encode(['options' => $options, 'updates' => $updates, 'report' => $report]);
    '''
    payload = run_php(php)

    assert payload["options"]["timezone_string"] == "Europe/Zurich"
    assert payload["updates"] == [["timezone_string", "Europe/Zurich"]]
    assert [row["status"] for row in payload["report"]] == ["update", "ok-equal"]


# --- 5. --collections-only: pages and site wiring are structurally unreachable

CLI_PHP = r'''
namespace WP_CLI\Utils {
    function get_flag_value($args, $key, $default = null) {
        return isset($args[$key]) ? $args[$key] : $default;
    }
}
namespace {
define('ABSPATH', __DIR__);
class WP_CLI {
    public static function log($m) {}
    public static function success($m) {}
    public static function error($m) { throw new RuntimeException($m); }
}
define('BIOCO_IMPORT_DEFAULT_SEED_DIR', '/seed');
function acf_get_field_group() {}
function acf_get_fields() {}
function bioco_import_load_seeds($dir, $only) { return ['home' => []]; }
function bioco_import_report_new() { return []; }
function bioco_import_report_print_cli() {}
function bioco_import_report_write_html() { return ''; }
function bioco_import_report_write_log() { return ''; }
function bioco_import_report_has_failures() { return false; }
function bioco_import_run() { $GLOBALS['calls'][] = 'pages'; }
function bioco_import_run_collections() { $GLOBALS['calls'][] = 'collections'; }
function bioco_import_run_site_wiring() { $GLOBALS['calls'][] = 'site-wiring'; }
require 'wordpress/web/app/mu-plugins/bioco-import/includes/cli.php';
$GLOBALS['calls'] = [];
$cmd = new Bioco_Import_CLI_Command();
$cmd->import([], json_decode('ARGS', true));
echo json_encode($GLOBALS['calls']);
}
'''


def run_cli(assoc_args):
    return run_php_raw(CLI_PHP.replace("ARGS", json.dumps(assoc_args)))


def test_collections_only_cli_never_reaches_pages_or_site_wiring():
    assert run_cli({"apply": True, "collections-only": True}) == ["collections"]


def test_without_the_flag_the_full_import_still_runs():
    assert run_cli({"apply": True}) == ["pages", "collections", "site-wiring"]


# --- 6. --collections-only --force: compare-before-write -------------------

EVENT_PHP = r'''
function wp_remote_get() {}
function is_wp_error() { return false; }
class WP_Error { public function __construct() {} }
function sanitize_title($t) { return 'sommerfest'; }
function wp_slash($v) { return $v; }
function bioco_import_excerpt($v) { return $v; }
function bioco_import_report_row(&$report, $page, $section, $field, $status, $detail) {
    $report[] = ['field' => $field, 'status' => $status, 'detail' => $detail];
}
function bioco_import_resolve_attachment_for_url($url, $mode) { return 77; }
function get_posts($args) {
    $p = new stdClass();
    $p->ID = 42;
    $p->post_title = 'Sommerfest';
    $p->post_content = '<p>Fest im Garten.</p>';
    return [$p];
}
function wp_update_post($args) { $GLOBALS['post_writes'][] = $args; return 42; }
function get_field($field, $postId = null) {
    $stored = [
        'event_date' => '2026-08-14 10:00:00',
        'event_status' => 'upcoming',
        'event_type' => 'general',
        'event_summary' => 'Fest im Garten.',
        'card_image' => 77,
    ];
    return isset($stored[$field]) ? $stored[$field] : null;
}
function update_field($field, $value, $postId) { $GLOBALS['field_writes'][] = [$field, $value]; }
require 'wordpress/web/app/mu-plugins/bioco-import/includes/collections.php';
$GLOBALS['post_writes'] = [];
$GLOBALS['field_writes'] = [];
$report = [];
bioco_import_import_event_item([
    'title' => 'Sommerfest',
    'fullDescription' => '<p>Fest im Garten.</p>',
    'description' => 'Fest im Garten.',
    'startDate' => '2026-08-15T18:00:00+02:00',
    'status' => 'upcoming',
    'eventType' => 'general',
    'cardImage' => 'https://cms.bioco.ch/fest.jpg',
], 'apply', true, $report);
echo json_encode([
    'post_writes' => $GLOBALS['post_writes'],
    'field_writes' => $GLOBALS['field_writes'],
    'report' => $report,
]);
'''


def existing_event_force_run():
    return run_php(EVENT_PHP, {"BIOCO_TEST_TZ": "Europe/Zurich"})


def test_force_writes_only_the_shifted_event_date():
    payload = existing_event_force_run()

    assert payload["field_writes"] == [["event_date", "2026-08-15 18:00:00"]]


def test_force_does_not_rewrite_an_unchanged_title_or_body():
    assert existing_event_force_run()["post_writes"] == []


def test_force_preserves_card_image_idempotency():
    rows = existing_event_force_run()["report"]
    card = [r for r in rows if r["field"] == "card_image"]

    assert [r["status"] for r in card] == ["ok-equal"], card


def test_force_reports_equal_fields_truthfully():
    rows = existing_event_force_run()["report"]
    by_field = {r["field"]: r["status"] for r in rows if r["field"]}

    assert by_field["event_date"] == "update"
    for equal_field in ("event_status", "event_type", "event_summary"):
        assert by_field[equal_field] == "ok-equal", (equal_field, rows)
    # The post-level row must not claim an update that never happened.
    post_rows = [r for r in rows if not r["field"]]
    assert [r["status"] for r in post_rows] == ["ok-equal"], post_rows


# --- 7. the second --collections-only --force run is a global no-op --------

# The state left behind by the first repair run: event_date has already been
# shifted to the planned value, and every other field/post column already
# matches. The one remaining difference is cosmetic — the stored meta is
# exactly the source HTML, but ACF's formatted read returns it with a trailing
# "\n" (wpautop-style formatting). A second --force run must therefore write
# nothing at all, not merely skip the summary.
NEWLINE_EVENT_PHP = EVENT_PHP.replace(
    "        'event_date' => '2026-08-14 10:00:00',",
    "        'event_date' => '2026-08-15 18:00:00',",
).replace(
    "        'event_summary' => 'Fest im Garten.',",
    "        'event_summary' => \"<p>Fest im Garten.</p>\\n\",",
).replace(
    "function update_field($field, $value, $postId) {",
    """function get_post_meta($postId, $field = '', $single = false) {
    $raw = ['event_summary' => '<p>Fest im Garten.</p>'];
    if (!isset($raw[$field])) return $single ? '' : [];
    return $single ? $raw[$field] : [$raw[$field]];
}
function update_field($field, $value, $postId) {""",
).replace(
    "    'description' => 'Fest im Garten.',",
    "    'description' => '<p>Fest im Garten.</p>',",
)


def newline_event_force_run():
    return run_php(NEWLINE_EVENT_PHP, {"BIOCO_TEST_TZ": "Europe/Zurich"})


def test_trailing_newline_from_formatted_get_field_is_not_rewritten():
    payload = newline_event_force_run()
    written = [f for f, _ in payload["field_writes"]]

    assert "event_summary" not in written, payload["field_writes"]


def test_trailing_newline_field_is_reported_as_equal():
    rows = newline_event_force_run()["report"]
    by_field = {r["field"]: r["status"] for r in rows if r["field"]}

    assert by_field["event_summary"] == "ok-equal", rows


def test_second_force_run_writes_nothing_at_all():
    payload = newline_event_force_run()

    assert payload["field_writes"] == [], payload["field_writes"]
    assert payload["post_writes"] == [], payload["post_writes"]


def test_second_force_run_reports_no_updates():
    rows = newline_event_force_run()["report"]

    assert [r for r in rows if r["status"] == "update"] == [], rows
    assert {r["status"] for r in rows} == {"ok-equal"}, rows


# --- 8. first run with raw meta present: the fallback cannot swallow a write -

# Section 6 runs without get_post_meta(), so the raw-meta fallback in
# bioco_import_acf_stored_value_equals() short-circuits before it is reached.
# On a real site the function always exists, so exercise the same first-run
# state with raw meta defined: event_date's raw value still differs from the
# planned value, so it must still be written exactly once.
RAW_META_EVENT_PHP = EVENT_PHP.replace(
    "function update_field($field, $value, $postId) {",
    """function get_post_meta($postId, $field = '', $single = false) {
    $raw = [
        'event_date' => '2026-08-14 10:00:00',
        'event_status' => 'upcoming',
        'event_type' => 'general',
        'event_summary' => 'Fest im Garten.',
        'card_image' => '77',
    ];
    if (!isset($raw[$field])) return $single ? '' : [];
    return $single ? $raw[$field] : [$raw[$field]];
}
function update_field($field, $value, $postId) {""",
)


def raw_meta_event_force_run():
    return run_php(RAW_META_EVENT_PHP, {"BIOCO_TEST_TZ": "Europe/Zurich"})


def test_raw_meta_fallback_still_writes_the_shifted_event_date():
    payload = raw_meta_event_force_run()

    assert payload["field_writes"] == [["event_date", "2026-08-15 18:00:00"]]
    assert payload["post_writes"] == []


def test_raw_meta_fallback_reports_the_event_date_update():
    rows = raw_meta_event_force_run()["report"]
    by_field = {r["field"]: r["status"] for r in rows if r["field"]}

    assert by_field["event_date"] == "update"
    for equal_field in ("event_status", "event_type", "event_summary", "card_image"):
        assert by_field[equal_field] == "ok-equal", (equal_field, rows)
