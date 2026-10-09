"""Real PHP enqueue hook and real native JS queue. See tests/README.md."""
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
CORE = ROOT / 'wordpress/web/app/mu-plugins/bioco-core'


def enqueue(environment='production', host='bioco.ch', url='https://matomo.bioco.ch', site_id='1'):
    php = r'''
    define('ABSPATH', __DIR__); $scenario=json_decode($argv[1],true); $scripts=[];$inline=[];$actions=[];
    putenv('BIOCO_MATOMO_URL='.$scenario['url']);putenv('BIOCO_MATOMO_SITE_ID='.$scenario['site_id']);
    function add_action($hook,$callback) {$GLOBALS['actions'][$hook]=$callback;}
    function wp_get_environment_type() {return $GLOBALS['scenario']['environment'];}
    function home_url($path) {return 'https://'.$GLOBALS['scenario']['host'].$path;}
    function plugin_dir_url($path) {return 'https://bioco.ch/wp-content/mu-plugins/bioco-core/';}
    function wp_enqueue_script(...$args) {$GLOBALS['scripts'][]=$args;}
    function wp_add_inline_script(...$args) {$GLOBALS['inline'][]=$args;}
    function wp_json_encode(...$args) {return json_encode(...$args);}
    require 'wordpress/web/app/mu-plugins/bioco-core/includes/matomo.php';
    $actions['wp_enqueue_scripts']();
    echo json_encode(['scripts'=>$scripts,'inline'=>$inline]);
    '''
    result = subprocess.run(['php', '-r', php, json.dumps(dict(environment=environment, host=host, url=url, site_id=site_id))], cwd=ROOT, check=True, capture_output=True, text=True)
    return json.loads(result.stdout)


@pytest.mark.parametrize('changes', [dict(environment='staging'), dict(host='staging.bioco.ch'), dict(host='www.bioco.ch'), dict(url=''), dict(site_id=''), dict(site_id='0'), dict(url='http://matomo.bioco.ch'), dict(url='https://user:password@matomo.bioco.ch'), dict(url='https://matomo.bioco.ch/?secret=test')])
def test_tracking_is_off_outside_configured_production(changes):
    assert enqueue(**changes) == dict(scripts=[], inline=[])


def test_enabled_tracking_uses_core_native_asset_and_safe_inline_config():
    result = enqueue(url='https://matomo.bioco.ch/</script>')
    assert result['scripts'][0][0:3] == ['bioco-matomo', 'https://bioco.ch/wp-content/mu-plugins/bioco-core/assets/bioco-matomo.js', []]
    assert result['scripts'][0][4] is True
    assert result['inline'][0][0] == 'bioco-matomo'
    assert result['inline'][0][2] == 'before'
    assert '</script>' not in result['inline'][0][1]
    assert '\\u003C' in result['inline'][0][1]
    assert "require_once BIOCO_CORE_DIR . '/includes/matomo.php';" in (CORE / 'bioco-core.php').read_text()


def test_real_js_queues_cookieless_commands_before_loading_async_tracker():
    result = enqueue()
    node = r'''
    const fs=require('fs'),vm=require('vm');
    const appended=[];const window={};
    const context=vm.createContext({window,document:{createElement:tag=>({tag}),head:{appendChild:s=>appended.push({...s,queue:JSON.parse(JSON.stringify(window._paq))})}}});
    vm.runInContext(process.argv[1],context);
    vm.runInContext(fs.readFileSync(process.argv[2],'utf8'),context);
    process.stdout.write(JSON.stringify({queue:window._paq,appended}));
    '''
    output = subprocess.run(['node', '-e', node, result['inline'][0][1], str(CORE / 'assets/bioco-matomo.js')], check=True, capture_output=True, text=True)
    behavior = json.loads(output.stdout)
    expected = [['setTrackerUrl', 'https://matomo.bioco.ch/matomo.php'], ['setSiteId', '1'], ['disableCookies'], ['trackPageView'], ['enableLinkTracking']]
    assert behavior['queue'] == expected
    assert behavior['appended'] == [dict(tag='script', **{'async': True}, src='https://matomo.bioco.ch/matomo.js', queue=expected)]
