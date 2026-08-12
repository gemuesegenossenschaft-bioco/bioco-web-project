import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]


def test_turnstile_uses_official_test_keys_only_for_unconfigured_staging():
    php = r'''
    define('ABSPATH', __DIR__);
    function add_action($hook, $callback) {}
    function wp_get_environment_type() { return $GLOBALS['wp_environment']; }
    require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';

    function config_for($environment, $site_key = null, $secret = null) {
        $GLOBALS['wp_environment'] = $environment;
        putenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY');
        putenv('TURNSTILE_SECRET_KEY');
        if ($site_key !== null) putenv('NEXT_PUBLIC_TURNSTILE_SITE_KEY=' . $site_key);
        if ($secret !== null) putenv('TURNSTILE_SECRET_KEY=' . $secret);
        return bioco_forms_turnstile_config();
    }

    echo json_encode([
        'staging' => config_for('staging'),
        'production' => config_for('production'),
        'explicit' => config_for('staging', 'real-site-key', 'real-secret'),
        'partial' => config_for('staging', 'real-site-key'),
    ]);
    '''
    result = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout
    configs = json.loads(result)

    assert configs["staging"] == {
        "site_key": "1x00000000000000000000AA",
        "secret": "1x0000000000000000000000000000000AA",
        "configured": True,
        "partial": False,
    }
    assert configs["production"] == {
        "site_key": "",
        "secret": "",
        "configured": False,
        "partial": False,
    }
    assert configs["explicit"] == {
        "site_key": "real-site-key",
        "secret": "real-secret",
        "configured": True,
        "partial": False,
    }
    assert configs["partial"] == {
        "site_key": "real-site-key",
        "secret": "",
        "configured": False,
        "partial": True,
    }
