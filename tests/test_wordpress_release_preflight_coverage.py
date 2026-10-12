"""Execute the release preflight with fixture JUnit and its real required-test gate."""

import os
import shutil
import subprocess
import sys
from pathlib import Path

import pytest
import yaml


ROOT = Path(__file__).resolve().parents[1]
POLICY = ROOT / ".github/workflows/wordpress-required-tests.json"
CHECKER = ROOT / ".github/workflows/check-required-tests.py"
PREFLIGHT = ROOT / "wordpress/scripts/release-wordpress-preflight.sh"


@pytest.mark.parametrize(
    ("scenario", "expected"),
    [
        ("skipped", "skipped required test"),
        ("failed", "failed required test"),
        ("missing", "missing or malformed report"),
    ],
)
def test_preflight_blocks_incomplete_required_coverage(tmp_path, scenario, expected):
    repo = tmp_path / "repo"
    for source in (POLICY, CHECKER, PREFLIGHT):
        target = repo / source.relative_to(ROOT)
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
    shim_dir = tmp_path / "bin"
    shim_dir.mkdir()
    shim = shim_dir / "python3"
    shim.write_text(
        "#!/usr/bin/env bash\n"
        'if [[ "$1" == "-m" && "$2" == "pytest" ]]; then\n'
        '  exec "$BIOCO_TEST_PYTHON" "$BIOCO_TEST_GENERATOR" "${@:3}"\n'
        "fi\n"
        'exec "$BIOCO_TEST_PYTHON" "$@"\n'
    )
    shim.chmod(0o755)
    generator = tmp_path / "generate.py"
    generator.write_text(
        "import json, os, sys\n"
        "from pathlib import Path\n"
        "from xml.etree import ElementTree as ET\n"
        "args = sys.argv[1:]\n"
        "report = Path(next(x.split('=', 1)[1] for x in args if x.startswith('--junitxml=')))\n"
        "forms = report.name == 'forms.xml'\n"
        "assert os.environ['BIOCO_APACHE_TESTS'] == '1'\n"
        "assert os.environ['BIOCO_BUTTON_BROWSER_TESTS'] == '1'\n"
        "assert os.environ['BIOCO_FORMS_BROWSER_TESTS'] == ('1' if forms else '0')\n"
        "assert ('tests/test_wordpress_forms_lifecycle.py' in args) == forms\n"
        "policy = json.loads(Path('.github/workflows/wordpress-required-tests.json').read_text())\n"
        "if forms:\n"
        "    assert {x.removeprefix('--deselect=') for x in args if x.startswith('--deselect=')} == set(policy['operator_only_nodes'])\n"
        "if forms and os.environ['BIOCO_TEST_SCENARIO'] == 'missing':\n"
        "    sys.exit(0)\n"
        "suite = ET.Element('testsuite')\n"
        "for index, path in enumerate(policy['required_modules'] if not forms else ['tests/test_wordpress_forms_lifecycle.py']):\n"
        "    case = ET.SubElement(suite, 'testcase', classname=path[:-3].replace('/', '.'), name='test_fixture')\n"
        "    if not forms and index == 0 and os.environ['BIOCO_TEST_SCENARIO'] in ('skipped', 'failed'):\n"
        "        ET.SubElement(case, os.environ['BIOCO_TEST_SCENARIO'] if os.environ['BIOCO_TEST_SCENARIO'] == 'skipped' else 'failure')\n"
        "ET.ElementTree(suite).write(report, encoding='unicode')\n"
    )
    env = os.environ.copy()
    env.update(
        BIOCO_RELEASE_REPO_ROOT=str(repo),
        BIOCO_TEST_PYTHON=sys.executable,
        BIOCO_TEST_GENERATOR=str(generator),
        BIOCO_TEST_SCENARIO=scenario,
        PATH=str(shim_dir) + os.pathsep + env["PATH"],
    )
    result = subprocess.run(["bash", str(repo / PREFLIGHT.relative_to(ROOT))],
                            cwd=repo, env=env, text=True, capture_output=True)
    assert result.returncode != 0, result.stdout + result.stderr
    assert expected in result.stdout + result.stderr
    assert "gate=hardcoded-content status=running" not in result.stdout
    assert "gate=required-tests status=passed" not in result.stdout


def test_release_workflows_install_required_browser_runtimes():
    for name in ("deploy-wordpress-staging.yml", "deploy-wordpress-production.yml"):
        workflow = yaml.safe_load((ROOT / ".github/workflows" / name).read_text())
        steps = workflow["jobs"]["release" if "staging" in name else "promote"]["steps"]
        assert any(step.get("uses", "").startswith("actions/setup-node@") and
                   step.get("with", {}).get("node-version") in ("22", 22) for step in steps)
        assert any(step.get("with", {}).get("extensions") == "dom" for step in steps)
