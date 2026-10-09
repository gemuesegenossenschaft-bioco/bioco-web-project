"""Execute shared single templates and verify native block/filter contracts.

WordPress functions are stubbed; this does not emulate the licensed Divi renderer.
"""
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_divi_block_serialization import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
THEME = 'wordpress/web/app/themes/bioco-divi/'

HARNESS = r'''
define('ABSPATH', __DIR__);
$fixture = json_decode(stream_get_contents(STDIN), true);
$calls = [];
function get_header() { require 'wordpress/web/app/themes/bioco-divi/header.php'; }
function get_footer() { require 'wordpress/web/app/themes/bioco-divi/footer.php'; }
function language_attributes() { echo 'lang="de"'; }
function bloginfo($key) { echo 'UTF-8'; }
function wp_head() {}
function body_class() { echo 'class="single-event"'; }
function wp_body_open() {}
function is_page_template($template) { return false; }
function bioco_render_primary_navigation() { return '<nav>Shared navigation</nav>'; }
function bioco_render_site_footer() { return '<footer>Shared footer</footer>'; }
function wp_footer() {}
function have_posts() { static $called = false; if ($called) return false; $called = true; return true; }
function the_post() {}
function get_the_ID() { return 42; }
function get_the_title($id = null) { global $fixture; return $fixture['title']; }
function get_post_field($field, $id) { global $fixture; return $fixture['body']; }
function get_field($field, $id) { global $fixture; return $fixture[$field] ?? null; }
function get_post_type($id) { global $fixture; return $fixture['post_type'] ?? 'event'; }
function get_the_date($format, $id) { return '01.10.2026'; }
function has_post_thumbnail($id) { global $fixture; return !empty($fixture['thumbnail']); }
function get_the_post_thumbnail_url($id, $size) { global $fixture; return $fixture['thumbnail']; }
function get_post_thumbnail_id($id) { return 7; }
function wp_get_attachment_image_url($id, $size) { return ''; }
function get_post_meta($id, $key, $single) { return 'Article thumbnail'; }
function home_url($path) { return 'https://example.test' . $path; }
function add_filter($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['filters'][$hook] = $callback; $GLOBALS['filter_args'][$hook] = $args; }
function add_action($hook, $callback) {}
function is_singular($types) { global $fixture; return !($fixture['archive'] ?? false) && in_array(get_post_type(42), (array) $types, true); }
function get_queried_object_id() { return 42; }
function apply_filters($hook, $value) {
    global $calls;
    $GLOBALS['layout_input'] = $value;
    $calls[] = 'native_layout_content_filters';
    return '<div>Native layout renderer result</div>';
}
function serialize_blocks($blocks) {
    $GLOBALS['layout'] = $blocks;
    return wp_test_serialize_blocks($blocks);
}

function wp_timezone() { return new DateTimeZone('Europe/Zurich'); }
function wp_date($format, $ts) { return (new DateTimeImmutable('@' . $ts))->setTimezone(wp_timezone())->format($format); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url_raw($value) { return str_starts_with($value, 'https://') ? $value : ''; }
function the_content() { global $calls; $calls[] = 'the_content'; echo '<p>Editor rendered with content filters.</p>'; }
// A sanitizer spy: verifies that fallback HTML passes through WP's sanitizer.
function wp_kses_post($value) { global $calls; $calls[] = ['wp_kses_post', $value]; return '<p>Safe <strong>summary</strong>.</p>'; }
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
ob_start();
require 'wordpress/web/app/themes/bioco-divi/functions.php';
require 'wordpress/web/app/themes/bioco-divi/' . (get_post_type(42) === 'event' ? 'single-event.php' : 'single.php');
$html = ob_get_clean();
$metadata_filter = $GLOBALS['filters']['get_post_metadata'];
$theme_layouts = ['header' => ['id' => 101, 'enabled' => true], 'body' => ['id' => 102, 'enabled' => true], 'footer' => ['id' => 103, 'enabled' => true]];
$theme_layouts_filter = $GLOBALS['filters']['et_theme_builder_template_layouts'];
echo json_encode(['html' => $html, 'calls' => $calls, 'layout' => $GLOBALS['layout'],
    'original_theme_layouts' => $theme_layouts, 'filtered_theme_layouts' => $theme_layouts_filter($theme_layouts),
    'layout_input' => $GLOBALS['layout_input'], 'fixture' => $fixture,
    'metadata' => array_map(static function ($key) use ($metadata_filter) {
        return [
            'single' => $metadata_filter(null, 42, $key, true),
            'multi' => $metadata_filter(null, 42, $key, false),
            'other_single' => $metadata_filter('other', 99, $key, true),
            'other_multi' => $metadata_filter(['other'], 99, $key, false),
        ];
    }, ['_et_pb_use_builder', '_et_pb_page_layout']),
    'accepted_args' => $GLOBALS['filter_args']['get_post_metadata'],
    'unrelated_single' => $metadata_filter('original', 42, 'unrelated', true),
    'unrelated_multi' => $metadata_filter(['original'], 42, 'unrelated', false)]);

'''


