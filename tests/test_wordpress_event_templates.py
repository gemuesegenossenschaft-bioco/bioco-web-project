"""Event binding behavior and real WP block round trips, not licensed Divi emulation.

Native builder save/reopen and public rendering are separate operator checks.
"""
import json
import subprocess
from pathlib import Path

import pytest

from test_wordpress_verify_runtime import WP_SERIALIZER_STUB

ROOT = Path(__file__).parents[1]
HARNESS = r'''
define('ABSPATH', '/');
$state = json_decode(stream_get_contents(STDIN), true);
$records = $state['records'];
$GLOBALS['post'] = (object) ['ID' => 999];
function add_filter($name, $callback, $priority, $args=1) { $GLOBALS['hooks'][$name]=[$callback,$args]; }
function get_post_type($id) { return $GLOBALS['records'][$id]['type'] ?? 'page'; }
function get_post_status($id) { return $GLOBALS['records'][$id]['status'] ?? 'publish'; }
function get_queried_object_id() { return $GLOBALS['state']['queried'] ?? 999; }
function get_post_meta($id,$key,$single) { return $GLOBALS['records'][$id]['meta'][$key] ?? ''; }
function current_user_can($cap,$id) { return in_array($id,$GLOBALS['state']['allowed'] ?? [],true); }
function get_field($key,$id) { return $GLOBALS['records'][$id][$key] ?? null; }
function get_the_title($id) { return $GLOBALS['records'][$id]['title']; }
function get_permalink($id) { return 'https://example.test/events/' . $id . '/'; }
function get_post($id) { return (object) ['ID'=>$id,'post_content'=>$GLOBALS['records'][$id]['body'] ?? '']; }
function post_password_required($id) { return $GLOBALS['records'][$id]['protected'] ?? false; }
function get_post_thumbnail_id($id) { return $GLOBALS['records'][$id]['thumbnail'] ?? 0; }
function wp_get_attachment_image_url($id,$size) { return $id ? 'https://example.test/' . $id . '.jpg' : ''; }
function wp_timezone() { return new DateTimeZone('Europe/Zurich'); }
function wp_date($format,$time) { return (new DateTimeImmutable('@'.$time))->setTimezone(wp_timezone())->format($format); }
function esc_html($value) { return htmlspecialchars($value,ENT_QUOTES,'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url_raw($value) { return str_starts_with($value,'https://') ? $value : ''; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wp_kses_post($value) { return preg_replace('~<script>.*?</script>~s','',$value); }
function apply_filters($name,$value) {
    $GLOBALS['content_ids'][]=$GLOBALS['post']->ID;
    if ($GLOBALS['state']['throw'] ?? false) throw new RuntimeException('Filter failed');
    if ($GLOBALS['state']['recursive'] ?? false) {
        $GLOBALS['recursive_result']=bioco_event_binding_value('', $GLOBALS['state']['context']);
    }
    return '<div class="filtered">'.$value.'</div>';
}
function wp_json_encode($value,$flags=0) { return json_encode($value,$flags); }
function home_url($path) { return 'https://example.test'.$path; }
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/event-bindings.php';
try { $resolved=bioco_event_binding_value($state['original'] ?? '',$state['context']); }
catch (Throwable $error) { $resolved=['error'=>$error->getMessage()]; }
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/event-template.php';
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
WP_SERIALIZER
$layout=serialize_blocks(bioco_import_event_template_blocks());
$parsed=(new WP_Block_Parser())->parse($layout);
echo json_encode(['value'=>$resolved,'post'=>$GLOBALS['post']->ID,'content_ids'=>$GLOBALS['content_ids'] ?? [],
    'recursive'=>$GLOBALS['recursive_result'] ?? null,'options'=>bioco_event_binding_fields(['keep'=>['id'=>'keep']]),
    'layout'=>$parsed,'stable'=>serialize_blocks($parsed)===$layout]);
'''


def record(**values):
    return dict(type='event', status='publish', title='Event & Titel', body='',
                event_date='2027-03-28 14:00:00', event_summary='<p>Kurz <strong>Text</strong></p>') | values


