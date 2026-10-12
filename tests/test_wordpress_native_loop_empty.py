"""Own branch selection/lifecycle at external boundaries; no licensed Divi emulation.

The installed native renderer is verified separately with a read-only operator probe.
Keep/Replace/Remove evidence map: tests/README.md.
"""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).parents[1]
HARNESS = r'''
namespace ET\Builder\FrontEnd\BlockParser {
    class BlockParserStore {
        public static array $parents = [];
        public static function get_parent($id, $instance) { return self::$parents[$id] ?? null; }
    }
}
namespace ET\Builder\Packages\ModuleLibrary\Row {
    class RowModule {
        public static array $calls = [];
        public static function render_callback($attrs,$content,$block,$elements,$defaults) {
            self::$calls[] = [$attrs,$elements,$defaults];
            return $content;
        }
    }
}
namespace {
    define('ABSPATH', '/');
    $state = json_decode(stream_get_contents(STDIN), true);
    function add_filter($name,$callback,$priority,$args) { $GLOBALS['registered']=[$name,$priority,$args]; }
    function et_fb_is_enabled() { return $GLOBALS['state']['editor'] ?? false; }
    class WP_Block_List extends \ArrayIterator { public function __construct($items,$context=[]) { parent::__construct($items); } }
    class WP_Block {
        public $name, $parsed_block, $context, $inner_blocks, $inner_content, $inner_html;
        public function render() {
            $this->context['render_mutation'] = true;
            if ($this->parsed_block['attrs']['throw'] ?? false) throw new \RuntimeException('Boundary failure');
            $output = $this->parsed_block['attrs']['text'] ?? '';
            foreach ($this->inner_blocks as $child) $output .= $child->render();
            return \Bioco_Native_Loop_Empty::filter_output($output,$this->parsed_block['attrs'],$this,'ELEMENTS',['style'=>'saved']);
        }
    }
    function attrs($class) { return ['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>['class'=>$class]]]]]]; }
    function block($id,$name,$class,$text='', $children=[]) {
        $b = new WP_Block(); $b->name = $name; $b->context = ['record'=>42];
        $b->inner_blocks = new WP_Block_List($children,$b->context);
        $b->inner_content = $children ? array_fill(0,count($children),null) : ["\n"];
        $b->inner_html = '';
        $b->parsed_block = ['id'=>$id,'storeInstance'=>17,'blockName'=>$name,'attrs'=>attrs($class)+['text'=>$text],
            'innerBlocks'=>array_map(fn($c)=>$c->parsed_block,$children),'innerContent'=>$b->inner_content,'innerHTML'=>''];
        foreach ($children as $child) \ET\Builder\FrontEnd\BlockParser\BlockParserStore::$parents[$child->parsed_block['id']] = (object)['id'=>$id,'attrs'=>$b->parsed_block['attrs']];
        return $b;
    }
    function inspect($b) { return ['id'=>$b->parsed_block['id'],'attrs'=>$b->parsed_block['attrs'],'context'=>$b->context,'content'=>$b->inner_content,'children'=>array_map('inspect',iterator_to_array($b->inner_blocks))]; }
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/native-loop-empty.php';
    $leaf = block('leaf','divi/text','', 'EMPTY_CHILD');
    $empty = block('empty','divi/column','bioco-loop-empty','EMPTY_A',[$leaf]);
    $empty2 = block('empty2','divi/text','bioco-loop-empty','EMPTY_B');
    $card = block('card','divi/text','', 'CARD');
    $column = block('column','divi/column','', '',[$card,$empty,$empty2]);
    $row = block('row','divi/row','bioco-record-loop','',[$column]);
    if ($state['decorated'] ?? false) {
        // Imported Divi rows can carry their visible class through the native
        // decoration attribute while htmlAttributes contains an empty class.
        $row->parsed_block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = '';
        $row->parsed_block['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] = [
            ['name'=>'class','value'=>'bioco-record-loop bioco-group-loop'],
        ];
        \ET\Builder\FrontEnd\BlockParser\BlockParserStore::$parents['column']->attrs = $row->parsed_block['attrs'];
    }
    if ($state['active'] ?? true) {
        \ET\Builder\FrontEnd\BlockParser\BlockParserStore::$parents['column']->attrs['__loop_source_id']='saved-loop';
    }
    if (isset($state['nested'])) {
        $innerEmpty = block('inner-empty','divi/text','bioco-loop-empty','INNER_EMPTY');
        $innerCard = block('inner-card','divi/text','','INNER_CARD');
        $innerColumn = block('inner-column','divi/column','','',[$innerCard,$innerEmpty]);
        $innerRow = block('inner-row','divi/row','bioco-record-loop','',[$innerColumn]);
        $innerRow->parsed_block['attrs']['__loop_source_id']='inner-loop';
        \ET\Builder\FrontEnd\BlockParser\BlockParserStore::$parents['inner-column']->attrs['__loop_source_id']='inner-loop';
        if ($state['nested'] === 'empty') $innerRow->parsed_block['attrs']['__loop_no_results']=true;
        $children = $state['nested'] === 'card' ? [$innerRow,$empty2]
            : [block('empty','divi/column','bioco-loop-empty','OUTER_EMPTY',[$innerRow])];
        $column = block('column','divi/column','','',$children);
        $row = block('row','divi/row','bioco-record-loop','',[$column]);
        \ET\Builder\FrontEnd\BlockParser\BlockParserStore::$parents['column']->attrs['__loop_source_id']='saved-loop';
    }
    $before = inspect($row);
    $selected = \Bioco_Native_Loop_Empty::select($column);
    $selected_before = inspect($selected);
    $a = $row->parsed_block['attrs'] + ['__loop_no_results'=>true,'__loop_source_id'=>'saved-loop'];
    if ($state['throw'] ?? false) {
        $leaf->parsed_block['attrs']['throw'] = true;
        try { \Bioco_Native_Loop_Empty::filter_output('ORIGINAL',$a,$row,'ELEMENTS',['style'=>'saved']); }
        catch (\RuntimeException $error) { $caught=$error->getMessage(); }
        unset($leaf->parsed_block['attrs']['throw']);
    }
    $output = \Bioco_Native_Loop_Empty::filter_output('ORIGINAL',$a,$row,'ELEMENTS',['style'=>'saved']);
    $normal = \Bioco_Native_Loop_Empty::filter_output('VISIBLE',$empty->parsed_block['attrs'],$empty,null,[]);
    $outside = block('outside','divi/text','bioco-loop-empty','outside');
    $outside_output = \Bioco_Native_Loop_Empty::filter_output('OUTSIDE',$outside->parsed_block['attrs'],$outside,null,[]);
    $unrelated = \Bioco_Native_Loop_Empty::filter_output('OTHER',attrs('other')+['__loop_no_results'=>true],$row,null,[]);
    $classes = [];
    foreach ([['class'=>'bioco-loop-empty other'], json_encode(['class'=>'bioco-loop-empty']), ['class'=>['bioco-loop-empty']], false, ['class'=>'bioco-loop-emptyish']] as $value) {
        $test=['module'=>['advanced'=>['htmlAttributes'=>['desktop'=>['value'=>$value]]]]];
        $classes[] = \Bioco_Native_Loop_Empty::has_class($test,'bioco-loop-empty');
    }
    foreach (['bioco-loop-empty other','bioco-loop-emptyish',['bioco-loop-empty']] as $value) {
        $test=attrs('');
        $test['module']['decoration']['attributes']['desktop']['value']['attributes'] = [['name'=>'class','value'=>$value]];
        $classes[] = \Bioco_Native_Loop_Empty::has_class($test,'bioco-loop-empty');
    }
    echo json_encode(['selected'=>$selected_before,'output'=>$output,'normal'=>$normal,'outside'=>$outside_output,'unrelated'=>$unrelated,
        'original_unchanged'=>inspect($row)===$before,'row_calls'=>\ET\Builder\Packages\ModuleLibrary\Row\RowModule::$calls,
        'classes'=>$classes,'hook'=>$GLOBALS['registered'],'caught'=>$caught ?? null]);
}
'''


