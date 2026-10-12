"""Operator previews stay private, cannot overwrite backups and require admin."""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
PHP = r'''
$state=json_decode(stream_get_contents(STDIN),true);define('ABSPATH',$state['root']);
function wp_json_encode($v,...$args){return json_encode($v,...$args);}
function current_user_can($cap){return false;}
class WP_CLI {static function add_command(...$args){}static function error($message){throw new RuntimeException($message);}}
require 'wordpress/web/app/mu-plugins/bioco-import/includes/event-template-migration.php';
require 'wordpress/web/app/mu-plugins/bioco-import/includes/form-adoption-cli.php';
try{
 if($state['mode']==='write'){bioco_import_form_write_preview($state['path'],['before'=>'Recovery snapshot']);echo json_encode(['saved'=>true,'mode'=>fileperms($state['path'])&0777]);}
 elseif($state['mode']==='read')echo json_encode(['plan'=>bioco_import_form_read_preview($state['path'])]);
 else{bioco_import_form_operator([],['action'=>'apply','preview'=>$state['path'],'expect'=>'wrong']);echo json_encode(['bad'=>true]);}
}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);}
'''


def run(root, path, mode):
    result = subprocess.run(['php', '-r', PHP], cwd=ROOT, input=json.dumps({'root': str(root), 'path': str(path), 'mode': mode}), text=True, capture_output=True)
    assert result.returncode == 0 and result.stderr == ''
    return json.loads(result.stdout)


@pytest.fixture()
def host(tmp_path):
    root = tmp_path / 'wordpress'; root.mkdir()
    backup = tmp_path / 'backups' / 'editor'; backup.mkdir(parents=True, mode=0o700)
    return root, backup


def test_private_preview_is_recoverable_and_cannot_be_replaced(host):
    root, backup = host; path = backup / 'contact.json'
    assert run(root, path, 'write') == {'saved': True, 'mode': 0o600}
    assert run(root, path, 'read') == {'plan': {'before': 'Recovery snapshot'}}
    assert 'error' in run(root, path, 'write')
    assert run(root, path, 'read')['plan']['before'] == 'Recovery snapshot'


def test_public_missing_and_symlink_paths_are_refused(host):
    root, backup = host
    assert 'error' in run(root, root / 'public.json', 'write')
    path = backup / 'contact.json'; assert run(root, path, 'write')['saved']
    path.chmod(0o644); assert 'error' in run(root, path, 'read')
    path.chmod(0o600); link = backup / 'link.json'; link.symlink_to(path)
    assert 'error' in run(root, link, 'read')
    backup.chmod(0o755); assert 'error' in run(root, backup / 'other.json', 'write')


def test_operator_refuses_mutation_without_administrator_before_reading_preview(host):
    root, backup = host
    result = run(root, backup / 'missing.json', 'apply')
    assert result == {'error': 'Use an authorized WordPress administrator.'}
