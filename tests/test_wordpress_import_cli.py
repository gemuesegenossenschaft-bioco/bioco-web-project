import base64
import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
SEED_DIR = ROOT / "wordpress/content-seed"

WP_SERIALIZER_STUB = r'''
function _wp_serialize_block($block) {
    $name = $block['blockName'];
    $attrs = empty($block['attrs']) ? '' : ' ' . json_encode($block['attrs'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $open = '<!-- wp:' . $name . $attrs . ' -->';

    $content = '';
    $child_idx = 0;
    $inner_content = $block['innerContent'] ?? [];
    foreach ($inner_content as $piece) {
        if ($piece === null) {
            if ($child_idx < count($block['innerBlocks'] ?? [])) {
                $content .= _wp_serialize_block($block['innerBlocks'][$child_idx]);
                $child_idx++;
            }
        } else {
            $content .= $piece;
        }
    }

    if ($content === '') {
        return '<!-- wp:' . $name . (empty($attrs) ? ' ' : $attrs . ' ') . '/-->';
    }
    return $open . $content . '<!-- /wp:' . $name . ' -->';
}
function serialize_blocks($blocks) {
    $out = '';
    foreach ($blocks as $b) {
        $out .= _wp_serialize_block($b);
    }
    return $out;
}
function serialize_block($block) {
    return _wp_serialize_block($block);
}
'''

