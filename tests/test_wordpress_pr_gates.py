"""PR workflow contracts and executable trigger/report gates.

Keep/Replace/Remove map: tests/README.md. Workflow structure is configuration;
the security decision and skipped-test decision execute their real CI code.
"""

import json
import re
import subprocess
import sys
from pathlib import Path
from xml.etree import ElementTree as ET

import pytest
import yaml

ROOT = Path(__file__).resolve().parents[1]
WORKFLOWS = ROOT / ".github/workflows"


def workflow(name):
    # BaseLoader preserves GitHub's `on` key instead of treating it as YAML 1.1 true.
    return yaml.load((WORKFLOWS / name).read_text(), Loader=yaml.BaseLoader)


def test_pr_workflow_runs_on_every_wordpress_pr_without_credentials():
    config = workflow("validate-wordpress-pr.yml")
    assert config["on"] == {"pull_request": {"branches": ["wordpress"]}}
    assert config["permissions"] == {"contents": "read"}
    assert "github.event.pull_request.number" in config["concurrency"]["group"]
    assert config["concurrency"]["cancel-in-progress"] == "true"
    assert {job["name"] for job in config["jobs"].values()} == {
        "WordPress tests", "WordPress PHP and shell lint",
        "WordPress generation and conformance",
    }
    for job in config["jobs"].values():
        assert "environment" not in job
        assert "permissions" not in job
        assert "if" not in job
        for step in job["steps"]:
            assert step.get("continue-on-error", "false") == "false"
            if "uses" in step:
                assert re.fullmatch(r"[\w./-]+@[0-9a-f]{40}", step["uses"])
            if step.get("uses", "").startswith("actions/checkout@"):
                assert step["with"]["persist-credentials"] == "false"
    text = (WORKFLOWS / "validate-wordpress-pr.yml").read_text()
    assert "secrets." not in text
    assert "STAGING_" not in text
    assert "release-wordpress-staging.sh" not in text
    assert "https://bioco.ch" not in text


def test_pr_tests_install_release_dependencies_and_keep_failure_evidence():
    job = workflow("validate-wordpress-pr.yml")["jobs"]["tests"]
    steps = job["steps"]
    commands = "\n".join(step.get("run", "") for step in steps)
    assert "--require-hashes -r .github/workflows/requirements-ci.txt" in commands
    assert "playwright install --with-deps chromium" in commands
    assert "apt-get install -y apache2" in commands
    assert "python3 -m pytest tests -q --junitxml=output/pr-gates/pytest.xml" in commands
    assert job["env"]["BIOCO_APACHE_TESTS"] == "1"
    assert job["env"]["BIOCO_BUTTON_BROWSER_TESTS"] == "1"
    assert any(step.get("with", {}).get("php-version") == "8.2" for step in steps)
    assert any(step.get("with", {}).get("extensions") == "dom" for step in steps)
    forms = next(step for step in steps if step.get("name") == "Run isolated form browsers")
    assert forms["env"]["BIOCO_FORMS_BROWSER_TESTS"] == "1"
    assert forms["if"] == "${{ !cancelled() }}"
    policy = json.loads((WORKFLOWS / "wordpress-required-tests.json").read_text())
    for node in policy["operator_only_nodes"]:
        assert f"--deselect={node}" in forms["run"]
    guard = next(step for step in steps if step.get("name") == "Reject skipped required coverage")
    assert guard["if"] == "${{ !cancelled() }}"
    assert "check-required-tests.py" in guard["run"]
    assert "pytest.xml" in guard["run"] and "forms.xml" in guard["run"]
    upload = next(step for step in steps if step.get("uses", "").startswith("actions/upload-artifact@"))
    assert upload["if"] == "${{ failure() }}"
    assert upload["with"]["path"] == "output/"
    assert upload["with"]["retention-days"] == "14"
    assert "github.event.pull_request.head.sha" in upload["with"]["name"]