def run(**state):
    result = subprocess.run(['php', '-r', HARNESS], cwd=ROOT, input=json.dumps(state),
                            capture_output=True, text=True, check=True)
    assert result.stderr == ''
    return json.loads(result.stdout)


def test_projection_retains_native_paths_order_and_complete_empty_subtrees():
    result = run()
    assert result['output'] == 'EMPTY_AEMPTY_CHILDEMPTY_B'
    chosen = result['selected']
    assert chosen['id'] == 'column' and chosen['content'] == [None, None]
    assert [b['id'] for b in chosen['children']] == ['empty', 'empty2']
    assert chosen['children'][0]['children'][0]['id'] == 'leaf'
    assert chosen['children'][1]['content'] == ['\n']
    assert chosen['context'] == {'record': 42}
    assert result['original_unchanged'] is True
    attrs, elements, defaults = result['row_calls'][0]
    assert '__loop_no_results' not in attrs and attrs['__loop_source_id'] == 'saved-loop'
    assert elements == 'ELEMENTS' and defaults == {'style': 'saved'}


def test_only_active_marked_record_loops_hide_empty_branches():
    assert run()['normal'] == ''
    assert run(active=False)['normal'] == 'VISIBLE'
    result = run()
    assert result['outside'] == 'OUTSIDE' and result['unrelated'] == 'OTHER'
    assert result['classes'] == [True, True, False, False, False, True, False, False]
    assert result['hook'] == ['divi_loop_rendered_output', 30, 5]


def test_editor_keeps_all_branches_visible_and_uses_the_original_renderer_output():
    result = run(editor=True)
    assert result['output'] == 'ORIGINAL' and result['normal'] == 'VISIBLE'
    assert result['row_calls'] == [] and result['original_unchanged'] is True


def test_saved_divi_decoration_class_selects_the_native_empty_branch():
    result = run(decorated=True)
    assert result['output'] == 'EMPTY_AEMPTY_CHILDEMPTY_B'
    assert result['normal'] == ''
    assert result['row_calls'][0][0]['module']['decoration']['attributes']['desktop']['value']['attributes'][0]['value'] == 'bioco-record-loop bioco-group-loop'


def test_render_exception_restores_projection_state_and_allows_retry():
    result = run(throw=True)
    assert result['caught'] == 'Boundary failure'
    assert result['normal'] == '' and result['output'] == 'EMPTY_AEMPTY_CHILDEMPTY_B'
    assert result['original_unchanged'] is True


def test_outer_projection_does_not_select_a_nested_cards_empty_branch():
    result = run(nested='card')
    assert result['output'] == 'EMPTY_B'
    assert [b['id'] for b in result['selected']['children']] == ['empty2']
    assert result['original_unchanged'] is True


def test_nested_populated_and_empty_loops_keep_their_own_state_in_an_outer_empty_branch():
    populated = run(nested='populated')
    assert populated['output'] == 'OUTER_EMPTYINNER_CARD'
    assert populated['original_unchanged'] is True
    empty = run(nested='empty')
    assert empty['output'] == 'OUTER_EMPTYINNER_EMPTY'
    assert empty['original_unchanged'] is True