# The harness boots the REAL WP-CLI command class (includes/cli.php) and the
# REAL importer includes against a stubbed WordPress runtime. It reports the
# full write journal so tests can prove that non-writing modes write nothing.
HARNESS = r'''<?php
namespace WP_CLI\Utils {
    function get_flag_value($assoc_args, $flag, $default = null) {
        return array_key_exists($flag, $assoc_args) ? $assoc_args[$flag] : $default;
    }
}
namespace {
    define('ABSPATH', __DIR__ . '/');
    error_reporting(E_ALL);
    $scenario = json_decode(file_get_contents($argv[1]), true);
    $GLOBALS['BIOCO_RENDER_HTML'] = $scenario['render_html'] ?? '<p>Abschnitt mit Inhalt gerendert.</p>';
    $GLOBALS['BIOCO_RENDER_EXCEPTION_FOR'] = $scenario['render_exception_for'] ?? '';
    ini_set('error_log', $scenario['error_log']);
    define('WP_CONTENT_DIR', $scenario['wp_content_dir']);
    define('BIOCO_IMPORT_DEFAULT_SEED_DIR', $scenario['seed_dir']);

    class WP_CLI {
        public static $logs = [];
        public static $success = [];
        public static $errors = [];
        public static function log($m) { self::$logs[] = (string) $m; }
        public static function success($m) { self::$success[] = (string) $m; }
        public static function error($m, $exit = true) {
            self::$errors[] = (string) $m;
            if ($exit) exit(1);
        }
    }

    function is_wp_error($value) { return false; }
    function esc_html($value) { return (string) $value; }
    function wp_slash($value) { return $value; }
    function sanitize_title($title) {
        $slug = strtolower(trim((string) $title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
    function current_time($format) { return date('Y-m-d H:i:s'); }

    $GLOBALS['BIOCO_TEST_PAGES'] = [];        // slug => content string
    $GLOBALS['BIOCO_POSTS'] = [];             // id => stdClass (pages/events/groups)
    $GLOBALS['BIOCO_NEXT_ID'] = 100;
    $GLOBALS['BIOCO_META'] = [];              // post meta; the ACF stub writes into the same store
    $GLOBALS['BIOCO_JOURNAL_POST_INSERT'] = [];
    $GLOBALS['BIOCO_JOURNAL_POST_UPDATE'] = [];
    $GLOBALS['BIOCO_JOURNAL_META'] = [];
    $GLOBALS['BIOCO_JOURNAL_ACF'] = [];
    $GLOBALS['BIOCO_JOURNAL_OPTION'] = [];
    $GLOBALS['BIOCO_JOURNAL_SIDELOAD'] = [];
    $GLOBALS['BIOCO_JOURNAL_MENU'] = [];
    $GLOBALS['BIOCO_JOURNAL_THEME_MOD'] = [];
    $GLOBALS['BIOCO_JOURNAL_REWRITE'] = [];
    $GLOBALS['BIOCO_URL_TO_ATTACHMENT'] = [];
    $GLOBALS['BIOCO_ATTACHMENT_TO_URL'] = [];

    function get_posts($args) {
        $type = $args['post_type'] ?? '';
        $name = $args['name'] ?? null;
        if (isset($args['meta_key']) && $args['meta_key'] === '_bioco_import_source_url') {
            $url = (string) $args['meta_value'];
            if (isset($GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$url])) {
                return [(object) ['ID' => $GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$url], 'post_type' => 'attachment']];
            }
            // Documents are tagged via update_post_meta after sideload; the
            // meta store is the source of truth for their reuse on re-run.
            foreach ($GLOBALS['BIOCO_META'] as $postId => $meta) {
                if (($meta['_bioco_import_source_url'] ?? null) === $url) {
                    return [(object) ['ID' => $postId, 'post_type' => 'attachment']];
                }
            }
            return [];
        }
        $matches = [];
        foreach ($GLOBALS['BIOCO_POSTS'] as $post) {
            if ($post->post_type !== $type) continue;
            if ($name !== null && $post->post_name !== $name) continue;
            $matches[] = $post;
        }
        return $matches;
    }

    function get_post($id) {
        return $GLOBALS['BIOCO_POSTS'][(int) $id] ?? null;
    }

    function wp_insert_post($data, $wp_error = false) {
        $id = $GLOBALS['BIOCO_NEXT_ID']++;
        $post = (object) [
            'ID' => $id,
            'post_type' => $data['post_type'],
            'post_name' => $data['post_name'] ?? '',
            'post_title' => $data['post_title'] ?? '',
            'post_content' => $data['post_content'] ?? '',
            'post_status' => $data['post_status'] ?? 'publish',
        ];
        $GLOBALS['BIOCO_POSTS'][$id] = $post;
        $GLOBALS['BIOCO_JOURNAL_POST_INSERT'][] = ['id' => $id, 'data' => $data];
        return $id;
    }

    function wp_update_post($data, $wp_error = false) {
        $post = $GLOBALS['BIOCO_POSTS'][(int) $data['ID']] ?? null;
        if ($post) {
            foreach (['post_title', 'post_content', 'post_status'] as $key) {
                if (isset($data[$key])) $post->{$key} = $data[$key];
            }
            $GLOBALS['BIOCO_JOURNAL_POST_UPDATE'][] = $data;
        }
        return $data['ID'];
    }

    function wp_delete_post($id, $force) { unset($GLOBALS['BIOCO_POSTS'][(int) $id]); return $id; }

    function wp_get_attachment_image_url($id, $size = 'full') {
        return $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] ?? false;
    }

    function media_sideload_image($url, $post_id, $description, $return) {
        $id = $GLOBALS['BIOCO_NEXT_ID']++;
        $GLOBALS['BIOCO_URL_TO_ATTACHMENT'][$url] = $id;
        $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] = $url;
        $GLOBALS['BIOCO_JOURNAL_SIDELOAD'][] = $url;
        return $id;
    }

    function wp_tempnam($name) { return tempnam(sys_get_temp_dir(), 'bioco-doc-'); }
    function media_handle_sideload($fileArray, $postId) {
        if (!is_file($fileArray['tmp_name'])) return (object) ['error' => ['no-file']];
        $id = $GLOBALS['BIOCO_NEXT_ID']++;
        $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] = 'https://staging.bioco.test/wp-content/uploads/' . $fileArray['name'];
        $GLOBALS['BIOCO_JOURNAL_SIDELOAD'][] = $fileArray['name'];
        return $id;
    }
    function wp_get_attachment_url($id) { return $GLOBALS['BIOCO_ATTACHMENT_TO_URL'][$id] ?? false; }
    function wp_delete_file($path) { return @unlink($path); }

    function get_post_meta($post_id, $key, $single = true) {
        if (!$single) return isset($GLOBALS['BIOCO_META'][$post_id][$key]) ? [$GLOBALS['BIOCO_META'][$post_id][$key]] : [];
        return $GLOBALS['BIOCO_META'][$post_id][$key] ?? '';
    }

    function update_post_meta($post_id, $key, $value) {
        $GLOBALS['BIOCO_META'][$post_id][$key] = $value;
        $GLOBALS['BIOCO_JOURNAL_META'][] = compact('post_id', 'key', 'value');
        return true;
    }

    function get_field($field, $post_id) {
        return $GLOBALS['BIOCO_META'][$post_id][$field] ?? null;
    }

    function update_field($field, $value, $post_id) {
        $GLOBALS['BIOCO_META'][$post_id][$field] = $value;
        $GLOBALS['BIOCO_JOURNAL_ACF'][] = compact('field', 'post_id', 'value');
        return true;
    }

    $GLOBALS['BIOCO_OPTIONS'] = $scenario['options'] ?? [];
    function get_option($key, $default = false) { return $GLOBALS['BIOCO_OPTIONS'][$key] ?? $default; }
    function update_option($key, $value) { $GLOBALS['BIOCO_OPTIONS'][$key] = $value; $GLOBALS['BIOCO_JOURNAL_OPTION'][] = compact('key', 'value'); return true; }

    function get_registered_nav_menus() { return ['primary' => 'Hauptnavigation']; }
    function wp_mkdir_p($dir) { return is_dir($dir) || mkdir((string) $dir, 0755, true); }
    function wp_get_nav_menu_object($name) {
        if ($name === 'Hauptnavigation') return (object) ['term_id' => 5, 'name' => $name];
        return null;
    }
    function wp_get_nav_menu_items($menu_id) { return []; }
    function wp_create_nav_menu($name) { $GLOBALS['BIOCO_JOURNAL_MENU'][] = ['create' => $name]; return 5; }
    function wp_update_nav_menu_item($menu_id, $item_id, $args) { $GLOBALS['BIOCO_JOURNAL_MENU'][] = ['item' => $item_id, 'args' => $args]; return $item_id; }
    function get_theme_mod($key, $default = null) { return $key === 'nav_menu_locations' ? ['primary' => 5] : $default; }
    function set_theme_mod($key, $value) { $GLOBALS['BIOCO_JOURNAL_THEME_MOD'][] = compact('key', 'value'); }
    function flush_rewrite_rules() { $GLOBALS['BIOCO_JOURNAL_REWRITE'][] = true; }

    WP_SERIALIZER

    // parse_blocks() is WordPress core; the REAL WP 7.1 parser from the
    // attributed fixture backs the runtime gate (no reimplementation).
    require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
    function parse_blocks($content) {
        return (new WP_Block_Parser())->parse((string) $content);
    }

    $GLOBALS['BIOCO_RENDER_CALLS'] = [];

    // render_block() is an external boundary: the harness returns the
    // scenario-provided render_html verbatim and records every call.
    function render_block($block) {
        $GLOBALS['BIOCO_RENDER_CALLS'][] = (string) ($block['blockName'] ?? 'freeform');
        if (!empty($GLOBALS['BIOCO_RENDER_EXCEPTION_FOR']) && (string) ($block['blockName'] ?? '') === $GLOBALS['BIOCO_RENDER_EXCEPTION_FOR']) {
            throw new RuntimeException('Divi-Renderer-Absturz');
        }
        return (string) ($GLOBALS['BIOCO_RENDER_HTML'] ?? '<p>Abschnitt mit Inhalt gerendert.</p>');
    }

    require 'wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/report.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/section-map.php';
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/documents.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/pages.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/verify.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/collections.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/site-wiring.php';
    require 'wordpress/web/app/mu-plugins/bioco-import/includes/cli.php';

    // Compose the initial WordPress state exactly like a real import would:
    // each seed becomes a real page post with its own ID.
    $seeds = bioco_import_load_seeds($scenario['seed_dir'], $scenario['only'] ?? []);
    $pageIds = [];
    foreach ($seeds as $seed) {
        // First compose the UNEDITED seed content: the reference the seed
        // gate and forced imports are measured against.
        $seedReport = bioco_import_report_new();
        [$seedContent] = bioco_import_build_desired_content($seed, 'apply', $seedReport);
        $GLOBALS['BIOCO_SEED_CONTENT'][$seed['slug']] = $seedContent;

        if (($seed['slug'] ?? '') === 'kontakt' && !empty($scenario['edit_kontakt'])) {
            $seed['sections'][0]['section_text'] = str_replace(
                'Hast du Fragen zu biocò?',
                'Jetzt redaktionell gepflegt:',
                (string) $seed['sections'][0]['section_text']
            );
        }
        $buildReport = bioco_import_report_new();
        [$content, $labels] = bioco_import_build_desired_content($seed, 'apply', $buildReport);
        $id = wp_insert_post([
            'post_type' => 'page',
            'post_name' => $seed['slug'],
            'post_title' => (string) $seed['title'],
            'post_status' => 'publish',
            'post_content' => $content,
        ], true);
        $pageIds[$seed['slug']] = $id;
        // Post-import state: Divi shell metadata + SEO values exactly as the importer would have left them.
        $GLOBALS['BIOCO_META'][$id]['_et_pb_use_builder'] = 'on';
        $GLOBALS['BIOCO_META'][$id]['_et_pb_page_layout'] = 'et_full_width_page';
        foreach (($seed['seo'] ?? []) as $seoKey => $seoValue) {
            $GLOBALS['BIOCO_META'][$id][$seoKey] = (string) $seoValue;
        }
    }
    foreach ($scenario['existing_events'] ?? [] as $event) {
        $id = wp_insert_post([
            'post_type' => 'event',
            'post_name' => sanitize_title($event['title']),
            'post_title' => $event['post_title'] ?? $event['title'],
            'post_status' => 'publish',
            'post_content' => $event['content'] ?? '',
        ], true);
        foreach (($event['fields'] ?? []) as $field => $value) {
            update_field($field, $value, $id);
        }
    }
    foreach ($scenario['existing_groups'] ?? [] as $group) {
        $id = wp_insert_post([
            'post_type' => 'bioco_group',
            'post_name' => sanitize_title($group['title']),
            'post_title' => $group['title'],
            'post_status' => 'publish',
        ], true);
        foreach (($group['fields'] ?? []) as $field => $value) {
            update_field($field, $value, $id);
        }
    }
    if (isset($pageIds['home'])) {
        $GLOBALS['BIOCO_OPTIONS']['show_on_front'] = 'page';
        $GLOBALS['BIOCO_OPTIONS']['page_on_front'] = $pageIds['home'];
    }
    foreach ($scenario['drop_pages'] ?? [] as $slug) {
        if (isset($pageIds[$slug])) unset($GLOBALS['BIOCO_POSTS'][$pageIds[$slug]]);
    }

    // The state build itself uses the same stubs; only writes made by the
    // command under test belong in the journal.
    $GLOBALS['BIOCO_JOURNAL_POST_INSERT'] = [];
    $GLOBALS['BIOCO_JOURNAL_POST_UPDATE'] = [];
    $GLOBALS['BIOCO_JOURNAL_META'] = [];
    $GLOBALS['BIOCO_JOURNAL_ACF'] = [];
    $GLOBALS['BIOCO_JOURNAL_OPTION'] = [];

    $command = new Bioco_Import_CLI_Command();
    $assocArgs = [];
    if (!empty($scenario['events_json'])) {
        $scenario['flags'][] = '--events-json=' . $scenario['events_json'];
    }
    if (!empty($scenario['groups_json'])) {
        $scenario['flags'][] = '--groups-json=' . $scenario['groups_json'];
    }
    foreach ($scenario['flags'] ?? [] as $flag) {
        $flag = ltrim((string) $flag, '-');
        if (str_contains($flag, '=')) {
            [$key, $value] = explode('=', $flag, 2);
            $assocArgs[$key] = $value;
        } else {
            $assocArgs[$flag] = true;
        }
    }

    // Support running the same command multiple times over the SAME state
    // (true idempotency: the second run executes over the first run's output).
    $runs = [];
    $runCount = max(1, (int) ($scenario['runs'] ?? 1));
    register_shutdown_function(function () use ($scenario, &$runs) {
        $pages = [];
        foreach ($GLOBALS['BIOCO_POSTS'] as $post) {
            if ($post->post_type === 'page') $pages[$post->post_name] = $post->post_content;
        }
        $htmlFiles = glob(WP_CONTENT_DIR . '/bioco-import-log/*') ?: [];
        $errorLog = file_exists($scenario['error_log']) ? file_get_contents($scenario['error_log']) : '';
        echo json_encode([
            'logs' => WP_CLI::$logs,
            'success' => WP_CLI::$success,
            'errors' => WP_CLI::$errors,
            'runs' => $runs,
            'journal' => [
                'post_insert' => $GLOBALS['BIOCO_JOURNAL_POST_INSERT'],
                'post_update' => $GLOBALS['BIOCO_JOURNAL_POST_UPDATE'],
                'meta' => $GLOBALS['BIOCO_JOURNAL_META'],
                'acf' => $GLOBALS['BIOCO_JOURNAL_ACF'],
                'option' => $GLOBALS['BIOCO_JOURNAL_OPTION'],
                'sideload' => $GLOBALS['BIOCO_JOURNAL_SIDELOAD'],
                'menu' => $GLOBALS['BIOCO_JOURNAL_MENU'],
                'theme_mod' => $GLOBALS['BIOCO_JOURNAL_THEME_MOD'],
                'rewrite' => $GLOBALS['BIOCO_JOURNAL_REWRITE'],
            ],
            'pages' => $pages,
            'seed_contents' => $GLOBALS['BIOCO_SEED_CONTENT'] ?? [],
            'html_report_files' => $htmlFiles,
            'error_log' => $errorLog,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });
    for ($runIndex = 0; $runIndex < $runCount; $runIndex++) {
        $GLOBALS['BIOCO_JOURNAL_POST_INSERT'] = [];
        $GLOBALS['BIOCO_JOURNAL_POST_UPDATE'] = [];
        $GLOBALS['BIOCO_JOURNAL_META'] = [];
        $GLOBALS['BIOCO_JOURNAL_ACF'] = [];
        $GLOBALS['BIOCO_JOURNAL_OPTION'] = [];
        $GLOBALS['BIOCO_JOURNAL_SIDELOAD'] = [];
        $GLOBALS['BIOCO_JOURNAL_MENU'] = [];
        $GLOBALS['BIOCO_JOURNAL_THEME_MOD'] = [];
        $GLOBALS['BIOCO_JOURNAL_REWRITE'] = [];
        if (($scenario['command'] ?? 'import') === 'verify') {
            $command->verify([], $assocArgs);
        } else {
            $command->import([], $assocArgs);
        }
        $runs[] = [
            'post_insert' => $GLOBALS['BIOCO_JOURNAL_POST_INSERT'],
            'post_update' => $GLOBALS['BIOCO_JOURNAL_POST_UPDATE'],
            'meta' => $GLOBALS['BIOCO_JOURNAL_META'],
            'acf' => $GLOBALS['BIOCO_JOURNAL_ACF'],
            'option' => $GLOBALS['BIOCO_JOURNAL_OPTION'],
            'sideload' => $GLOBALS['BIOCO_JOURNAL_SIDELOAD'],
            'menu' => $GLOBALS['BIOCO_JOURNAL_MENU'],
            'theme_mod' => $GLOBALS['BIOCO_JOURNAL_THEME_MOD'],
            'rewrite' => $GLOBALS['BIOCO_JOURNAL_REWRITE'],
        ];
    }
}
'''


