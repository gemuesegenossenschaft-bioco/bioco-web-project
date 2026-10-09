"""Execute CLI-only archival PHP against controlled post metadata. See tests/README.md."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).parents[1]
SAMPLE = dict(page='wir', seo=dict(title='CMS title', description='CMS description'),
              sections=[dict(id='old-text', text='<p>All recovered text</p>')], fingerprint='source-fingerprint')


def archive(directory, source=None, existing=None, apply=True, repeat=False):
    directory.mkdir(exist_ok=True)
    (directory / 'wir.json').write_text(json.dumps(source or SAMPLE))
    php = r'''
    define('WP_CLI',true); define('OBJECT','OBJECT');
    $meta=json_decode($argv[2],true); $before=$meta; $content='Current editorial content';
    function get_page_by_path($path,...$rest) {return $path==='wir' ? (object)['ID'=>12] : null;}
    function metadata_exists($type,$id,$key) {return array_key_exists($key,$GLOBALS['meta']);}
    function get_post_meta($id,$key,$single) {return $GLOBALS['meta'][$key]??'';}
    function wp_slash($s) {return addslashes($s);}
    function add_post_meta($id,$key,$value,$unique) {if(metadata_exists('post',$id,$key))return false; $GLOBALS['meta'][$key]=stripslashes($value);return 1;}
    function wp_cache_delete(...$args) {}
    function wp_update_post(...$args) {throw new Exception('Content modification forbidden');}
    function register_post_meta(...$args) {throw new Exception('Public meta registration forbidden');}
    class DB {
        public $postmeta='prefix_postmeta';
        function prepare($sql,...$args) {if(!str_contains($sql,"meta_value = ''"))throw new Exception('Missing conditional');return $args;}
        function query($args) {[$value,$id,$key]=$args;if(($GLOBALS['meta'][$key]??null)!=='')return 0;$GLOBALS['meta'][$key]=$value;return 1;}
    }
    $wpdb=new DB;
    require 'wordpress/scripts/archive-cms-source.php';
    try {
        $report=bioco_archive_cms_source($argv[1],$argv[3]==='true');
        $second=$argv[4]==='true' ? bioco_archive_cms_source($argv[1],true) : null;
        echo json_encode(['report'=>$report,'second'=>$second,'meta'=>(object)$meta,'content'=>$content]);
    } catch(Throwable $error) {echo json_encode(['error'=>$error->getMessage(),'meta'=>(object)$meta,'before'=>(object)$before]);}
    '''
    output = subprocess.run(['php', '-r', php, str(directory), json.dumps(existing or {}), json.dumps(apply), json.dumps(repeat)], cwd=ROOT, check=True, capture_output=True, text=True)
    return json.loads(output.stdout)


def test_archive_is_complete_private_and_does_not_change_current_content(tmp_path):
    result = archive(tmp_path / 'cms-api', repeat=True)
    assert result['content'] == 'Current editorial content'
    archives = {k: v for k, v in result['meta'].items() if k.startswith('_bioco_cms_source_')}
    assert len(archives) == 1
    assert next(iter(archives.values())) == (tmp_path / 'cms-api/wir.json').read_text()
    assert json.loads(next(iter(archives.values()))) == SAMPLE
    assert result['meta']['rank_math_title'] == 'CMS title'
    assert result['meta']['rank_math_description'] == 'CMS description'
    assert result['second'] == dict(pages=1, archives_added=0, seo_filled=0)


def test_existing_seo_survives_conflict_and_empty_field_is_filled(tmp_path):
    result = archive(tmp_path / 'cms-api', existing=dict(rank_math_title='Editor title', rank_math_description=''))
    assert result['meta']['rank_math_title'] == 'Editor title'
    assert result['meta']['rank_math_description'] == 'CMS description'
    assert 'CMS title' in next(v for k, v in result['meta'].items() if k.startswith('_bioco_cms_source_'))


def test_preview_does_not_write(tmp_path):
    result = archive(tmp_path / 'cms-api', apply=False)
    assert result['meta'] == {}
    assert result['report'] == dict(pages=1, archives_added=1, seo_filled=2)


def test_invalid_shape_aborts_entire_directory_before_writes(tmp_path):
    directory = tmp_path / 'cms-api'
    directory.mkdir()
    (directory / 'z-invalid.json').write_text('{"page":"missing"}')
    result = archive(directory)
    assert 'error' in result
    assert result['meta'] == result['before'] == {}


def test_unknown_page_does_not_create_or_overwrite_posts(tmp_path):
    result = archive(tmp_path / 'cms-api', source=dict(SAMPLE, page='missing'))
    assert 'error' in result
    assert result['meta'] == {}


def test_http_cannot_execute_helper():
    result = subprocess.run(['php', '-r', "require 'wordpress/scripts/archive-cms-source.php';"], cwd=ROOT, capture_output=True)
    assert result.returncode == 1
    assert result.stdout == b''
