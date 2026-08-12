import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
CALCULATOR_FIELDS = (
    ROOT
    / "wordpress/web/app/mu-plugins/bioco-core/acf-json"
    / "group_bioco_block_pricing_calculator.json"
)
MEMBERSHIP_JS = (
    ROOT
    / "wordpress/web/app/mu-plugins/bioco-core/blocks"
    / "membership-form/view.js"
)
FORMS_PLUGIN = (
    ROOT / "wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php"
)


def membership_selection(search):
    script = r'''
    const fs = require('fs');
    const vm = require('vm');
    const inputs = Object.fromEntries(
      ['membershipType', 'aboType', 'additionalShares', 'sharesOnly']
        .map(name => [name, { name, value: '' }])
    );
    const form = {
      getAttribute: () => '',
      parentNode: { querySelector: () => ({}) },
      querySelector: selector => {
        const match = selector.match(/^\[name="([^"]+)"\]$/);
        return match ? inputs[match[1]] : null;
      },
      addEventListener: () => {},
    };
    const document = {
      readyState: 'complete',
      querySelectorAll: selector => selector.includes('membership-form') ? [form] : [],
    };
    const context = {
      document,
      window: { location: { search: process.argv[2] } },
      URLSearchParams,
      console,
    };
    vm.runInNewContext(fs.readFileSync(process.argv[1], 'utf8'), context);
    process.stdout.write(JSON.stringify(inputs));
    '''
    return json.loads(subprocess.run(
        ["node", "-e", script, str(MEMBERSHIP_JS), search],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout)


def test_pricing_calculator_targets_membership_form():
    fields = json.loads(CALCULATOR_FIELDS.read_text())["fields"]
    signup = next(field for field in fields if field["name"] == "signup_url")

    assert signup["default_value"] == "/anmeldung"


def test_membership_form_receives_calculator_selection():
    normal = membership_selection(
        "?abo=doppel-4-6-personen&shares=6&additional=2"
    )
    assert normal["membershipType"]["value"] == "abo"
    assert normal["aboType"]["value"] == "doppel"
    assert normal["additionalShares"]["value"] == "2"
    assert normal["sharesOnly"]["value"] == "0"

    shares_only = membership_selection("?abo=kein&shares=3&additional=3")
    assert shares_only["membershipType"]["value"] == "shares-only"
    assert shares_only["aboType"]["value"] == "none"
    assert shares_only["additionalShares"]["value"] == "0"
    assert shares_only["sharesOnly"]["value"] == "3"

    partial = membership_selection(
        "?abo=doppel-4-6-personen&additional=2evil"
    )
    assert partial["additionalShares"]["value"] == "0"
    minimum = membership_selection("?abo=kein&shares=0")
    assert minimum["sharesOnly"]["value"] == "1"
    bounded = membership_selection("?abo=kein&shares=999")
    assert bounded["sharesOnly"]["value"] == "100"

    source = MEMBERSHIP_JS.read_text()
    assert "'/anmeldung-danke/'" in source
    assert "'/anmeldung/danke'" not in source


def membership_contract(data):
    php = r'''
    define('ABSPATH', __DIR__);
    function add_action() {}
    function sanitize_text_field($value) { return trim((string) $value); }
    function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
    require 'wordpress/web/app/mu-plugins/bioco-forms/bioco-forms.php';
    $data = json_decode($argv[1], true);
    echo json_encode([
        'validation' => bioco_forms_validate_membership($data),
        'total' => bioco_forms_membership_total_shares($data),
    ]);
    '''
    return json.loads(subprocess.run(
        ["php", "-r", php, json.dumps(data)],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    ).stdout)


def membership_validation(data):
    return membership_contract(data)["validation"]


def valid_membership(**updates):
    data = {
        "firstName": "Stage",
        "lastName": "Test",
        "email": "stage@example.com",
        "address": "Testweg 1",
        "zip": "5400",
        "city": "Baden",
        "privacyAccept": True,
        "membershipType": "abo",
        "aboType": "standard",
        "additionalShares": 0,
        "sharesOnly": 0,
    }
    data.update(updates)
    return data


def test_membership_server_rejects_tampered_selection():
    assert membership_validation(valid_membership())["ok"] is True
    assert membership_validation(valid_membership(
        membershipType="shares-only", aboType="none", sharesOnly=1
    ))["ok"] is True
    old_payload = valid_membership()
    del old_payload["sharesOnly"]
    assert membership_validation(old_payload)["ok"] is True
    assert membership_validation(valid_membership(
        additionalShares="2", sharesOnly="0"
    ))["ok"] is True
    assert membership_contract(valid_membership(
        aboType="doppel", additionalShares="2", sharesOnly="0"
    ))["total"] == 6
    assert membership_contract(valid_membership(
        membershipType="shares-only", aboType="none",
        additionalShares="0", sharesOnly="3"
    ))["total"] == 3

    invalid = [
        {"membershipType": "tampered"},
        {"membershipType": "abo", "aboType": "none"},
        {"membershipType": "shares-only", "aboType": "standard", "sharesOnly": 1},
        {"membershipType": "shares-only", "aboType": "none", "sharesOnly": 0},
        {"membershipType": "shares-only", "aboType": "none", "sharesOnly": 101},
        {"additionalShares": -1},
        {"additionalShares": 101},
        {"additionalShares": "2evil"},
    ]
    for update in invalid:
        result = membership_validation(valid_membership(**update))
        assert result["ok"] is False, update
        assert "membershipSelection" in result["errors"], update