def _b64(text: str) -> str:
    return base64.b64encode(text.encode("utf-8")).decode("ascii")


def _run_scenario(tmp_path: Path, scenario: dict) -> dict:
    import os

    scenario = dict(scenario)
    scenario["seed_dir"] = str(SEED_DIR)
    scenario["error_log"] = str(tmp_path / "error.log")
    scenario["wp_content_dir"] = str(tmp_path / "wp-content")
    # Site wiring options as a previously wired staging would have them, so
    # idempotent wiring makes zero writes unless a test breaks the wiring.
    scenario.setdefault(
        "options",
        {
            "timezone_string": "Europe/Zurich",
            "permalink_structure": "/%postname%/",
        },
    )
    scenario_path = tmp_path / "scenario.json"
    scenario_path.write_text(json.dumps(scenario, ensure_ascii=False), encoding="utf-8")
    harness_path = tmp_path / "harness-cli.php"
    harness_path.write_text(HARNESS.replace("WP_SERIALIZER", WP_SERIALIZER_STUB), encoding="utf-8")
    proc = subprocess.run(
        ["php", "-d", "error_reporting=E_ALL", "-f", str(harness_path), str(scenario_path)],
        cwd=ROOT,
        text=True,
        capture_output=True,
    )
    # Exit code 1 is a legitimate WP_CLI::error exit; the shutdown handler
    # still emitted the report. Only a bare crash (no JSON) is a harness bug.
    assert proc.returncode in (0, 1), proc.stdout + proc.stderr
    assert proc.stdout.strip(), proc.stderr
    result = json.loads(proc.stdout)
    result["exit_code"] = proc.returncode
    return result


