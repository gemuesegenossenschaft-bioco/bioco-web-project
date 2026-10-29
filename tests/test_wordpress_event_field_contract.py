"""Event metadata generation and save/render compatibility for #229.

Behavioral test map and runtime limits: tests/README.md.
"""
import importlib.util
import json
from pathlib import Path
import subprocess

import pytest

from test_wordpress_native_divi_modules import _preamble
from test_wordpress_verify_runtime import WP_SERIALIZER_STUB


ROOT = Path(__file__).parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'
COMPONENTS = ['events_feed', 'schnuppertage']


def generator_at(tmp_path):
    spec = importlib.util.spec_from_file_location(
        'event_metadata', ROOT / 'wordpress/scripts/build-native-modules.py')
    generator = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(generator)
    generator.OUT = tmp_path
    (tmp_path / 'fields.json').write_bytes((CORE / 'native-modules/fields.json').read_bytes())
    return generator


def test_regenerating_event_metadata_preserves_the_supported_saved_contract(tmp_path):
    generator = generator_at(tmp_path)
    before = json.loads((tmp_path / 'fields.json').read_text())
    generator.build(COMPONENTS)
    after = json.loads((tmp_path / 'fields.json').read_text())
    assert after == before
    for slug in ['events-feed', 'schnuppertage']:
        assert (tmp_path / slug / 'module.json').read_bytes() == (
            CORE / 'native-modules' / slug / 'module.json').read_bytes()


def snapshot(directory):
    return {p.relative_to(directory): p.read_bytes() for p in directory.rglob('*') if p.is_file()}


def test_check_reports_drift_without_creating_or_rewriting_files(tmp_path):
    generator = generator_at(tmp_path)
    generator.build(COMPONENTS)
    before = snapshot(tmp_path)
    generator.build(COMPONENTS, check=True)
    assert snapshot(tmp_path) == before
    target = tmp_path / 'events-feed/module.json'
    target.write_text(target.read_text().replace('include_schnuppertage', 'lost_setting'))
    drift = snapshot(tmp_path)
    with pytest.raises(ValueError, match='events-feed/module.json'):
        generator.build(COMPONENTS, check=True)
    assert snapshot(tmp_path) == drift
    target.unlink()
    missing = snapshot(tmp_path)
    with pytest.raises(ValueError, match='events-feed/module.json'):
        generator.build(COMPONENTS, check=True)
    assert snapshot(tmp_path) == missing


@pytest.mark.parametrize('change,diagnostic', [
    ('unsupported', 'Unsupported.*events_feed'),
    ('duplicate', 'Duplicate.*variant'),
    ('removed', 'Removed.*include_schnuppertage'),
    ('conflict', 'Conflicting.*include_schnuppertage'),
    ('choice', 'Conflicting.*variant'),
    ('narrow', 'Conflicting.*title'),
])
def test_invalid_event_contract_fails_before_writing_any_output(tmp_path, change, diagnostic):
    generator = generator_at(tmp_path)
    fields = next(g['fields'] for g in generator.GROUPS if g['key'] == 'group_bioco_block_events_feed')
    if change == 'unsupported':
        fields[0]['type'] = 'future_widget'
    elif change == 'duplicate':
        fields.append(dict(fields[0]))
    elif change == 'removed':
        fields[:] = [f for f in fields if f['name'] != 'include_schnuppertage']
    elif change == 'conflict':
        next(f for f in fields if f['name'] == 'include_schnuppertage')['type'] = 'number'
    elif change == 'choice':
        del fields[0]['choices']['banner']
    else:
        field = next(f for f in fields if f['name'] == 'title')
        field.update(type='select', choices={'restricted': 'Restricted'})
    before = snapshot(tmp_path)
    with pytest.raises(ValueError, match=diagnostic):
        generator.build(COMPONENTS)
    assert snapshot(tmp_path) == before


def test_new_required_row_field_is_an_incompatible_saved_contract(tmp_path):
    generator = generator_at(tmp_path)
    fields = next(g['fields'] for g in generator.GROUPS if g['key'] == 'group_bioco_block_schnuppertage')
    rows = next(f for f in fields if f['name'] == 'items')
    rows['sub_fields'].append({'name': 'new_required', 'label': 'New', 'type': 'text', 'required': 1})
    before = snapshot(tmp_path)
    with pytest.raises(ValueError, match='Conflicting.*new_required'):
        generator.build(COMPONENTS)
    assert snapshot(tmp_path) == before