def run(field='date_time', records=None, **state):
    context = state.pop('context', dict(post_id=1, name='bioco_event_' + field))
    result = subprocess.run(['php', '-r', HARNESS.replace('WP_SERIALIZER', WP_SERIALIZER_STUB)],
                            cwd=ROOT, input=json.dumps(dict(records=records or {'1': record()}, context=context, **state)),
                            capture_output=True, text=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def walk(blocks):
    for block in blocks:
        yield block
        yield from walk(block['innerBlocks'])


def test_local_event_date_preserves_wall_clock_across_dst():
    assert run()['value'] == '28.03.2027 14:00 Uhr'
    assert run('date')['value'] == '28.03.2027'
    assert run('time')['value'] == '14:00 Uhr'


def test_loop_event_wins_over_the_enclosing_page_and_other_record():
    state = {'1': record(), '2': record(event_date='2027-06-30 09:30:00')}
    result = run(records=state, queried=1, context=dict(post_id=999,loop_id=2,name='loop_bioco_event_date_time'))
    assert result['value'] == '30.06.2027 09:30 Uhr'
    assert run(context=dict(post_id=2,name='loop_bioco_event_date'), records=state)['value'] == '30.06.2027'
    assert run(context=dict(post_id=999,name='loop_bioco_event_date'), queried=1)['value'] == ''


def test_summary_and_body_precedence_preserve_filters_and_restore_record_context():
    assert run('summary')['value'] == 'Kurz Text'
    assert run('description')['value'] == '<p>Kurz <strong>Text</strong></p>'
    result = run('description', records={'1': record() | {'body':'<p>Editor body</p>'}})
    assert result['value'] == '<div class="filtered"><p>Editor body</p></div>'
    assert result['content_ids'] == [1] and result['post'] == 999
    result = run('description', records={'1': record() | {'body':'0'}}, recursive=True)
    assert result['value'] == '<div class="filtered">0</div>' and result['recursive'] == ''
    result = run('description', records={'1': record() | {'body':'Body'}}, throw=True)
    assert result['value'] == {'error':'Filter failed'} and result['post'] == 999
    result = run('description', records={'1': record() | {'body':'<p>Safe</p><script>bad()</script>'}})
    assert result['value'] == '<div class="filtered"><p>Safe</p></div>'


def test_protected_or_unreadable_records_cannot_leak_description():
    assert run('description', records={'1': record(protected=True)})['value'] == ''
    assert run('summary', records={'1': record(protected=True)})['value'] == ''
    draft = {'1': record() | {'status':'draft'}}
    assert run(records=draft)['value'] == ''
    assert run(records=draft, allowed=[1])['value'] == '28.03.2027 14:00 Uhr'
    assert run(records={'1':record() | {'type':'page'}})['value'] == ''
    assert run(context={'name':'unrelated'}, original={'keep':True})['value'] == {'keep':True}


@pytest.mark.parametrize('field', ['image','image_alt'])
def test_card_image_falls_back_to_featured_and_missing_media_stays_empty(field):
    result = run(field, records={'1':record(card_image={'url':'https://example.test/card.jpg','alt':'Bild'})})
    assert result['value'] == ('https://example.test/card.jpg' if field == 'image' else 'Bild')
    result = run(field, records={'1':record(thumbnail=42)})
    assert result['value'] == ('https://example.test/42.jpg' if field == 'image' else 'Event &amp; Titel')
    assert run(field)['value'] == ''


def test_unassigned_template_can_only_preview_its_readable_matching_draft_event():
    records = {'1':record() | {'status':'draft','meta':{'_bioco_editor_verification':'qa-20261011'}},
               '2':{'type':'et_body_layout','status':'draft','meta':{
                   '_bioco_editor_verification':'qa-20261011','_bioco_editor_verification_event':1}}}
    context = dict(post_id=2,name='bioco_event_date')
    assert run(records=records,context=context,allowed=[1,2])['value'] == '28.03.2027'
    assert run(records=records,context=context,allowed=[1])['value'] == ''
    records['2']['meta']['_bioco_editor_verification']='another-run'
    assert run(records=records,context=context,allowed=[1,2])['value'] == ''


def test_saved_template_contains_editable_native_modules_and_record_bindings():
    result = run()
    assert result['stable']
    modules = [b for b in walk(result['layout']) if b['blockName'] not in ['divi/section','divi/row','divi/column']]
    assert [b['blockName'] for b in modules] == ['divi/heading','divi/text','divi/image','divi/text','divi/button']
    assert 'bioco_event_title' in modules[0]['attrs']['title']['innerContent']['desktop']['value']
    assert 'bioco_event_date_time' in modules[1]['attrs']['content']['innerContent']['desktop']['value']
    assert 'bioco_event_image' in modules[2]['attrs']['image']['innerContent']['desktop']['value']['src']
    assert 'bioco_event_description' in modules[3]['attrs']['content']['innerContent']['desktop']['value']
    button=modules[4]['attrs']['button']['innerContent']['desktop']['value']
    assert button == dict(text='Zurück zu Aktuelles',linkUrl='https://example.test/aktuelles/',linkTarget='off')
    assert len(result['options']) == 19 and result['options']['keep'] == {'id':'keep'}
    assert result['options']['loop_bioco_event_image']['type'] == 'image'