def test_cli_verify_runtime_flag_runs_the_runtime_gate(tmp_path):
    result = _run_scenario(
        tmp_path, {"command": "verify", "flags": ["--runtime", "--only=kontakt"]}
    )

    assert result["errors"] == []
    assert result["success"], result["logs"]
    statuses = [line.split()[0] for line in result["logs"] if line.startswith("runtime-")]
    assert "runtime-ok" in statuses


def test_cli_verify_without_runtime_flag_is_seed_parity(tmp_path):
    result = _run_scenario(
        tmp_path,
        {"command": "verify", "flags": ["--only=kontakt"], "build_seeds": True, "edit_kontakt": True},
    )

    assert result["exit_code"] != 0
    assert result["success"] == []
    assert any("verify-mismatch" in line for line in result["logs"])


def test_cli_import_without_apply_is_a_non_writing_preview(tmp_path):
    result = _run_scenario(tmp_path, {"flags": [], "build_seeds": True})

    assert result["errors"] == []
    assert result["success"], result["logs"]
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal
    assert result["html_report_files"] == []
    assert result["error_log"] == ""


def test_cli_force_preview_names_overwrite_risk_without_writes(tmp_path):
    result = _run_scenario(
        tmp_path, {"flags": ["--force"], "build_seeds": True, "edit_kontakt": True}
    )

    assert result["errors"] == []
    rows = "\n".join(result["logs"])
    assert "kontakt" in rows
    assert "force=1" in rows
    assert "ueberschrieben" in rows
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal
    assert result["html_report_files"] == []
    assert result["error_log"] == ""