def test_workflow_lint_steps_parse_as_bash_and_fail_on_bad_php(tmp_path):
    job = workflow("validate-wordpress-pr.yml")["jobs"]["lint"]
    php_step = next(step["run"] for step in job["steps"] if step.get("name") == "Lint owned PHP")
    shell_step = next(step["run"] for step in job["steps"] if step.get("name") == "Lint shell scripts")
    for directory in ("wordpress/web/app/mu-plugins", "wordpress/web/app/themes/bioco-divi",
                      "scripts", "tests", "wordpress/scripts"):
        (tmp_path / directory).mkdir(parents=True, exist_ok=True)
    php_file = tmp_path / "wordpress/web/app/mu-plugins/broken.php"
    php_file.write_text("<?php function broken( {\n")
    result = subprocess.run(["bash", "-eo", "pipefail", "-c", php_step], cwd=tmp_path, capture_output=True)
    assert result.returncode != 0
    php_file.write_text("<?php function valid() {}\n")
    subprocess.run(["bash", "-eo", "pipefail", "-c", php_step], cwd=tmp_path, check=True, capture_output=True)
    script = tmp_path / "wordpress/scripts/broken.sh"
    script.write_text("if then\n")
    result = subprocess.run(["bash", "-eo", "pipefail", "-c", shell_step], cwd=tmp_path, capture_output=True)
    assert result.returncode != 0
    script.write_text("#!/usr/bin/env bash\nexit 0\n")
    subprocess.run(["bash", "-eo", "pipefail", "-c", shell_step], cwd=tmp_path, check=True, capture_output=True)
    for filename in ("validate-wordpress-pr.yml", "claude.yml", "claude-task.yml"):
        for job in workflow(filename)["jobs"].values():
            for step in job["steps"]:
                if "run" in step:
                    subprocess.run(["bash", "-n"], input=step["run"], text=True, check=True)


def test_conformance_runs_the_existing_read_only_gates():
    job = workflow("validate-wordpress-pr.yml")["jobs"]["conformance"]
    commands = "\n".join(step.get("run", "") for step in job["steps"])
    for command in (
        "python3 wordpress/scripts/build-divi-design-system.py --check",
        "python3 wordpress/scripts/check-divi-design-system.py",
        "php wordpress/scripts/check-hardcoded-content.php",
        "php wordpress/scripts/check-seed-plan.php",
        "diff -ru wordpress/content-seed cms/content-seed",
    ):
        assert command in commands
    # Generators without a --check mode are already executed by the full suite.
    assert "build-native-modules.py" not in commands
    upload = next(step for step in job["steps"] if step.get("uses", "").startswith("actions/upload-artifact@"))
    assert upload["if"] == "${{ failure() }}"


def write_report(path, rows):
    suite = ET.Element("testsuite")
    for module, name, status in rows:
        case = ET.SubElement(suite, "testcase", classname=f"tests.{module}", name=name)
        if status != "passed":
            ET.SubElement(case, status)
    ET.ElementTree(suite).write(path)


def report_gate(tmp_path, rows, second_rows=None):
    policy = tmp_path / "policy.json"
    policy.write_text(json.dumps({
        "required_modules": ["tests/test_example.py", "tests/test_browser.py"],
        "operator_only_nodes": ["tests/test_browser.py::test_local_wordpress"],
    }))
    report = tmp_path / "report.xml"
    write_report(report, rows)
    args = [sys.executable, str(WORKFLOWS / "check-required-tests.py"), "--policy", str(policy), str(report)]
    if second_rows is not None:
        second = tmp_path / "second.xml"
        write_report(second, second_rows)
        args.append(str(second))
    return subprocess.run(args, capture_output=True, text=True)


def test_required_reports_accept_passes_from_both_runs(tmp_path):
    result = report_gate(tmp_path, [
        ("test_example", "test_ok", "passed"), ("test_browser", "test_fixture", "skipped"),
        ("test_browser", "test_local_wordpress", "skipped"),
    ], [("test_browser", "test_fixture", "passed")])
    assert result.returncode == 0, result.stdout + result.stderr
    assert "operator follow-up" in result.stdout


@pytest.mark.parametrize("rows,reason", [
    ([("test_example", "test_ok", "passed")], "missing"),
    ([("test_example", "test_ok", "passed"), ("test_browser", "test_fixture", "skipped")], "skipped"),
    ([("test_example", "test_ok", "passed"), ("test_browser", "test_one", "passed"),
      ("test_browser", "test_two", "skipped")], "skipped"),
    ([("test_example", "test_ok", "passed"), ("test_browser", "test_fixture", "failure")], "failed"),
    ([("test_example", "test_ok", "passed"), ("test_browser", "test_fixture", "error")], "failed"),
])
def test_required_reports_reject_controlled_failures(tmp_path, rows, reason):
    result = report_gate(tmp_path, rows)
    assert result.returncode != 0
    assert reason in result.stdout + result.stderr