SAVED_RENDER = _preamble(with_seam=True, stubs=r'''
require 'wordpress/web/app/mu-plugins/bioco-core/includes/dynamic-sections.php';
require 'wordpress/web/app/mu-plugins/bioco-core/includes/helpers.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-blocks.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/divi-composer.php';
require 'tests/fixtures/wp-block-parser/class-wp-block-parser.php';
function parse_blocks($html) { return (new WP_Block_Parser())->parse($html); }
function current_time($format) { return '2026-10-29 08:00:00'; }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function bioco_navigation_url($v) { return $v; }
function wp_kses_allowed_html($context) { return []; }
function wp_kses($html, $allowed) { return $html; }
class WP_Query {
    public function __construct($args) {
        $GLOBALS['queries'][] = $args;
        // WP_Query normalizes post__in with absint; ID 1 is a valid fixture event.
        $ids = [1, 2];
        if (isset($args['post__in'])) {
            $included = array_map(static fn($id) => abs((int) $id), $args['post__in']);
            $ids = array_values(array_intersect($ids, $included));
        }
        $GLOBALS['selected_event_ids'][] = array_slice($ids, 0, $args['posts_per_page']);
    }
    public function have_posts() { return false; }
}
''') + WP_SERIALIZER_STUB + r'''
$state = json_decode(stream_get_contents(STDIN), true);
$component = $state['component'];
$tree = Bioco_Import_Divi_Composer::section(['block'=>str_replace('_','-',$component),'values'=>$state['values']]);
$content = serialize_blocks([$tree]);
$saved = parse_blocks($content)[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
$values = bioco_native_extract_values($component, $saved['attrs']);
$valid = $values instanceof WP_Error ? $values : bioco_native_validate_values($component, $values);
$html = $valid instanceof WP_Error ? '' : bioco_render_dynamic_component($component, $valid);
echo json_encode(['values'=>$values,'error'=>$valid instanceof WP_Error ? $valid->get_error_code() : null,
    'queries'=>$GLOBALS['queries'] ?? [],'selected_event_ids'=>$GLOBALS['selected_event_ids'] ?? [],'html'=>$html,'attrs'=>$saved['attrs'],
    'stable'=>serialize_blocks(parse_blocks($content)) === $content]);
'''


def saved_render(component, values):
    result = subprocess.run(['php', '-r', SAVED_RENDER], cwd=ROOT,
                            input=json.dumps({'component': component, 'values': values}),
                            capture_output=True, text=True)
    assert result.returncode == 0, result.stderr
    assert result.stderr == ''
    return json.loads(result.stdout)


@pytest.mark.parametrize('component', COMPONENTS)
@pytest.mark.parametrize('limit', [0, 1, 5])
def test_saved_limit_survives_import_parse_and_public_query(component, limit):
    result = saved_render(component, {'limit': limit})
    assert result['error'] is None and result['stable']
    assert result['values'] == {'limit': limit}
    query = result['queries'][0]
    if limit == 0:
        assert result['selected_event_ids'][0] == [], 'A zero limit must exclude valid event ID 1 after WordPress ID normalization.'
    else:
        assert query['posts_per_page'] == limit


@pytest.mark.parametrize('component', COMPONENTS)
def test_omitted_fields_stay_omitted_and_query_defaults_remain_compatible(component):
    result = saved_render(component, {})
    assert result['error'] is None and result['stable']
    assert result['values'] == []  # PHP empty map; no materialized editor defaults.
    assert result['queries'][0]['posts_per_page'] == 3


@pytest.mark.parametrize('include', [True, False])
def test_saved_include_setting_controls_the_public_query_without_duplicate_visits(include):
    result = saved_render('events_feed', {'include_schnuppertage': include, 'schnuppertage_title': 'Visits'})
    assert result['values']['include_schnuppertage'] is include
    assert len(result['queries']) == (2 if include else 3)
    assert ('event_type' in json.dumps(result['queries'][0])) is not include


@pytest.mark.parametrize('event_type', ['general', 'schnuppertag', ''])
def test_saved_event_type_controls_the_public_query(event_type):
    result = saved_render('schnuppertage', {'event_type': event_type})
    assert result['values']['event_type'] == event_type
    assert (event_type or 'schnuppertag') in json.dumps(result['queries'][0]['meta_query'])


def test_repeated_items_and_explicit_empty_values_survive_the_saved_round_trip():
    values = {'title': '0', 'text': '', 'items': [{'text': 'A'}, {'text': 'A'}, {'text': '0'}],
              'closing': '', 'empty_message': ''}
    result = saved_render('schnuppertage', values)
    assert result['values'] == values and result['stable']


def test_unknown_saved_choice_is_reported_and_cannot_query_all_events():
    result = saved_render('schnuppertage', {'event_type': 'legacy-unknown'})
    assert result['error'] == 'bioco_native_invalid_value'
    assert result['queries'] == [] and result['stable']


def test_unknown_legacy_attributes_remain_stored_without_becoming_query_settings():
    result = saved_render('events_feed', {'future_setting': 'keep-legacy', 'limit': 1})
    assert result['attrs']['future_setting']['innerContent']['desktop']['value'] == 'keep-legacy'
    assert result['values'] == {'limit': 1} and result['stable']
    assert result['error'] is None and result['queries'][0]['posts_per_page'] == 1