def test_cli_apply_skips_editorially_changed_pages(tmp_path):
    result = _run_scenario(
        tmp_path, {"flags": ["--apply"], "build_seeds": True, "edit_kontakt": True}
    )

    assert result["errors"] == []
    rows = "\n".join(result["logs"])
    assert "skip-existing" in rows
    # The editorial text survives: the stored page is NOT reset to the seed.
    assert result["pages"]["kontakt"] != result["seed_contents"]["kontakt"]
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal


def test_cli_apply_creates_a_missing_required_page(tmp_path):
    result = _run_scenario(
        tmp_path,
        {"flags": ["--apply", "--only=kontakt"], "drop_pages": ["kontakt"]},
    )

    assert result["errors"] == []
    assert len(result["journal"]["post_insert"]) == 1
    assert result["pages"]["kontakt"].strip() != ""


def test_cli_repeated_unchanged_apply_is_idempotent(tmp_path):
    # The FIRST run creates the missing page over an empty state; the SECOND
    # run executes over the first run's output and must not add anything.
    result = _run_scenario(
        tmp_path,
        {"flags": ["--apply", "--only=kontakt"], "drop_pages": ["kontakt"], "runs": 2},
    )

    assert result["errors"] == []
    first, second = result["runs"][0], result["runs"][1]
    assert len(first["post_insert"]) == 1, first
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert second[kind] == [], second
    assert result["pages"]["kontakt"].strip() != ""