def test_required_report_cannot_hide_a_failure_with_a_later_pass(tmp_path):
    result = report_gate(tmp_path, [
        ("test_example", "test_ok", "passed"), ("test_browser", "test_fixture", "failure"),
    ], [("test_browser", "test_fixture", "passed")])
    assert result.returncode != 0


@pytest.mark.parametrize("contents", [None, "<broken"])
def test_required_reports_fail_if_junit_is_absent_or_malformed(tmp_path, contents):
    policy = tmp_path / "policy.json"
    policy.write_text(json.dumps({
        "required_modules": ["tests/test_example.py"],
        "operator_only_nodes": [],
    }))
    valid = tmp_path / "valid.xml"
    write_report(valid, [("test_example", "test_ok", "passed")])
    args = [sys.executable, str(WORKFLOWS / "check-required-tests.py"),
            "--policy", str(policy), str(valid)]
    green = subprocess.run(args, capture_output=True, text=True)
    assert green.returncode == 0, green.stdout + green.stderr
    report = tmp_path / "missing.xml"
    if contents is not None:
        report.write_text(contents)
    result = subprocess.run(args + [str(report)], capture_output=True, text=True)
    assert result.returncode != 0
    assert f"missing or malformed report {report}:" in result.stdout + result.stderr


def test_report_gate_consumes_real_pytest_pass_and_entire_module_skip(tmp_path):
    tests = tmp_path / "tests"
    tests.mkdir()
    (tests / "test_example.py").write_text("def test_ok():\n    assert 1 + 1 == 2\n")
    browser = tests / "test_browser.py"
    browser.write_text("def test_fixture():\n    assert True\n")
    policy = tmp_path / "policy.json"
    policy.write_text(json.dumps({
        "required_modules": ["tests/test_example.py", "tests/test_browser.py"],
        "operator_only_nodes": [],
    }))
    report = tmp_path / "real.xml"
    pytest_args = [sys.executable, "-m", "pytest", "tests", "-q", f"--junitxml={report}"]
    guard_args = [sys.executable, str(WORKFLOWS / "check-required-tests.py"),
                  "--policy", str(policy), str(report)]
    subprocess.run(pytest_args, cwd=tmp_path, check=True, capture_output=True)
    green = subprocess.run(guard_args, text=True, capture_output=True)
    assert green.returncode == 0, green.stdout + green.stderr
    browser.write_text(
        "import pytest\npytestmark = pytest.mark.skip(reason='controlled missing browser')\n"
        "def test_fixture():\n    assert True\n"
    )
    # Pytest itself exits successfully when this required module is all skipped.
    subprocess.run(pytest_args, cwd=tmp_path, check=True, capture_output=True)
    red = subprocess.run(guard_args, text=True, capture_output=True)
    assert red.returncode == 1
    assert "entire required module skipped" in red.stdout


def test_required_policy_references_existing_modules_and_only_declared_runtime_exclusions():
    policy = json.loads((WORKFLOWS / "wordpress-required-tests.json").read_text())
    assert len(policy["required_modules"]) == len(set(policy["required_modules"]))
    for module in policy["required_modules"]:
        assert (ROOT / module).is_file()
    assert "tests/test_wordpress_visual_parity.py" in policy["required_modules"]
    assert len(policy["operator_only_nodes"]) == 3
    assert all(node.startswith("tests/test_wordpress_forms_lifecycle.py::test_wordpress_")
               for node in policy["operator_only_nodes"])


def authorize(event_name, payload, permission="write"):
    config = workflow("claude.yml")
    script = config["jobs"]["authorize"]["steps"][0]["with"]["script"]
    harness = r"""
const fs = require('fs');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const outputs = {};
const core = {setOutput: (key, value) => outputs[key] = String(value), info: () => {}};
const context = {eventName: input.event_name, payload: input.payload, repo: {owner: 'org', repo: 'repo'}};
const github = {rest: {repos: {getCollaboratorPermissionLevel: async () => {
  if (input.permission === 'error') throw new Error('API unavailable');
  return {data: {permission: input.permission}};
}}}};
const AsyncFunction = Object.getPrototypeOf(async function(){}).constructor;
(async () => {await new AsyncFunction('github', 'context', 'core', input.script)(github, context, core);
process.stdout.write(JSON.stringify(outputs));})().catch(error => {console.error(error); process.exit(1);});
"""
    result = subprocess.run(["node", "-e", harness], input=json.dumps({
        "script": script, "event_name": event_name, "payload": payload, "permission": permission,
    }), text=True, capture_output=True, check=True)
    return json.loads(result.stdout)["authorized"] == "true"