def render(body='', title='Geburtstagfondue', **fields):
    fixture = {'body': body, 'title': title, 'event_date': '2026-10-09 18:30:00',
               'event_summary': '<p>Safe <strong>summary</strong>.</p><script>bad()</script>',
               'card_image': {'url': 'https://example.test/event.jpg', 'alt': 'Bild "<unsafe>"'}}
    fixture.update(fields)
    serializer = WP_SERIALIZER_STUB.replace('function serialize_blocks(', 'function wp_test_serialize_blocks(')
    result = subprocess.run(['php', '-r', serializer + HARNESS], input=json.dumps(fixture), cwd=ROOT,
                            text=True, capture_output=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def modules(result):
    section = result['layout'][0]
    assert section['blockName'] == 'divi/section'
    row, = section['innerBlocks']
    assert row['blockName'] == 'divi/row'
    column, = row['innerBlocks']
    assert column['blockName'] == 'divi/column'
    return column['innerBlocks']


def text_value(module):
    return module['attrs']['content']['innerContent']['desktop']['value']


@pytest.mark.parametrize('title', ['Geburtstagfondue', 'Ausserordentliche GV'])
def test_empty_production_event_uses_sanitized_summary_and_shared_shell(title):
    result = render(title=title)
    layout = modules(result)
    assert [m['blockName'] for m in layout] == [
        'divi/post-title', 'divi/text', 'divi/image', 'divi/text', 'divi/button']
    assert '09.10.2026 18:30 Uhr' in text_value(layout[1])
    assert text_value(layout[3]) == '<p>Safe <strong>summary</strong>.</p>'
    assert 'Shared navigation' in result['html'] and 'Shared footer' in result['html']
    assert '<main id="bioco-main-content">' in result['html']
    assert 'Native layout renderer result' in result['html']
    assert result['calls'] == [
        ['wp_kses_post', '<p>Safe <strong>summary</strong>.</p><script>bad()</script>'],
        'native_layout_content_filters']
    assert '<!-- wp:divi/section' in result['layout_input']
    assert '<script>' not in result['layout_input']
    assert result['fixture']['body'] == ''


@pytest.mark.parametrize('body', ['<p>Editor text.</p>', '<!-- wp:shortcode -->[event]<!-- /wp:shortcode -->', '0'])
def test_editor_content_uses_normal_content_rendering_and_wins_over_summary(body):
    result = render(body=body)
    assert result['calls'] == ['the_content', 'native_layout_content_filters']
    assert text_value(modules(result)[3]) == '<p>Editor rendered with content filters.</p>'
    assert result['fixture']['body'] == body
    assert 'divi/post-content' not in result['layout_input']


def test_dynamic_title_disables_publication_meta_and_featured_image_for_event():
    result = render(title='<script>title</script>')
    layout = modules(result)
    title_attrs = layout[0]['attrs']
    assert title_attrs['meta']['advanced']['showMeta']['desktop']['value'] == 'off'
    assert title_attrs['image']['advanced']['enabled']['desktop']['value'] == 'off'
    image = layout[2]['attrs']['image']['innerContent']['desktop']['value']
    assert image['src'] == 'https://example.test/event.jpg'
    assert image['alt'] == 'Bild "<unsafe>"'
    # Values stay in JSON attributes for the native module renderer to escape.
    assert '<unsafe>' not in result['layout_input']
    assert '<script>title' not in result['layout_input']


def test_whitespace_body_falls_back_and_missing_image_date_do_not_break_layout():
    result = render(body=' \n ', event_date='', card_image=None)
    assert [m['blockName'] for m in modules(result)] == ['divi/post-title', 'divi/text', 'divi/button']
    assert result['calls'][0][0] == 'wp_kses_post'


def test_posts_use_shared_native_layout_with_publication_date_and_thumbnail():
    result = render(body='Article editor', post_type='post', thumbnail='https://example.test/post.jpg')
    layout = modules(result)
    assert '01.10.2026' in text_value(layout[1])
    assert '18:30 Uhr' not in text_value(layout[1])
    assert layout[2]['attrs']['image']['innerContent']['desktop']['value']['src'] == 'https://example.test/post.jpg'
    assert result['calls'] == ['the_content', 'native_layout_content_filters']
    back = layout[-1]['attrs']['button']['innerContent']['desktop']['value']
    assert back['linkUrl'] == 'https://example.test/aktuelles/'
    assert back['text'] == 'Zurück zu Aktuelles'


def test_empty_post_does_not_use_event_summary():
    result = render(post_type='post', card_image=None)
    assert text_value(modules(result)[2]) == ''
    assert result['calls'] == ['native_layout_content_filters']


def test_frontend_builder_marker_is_request_only_and_scoped_to_queried_single():
    for post_type in ('event', 'post', 'page'):
        result = render(post_type=post_type)
        assert result['accepted_args'] == 4
        for metadata, value in zip(result['metadata'], ('on', 'et_full_width_page')):
            assert metadata['single'] == (value if post_type != 'page' else None)
            assert metadata['multi'] == ([value] if post_type != 'page' else None)
            assert metadata['other_single'] == 'other'
            assert metadata['other_multi'] == ['other']
        assert result['unrelated_single'] == 'original'
        assert result['unrelated_multi'] == ['original']


def test_image_url_stays_raw_in_native_attributes_without_double_encoding():
    url = 'https://example.test/event.jpg?width=760&quality=90'
    result = render(card_image={'url': url, 'alt': 'Image'})
    image = modules(result)[2]['attrs']['image']['innerContent']['desktop']['value']
    assert image['src'] == url
    assert '&#' not in image['src']


@pytest.mark.parametrize('post_type', ['event', 'post'])
def test_article_singles_bypass_global_theme_builder_layouts(post_type):
    result = render(post_type=post_type)
    assert result['filtered_theme_layouts'] == []
    assert result['original_theme_layouts']['body']['id'] == 102
    assert 'Shared navigation' in result['html']
    assert 'Shared footer' in result['html']


@pytest.mark.parametrize('post_type,archive', [
    ('page', False), ('event', True), ('post', True),
    ('et_template', False), ('et_header_layout', False),
    ('et_body_layout', False), ('et_footer_layout', False),
])
def test_other_requests_keep_global_theme_builder_layouts(post_type, archive):
    result = render(post_type=post_type, archive=archive)
    assert result['filtered_theme_layouts'] == result['original_theme_layouts']