def test_cli_apply_force_overwrites_and_keeps_its_report(tmp_path):
    result = _run_scenario(
        tmp_path, {"flags": ["--apply", "--force"], "build_seeds": True, "edit_kontakt": True}
    )

    assert result["errors"] == []
    assert any("ueberschrieben" in line for line in result["logs"])
    # The explicit forced import DOES reset the editorial text to the seed
    # and rebuilds the primary menu (--force contract).
    assert result["pages"]["kontakt"] == result["seed_contents"]["kontakt"]
    journal = result["journal"]
    for kind in ("post_insert", "meta", "acf", "option", "sideload"):
        assert journal[kind] == [], journal
    assert len(journal["post_update"]) >= 1
    assert journal["menu"], "forced import rebuilds the primary menu"
    assert result["html_report_files"], "applied import keeps its HTML report"
    assert result["error_log"], "applied import keeps its log"




# ---------------------------------------------------------------------------
# Explicit content import over collections (events/groups): the preview must
# name the overwrite risk; the no-clobber apply contract stays intact.
# ---------------------------------------------------------------------------


def _events_source(tmp_path: Path, description: str) -> Path:
    import json as _json
    source = tmp_path / "events.json"
    source.write_text(_json.dumps({
        "events": [
            {
                "title": "Schnuppertag Mai",
                "startDate": "2026-05-29T14:00:00+02:00",
                "eventType": "schnuppertag",
                "description": description,
                "status": "upcoming",
            }
        ]
    }), encoding="utf-8")
    return source