def payload(kind="comment", body="@claude fix this", association="COLLABORATOR", action="created"):
    return {"action": action, "sender": {"login": "maintainer", "type": "User"},
            kind: {"body": body, "author_association": association,
                   "user": {"login": "maintainer", "type": "User"}}}


@pytest.mark.parametrize("event,kind,action", [
    ("issue_comment", "comment", "created"), ("issue_comment", "comment", "edited"),
    ("pull_request_review_comment", "comment", "created"),
    ("pull_request_review", "review", "submitted"), ("issues", "issue", "opened"),
])
def test_explicit_writer_requests_authorize_claude(event, kind, action):
    assert authorize(event, payload(kind, action=action))
    assert authorize(event, payload(kind, action=action), "admin")
    assert authorize(event, payload(kind, action=action), "maintain")


@pytest.mark.parametrize("body", ["@claude.", "@CLAUDE. Fix this", "@claude, fix",
                                  "@claude: fix", "@claude; fix", "@claude!", "@claude?"])
def test_claude_mentions_accept_trailing_punctuation(body):
    assert authorize("issue_comment", payload(body=body))


@pytest.mark.parametrize("title,body", [
    ("@claude. Fix this", "No mention here"), ("@claude fix this", None),
    ("Ordinary title", "@claude. Fix this"),
])
def test_opened_issues_authorize_mentions_in_title_or_body(title, body):
    event = payload("issue", body=body, action="opened")
    event["issue"]["title"] = title
    assert authorize("issues", event)
    assert not authorize("issues", event, "triage")


@pytest.mark.parametrize("association,permission,body", [
    ("NONE", "write", "@claude fix"), ("CONTRIBUTOR", "write", "@claude fix"),
    ("MEMBER", "read", "@claude fix"), ("COLLABORATOR", "triage", "@claude fix"),
    ("OWNER", "error", "@claude fix"), ("OWNER", "write", "fix this"),
    ("OWNER", "write", "@claude-other fix"), ("OWNER", "write", "mail@claude.example"),
])
def test_untrusted_or_nonexplicit_requests_do_not_authorize(association, permission, body):
    assert not authorize("issue_comment", payload(body=body, association=association), permission)


def test_ordinary_assignment_never_authorizes_even_with_mention_in_issue():
    event = payload("issue", action="assigned")
    event["assignee"] = {"login": "ordinary-maintainer"}
    assert not authorize("issues", event)
    event["assignee"]["login"] = "claude[bot]"
    assert authorize("issues", event)
    assert not authorize("issues", event, "triage")
    event["sender"]["type"] = "Bot"
    assert not authorize("issues", event)


def test_bot_and_edited_someone_elses_request_never_authorize():
    event = payload()
    event["sender"]["type"] = "Bot"
    assert not authorize("issue_comment", event)
    event["sender"]["type"] = "User"
    event["comment"]["user"]["login"] = "someone-else"
    assert not authorize("issue_comment", event)


def test_claude_permissions_are_scoped_to_authorized_jobs_and_actions_pinned():
    config = workflow("claude.yml")
    assert config["permissions"] == {"contents": "read"}
    assert config["jobs"]["authorize"].get("permissions", config["permissions"]) == {"contents": "read"}
    assert config["jobs"]["claude"]["needs"] == "authorize"
    assert config["jobs"]["claude"]["if"] == "needs.authorize.outputs.authorized == 'true'"
    manual = workflow("claude-task.yml")
    assert set(manual["on"]) == {"workflow_dispatch"}
    assert manual["permissions"] == {"contents": "read"}
    for job in (config["jobs"]["claude"], manual["jobs"]["claude-task"]):
        assert job["permissions"] == {
            "contents": "write", "pull-requests": "write", "issues": "write", "id-token": "write",
        }
        for step in job["steps"]:
            if "uses" in step:
                assert re.fullmatch(r"[\w./-]+@[0-9a-f]{40}", step["uses"])
            if step.get("uses", "").startswith("actions/checkout@"):
                assert step["with"]["persist-credentials"] == "false"
            if step.get("uses", "").startswith("anthropics/claude-code-action@"):
                assert "allowed_non_write_users" not in step["with"]
    action = config["jobs"]["claude"]["steps"][-1]
    assert action["with"]["assignee_trigger"] == "claude[bot]"
