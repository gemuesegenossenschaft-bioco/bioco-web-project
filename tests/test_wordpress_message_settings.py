"""Saved message ownership and editor permissions for #158.

Test map: tests/README.md, Keep/Replace/Remove.
"""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PREAMBLE = r'''
define('ABSPATH', __DIR__);
$GLOBALS['options'] = []; $GLOBALS['hooks'] = [];
function add_action($name, $callback) { $GLOBALS['hooks'][$name] = $callback; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['options'][$name] = $value; }
function sanitize_textarea_field($value) { return strip_tags($value); }
function current_user_can($capability) { return $GLOBALS['can_edit'] ?? false; }
function wp_die(...$args) { throw new RuntimeException('forbidden'); }
function check_admin_referer($action) { throw new RuntimeException('nonce-required'); }
require 'wordpress/web/app/mu-plugins/bioco-forms/messages.php';
'''


def run_php(code):
    result = subprocess.run(['php', '-r', PREAMBLE + code], cwd=ROOT, capture_output=True, text=True)
    assert result.returncode == 0, result.stderr + result.stdout
    return json.loads(result.stdout)


def test_saved_values_and_explicit_empty_survive_reseed():
    result = run_php(r'''
    $seed = json_decode(file_get_contents('wordpress/content-seed/block-content/defaults.json'), true);
    bioco_forms_seed_messages($seed);
    $initial = bioco_forms_message('contact-form', 'success_message');
    $GLOBALS['options']['bioco_form_messages']['contact-form']['success_message'] = 'Redaktioneller Text';
    $GLOBALS['options']['bioco_form_messages']['contact-form']['captcha_error'] = '';
    bioco_forms_seed_messages($seed);
    echo json_encode([$initial, bioco_forms_message_values('contact-form', ['success_message'=>'old marker', 'captcha_error'=>'old marker'])]);
    ''')
    assert result[0].startswith('Vielen Dank für deine Nachricht!')
    assert result[1]['success_message'] == 'Redaktioneller Text'
    assert result[1]['captcha_error'] == ''


def test_message_sanitizer_rejects_unknown_keys_and_markup():
    result = run_php(r'''
    echo json_encode(bioco_forms_sanitize_messages(['contact-form'=>['success_message'=>'<script>x</script>Text', 'injected'=>'secret']]));
    ''')
    assert result['contact-form']['success_message'] == 'xText'
    assert 'injected' not in result['contact-form']
    assert result['contact-form']['captcha_error'] == ''


def test_save_handler_requires_editor_permission_and_nonce():
    result = run_php(r'''
    $errors=[];
    foreach ([false,true] as $canEdit) {
        $GLOBALS['can_edit']=$canEdit;
        try { $GLOBALS['hooks']['admin_post_bioco_save_form_messages'](); }
        catch (RuntimeException $error) { $errors[]=$error->getMessage(); }
    }
    echo json_encode([$errors,$GLOBALS['options']]);
    ''')
    assert result == [['forbidden', 'nonce-required'], []]