def test_cli_force_preview_with_collections_names_the_overwrite_without_writes(tmp_path):
    # The existing event was editorially changed: the preview must say it
    # WOULD be overwritten (the follow-up --apply --force overwrites it),
    # and it must not write anything.
    source = _events_source(tmp_path, "Seed-Beschreibung")
    result = _run_scenario(
        tmp_path,
        {
            "flags": ["--force"],
            "events_json": str(source),
            "existing_events": [
                {
                    "title": "Schnuppertag Mai",
                    "content": "Redaktionell gepflegte Beschreibung",
                    "fields": {"event_summary": "Redaktionell gepflegt", "event_status": "past"},
                }
            ],
        },
    )

    assert result["errors"] == [], result["errors"]
    event_rows = [line for line in result["logs"] if "event:schnuppertag-m" in line]
    assert event_rows, result["logs"][-12:]
    # The changed event row itself must announce the overwrite (WÜRDE/FORCE),
    # not the misleading "ok-equal + CMS gewinnt" it reported before.
    assert any("ueberschrieben" in line for line in event_rows), event_rows
    assert not any("CMS gewinnt" in line for line in event_rows), event_rows
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal
    assert result["html_report_files"] == []
    assert result["error_log"] == ""


def test_cli_force_preview_reports_only_the_actually_changed_fields(tmp_path):
    # Title-only case: the body already matches the source. The force preview
    # must name exactly what a follow-up --apply --force would overwrite
    # (array_keys of the changed fields, like the applied run) — the generic
    # "Titel/Beitragsinhalt" row falsely reported an unchanged body.
    source = _events_source(tmp_path, "Seed-Beschreibung")
    result = _run_scenario(
        tmp_path,
        {
            "flags": ["--force"],
            "events_json": str(source),
            "existing_events": [
                {
                    "title": "Schnuppertag Mai",
                    "post_title": "Schnuppertag Mai umbenannt",
                    "content": "Seed-Beschreibung",
                }
            ],
        },
    )

    assert result["exit_code"] == 0, result["errors"]
    event_rows = [line for line in result["logs"] if "event:schnuppertag-m" in line]
    assert event_rows, result["logs"][-12:]
    assert any("WÜRDE: FORCE: post_title ueberschrieben" in line for line in event_rows), event_rows
    assert not any("Beitragsinhalt" in line for line in event_rows), event_rows
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal


def test_cli_apply_with_collections_keeps_changed_event_without_writes(tmp_path):
    source = _events_source(tmp_path, "Seed-Beschreibung")
    result = _run_scenario(
        tmp_path,
        {
            "flags": ["--apply"],
            "events_json": str(source),
            "existing_events": [
                {
                    "title": "Schnuppertag Mai",
                    "content": "Redaktionell gepflegte Beschreibung",
                    "fields": {
                        "event_summary": "Redaktionell gepflegt",
                        "event_date": "2026-05-29 14:00:00",
                        "event_type": "schnuppertag",
                        "event_status": "upcoming",
                    },
                }
            ],
        },
    )

    assert result["errors"] == [], result["errors"]
    rows = "\n".join(result["logs"])
    assert "CMS gewinnt" in rows
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal


def test_cli_apply_force_with_collections_overwrites_and_reports(tmp_path):
    source = _events_source(tmp_path, "Seed-Beschreibung")
    result = _run_scenario(
        tmp_path,
        {
            "flags": ["--apply", "--force"],
            "events_json": str(source),
            "existing_events": [
                {
                    "title": "Schnuppertag Mai",
                    "content": "Redaktionell gepflegte Beschreibung",
                    "fields": {"event_summary": "Redaktionell gepflegt"},
                }
            ],
        },
    )

    assert result["errors"] == []
    journal = result["journal"]
    # The explicit forced import overwrites title/content and fields.
    assert len(journal["post_update"]) >= 1
    assert len(journal["acf"]) >= 1
    assert result["html_report_files"], "applied import keeps its HTML report"


def test_cli_apply_and_dry_run_flags_are_incompatible(tmp_path):
    result = _run_scenario(tmp_path, {"flags": ["--apply", "--dry-run"]})

    assert result["exit_code"] == 1
    assert result["success"] == []
    assert any("--dry-run" in message for message in result["errors"])


def _groups_source(tmp_path: Path, text: str) -> Path:
    source = tmp_path / "groups.json"
    source.write_text(json.dumps({
        "groups": [{"title": "Depot Reinach", "text": text}]
    }, ensure_ascii=False), encoding="utf-8")
    return source


def test_cli_force_preview_with_groups_names_the_overwrite_without_writes(tmp_path):
    # The existing group was editorially changed: the group source preview
    # must say its field WOULD be overwritten (a follow-up --apply --force
    # overwrites it), and it must not write anything.
    source = _groups_source(tmp_path, "Seed-Gruppentext")
    result = _run_scenario(
        tmp_path,
        {
            "flags": ["--force"],
            "groups_json": str(source),
            "existing_groups": [
                {"title": "Depot Reinach", "fields": {"group_text": "Redaktionell gepflegter Gruppentext"}}
            ],
        },
    )

    assert result["exit_code"] == 0, result["errors"]
    group_rows = [line for line in result["logs"] if "group:depot-reinach" in line]
    assert group_rows, result["logs"][-12:]
    assert any("ueberschrieben" in line for line in group_rows), group_rows
    assert not any("CMS gewinnt" in line for line in group_rows), group_rows
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal
    assert result["html_report_files"] == []
    assert result["error_log"] == ""


def test_cli_force_preview_reports_no_overwrite_for_an_all_equal_group(tmp_path):
    # An unchanged group must NOT get the misleading "Felder wuerden mit
    # --force ueberschrieben" header row: nothing would be written, so the
    # preview must report ok-equal only.
    source = _groups_source(tmp_path, "Seed-Gruppentext")
    result = _run_scenario(
        tmp_path,
        {
            "flags": ["--force"],
            "groups_json": str(source),
            "existing_groups": [
                {"title": "Depot Reinach", "fields": {"group_text": "Seed-Gruppentext"}}
            ],
        },
    )

    assert result["exit_code"] == 0, result["errors"]
    group_rows = [line for line in result["logs"] if "group:depot-reinach" in line]
    assert group_rows, result["logs"][-12:]
    assert not any("ueberschrieben" in line for line in group_rows), group_rows
    assert any("ok-equal" in line for line in group_rows), group_rows
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal
    assert result["html_report_files"] == []
    assert result["error_log"] == ""


def test_cli_apply_with_groups_keeps_changed_group_without_writes(tmp_path):
    # Applied import without --force keeps the no-clobber contract for
    # groups as well: the editorially changed group survives untouched.
    source = _groups_source(tmp_path, "Seed-Gruppentext")
    result = _run_scenario(
        tmp_path,
        {
            "flags": ["--apply"],
            "groups_json": str(source),
            "existing_groups": [
                {"title": "Depot Reinach", "fields": {"group_text": "Redaktionell gepflegter Gruppentext"}}
            ],
        },
    )

    assert result["exit_code"] == 0, result["errors"]
    rows = "\n".join(result["logs"])
    assert "CMS gewinnt" in rows
    journal = result["journal"]
    for kind in ("post_insert", "post_update", "meta", "acf", "option", "sideload", "menu", "theme_mod", "rewrite"):
        assert journal[kind] == [], journal


def test_cli_runtime_verify_failure_exits_nonzero(tmp_path):
    # A failed runtime gate must exit nonzero (WP_CLI::error), so a shell
    # release pipeline cannot mistake a failing site for a healthy one.
    result = _run_scenario(
        tmp_path,
        {"command": "verify", "flags": ["--runtime", "--only=kontakt"], "drop_pages": ["kontakt"]},
    )

    assert result["exit_code"] == 1
    assert result["success"] == []
    assert any("runtime-missing" in line for line in result["logs"])
