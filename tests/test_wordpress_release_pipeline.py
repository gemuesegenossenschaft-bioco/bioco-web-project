import os
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
SCRIPT = ROOT / "wordpress/scripts/release-wordpress-staging.sh"


def _write_executable(path: Path, body: str) -> Path:
    path.write_text("#!/usr/bin/env bash\nset -euo pipefail\n" + body)
    path.chmod(0o755)
    return path


def _fixture(tmp_path: Path):
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-q"], cwd=repo, check=True)
    subprocess.run(["git", "config", "user.email", "test@example.com"], cwd=repo, check=True)
    subprocess.run(["git", "config", "user.name", "Test"], cwd=repo, check=True)
    (repo / "tracked.txt").write_text("release fixture\n")
    subprocess.run(["git", "add", "tracked.txt"], cwd=repo, check=True)
    subprocess.run(["git", "commit", "-qm", "fixture"], cwd=repo, check=True)
    commit = subprocess.run(
        ["git", "rev-parse", "HEAD"], cwd=repo, text=True, capture_output=True, check=True
    ).stdout.strip()

    events = tmp_path / "events.log"
    preflight = _write_executable(tmp_path / "preflight", 'echo preflight >> "$BIOCO_TEST_EVENTS"\n')
    deploy = _write_executable(
        tmp_path / "deploy",
        'case " $* " in *" --apply "*) echo deploy:apply ;; *) echo deploy:dry ;; esac >> "$BIOCO_TEST_EVENTS"\n',
    )
    ssh = _write_executable(
        tmp_path / "ssh",
        '''
case "$*" in
  *"wp option get siteurl"*)
    event=identity
    printf '%s\n%s\n' "$BIOCO_TEST_SITEURL" "$BIOCO_TEST_SITEURL"
    ;;
  *"wp cache flush"*) event=cache-flush ;;
  *"wp db export"*) event=backup ;;
  *"wp bioco import --apply --force"*) event=import ;;
  *"wp bioco verify"*) event=parity ;;
  *"wp option update bioco_release_marker"*) event=marker ;;
  *) event=unexpected-ssh ;;
esac
echo "$event" >> "$BIOCO_TEST_EVENTS"
[[ "${BIOCO_TEST_FAIL_STEP:-}" != "$event" ]]
''',
    )
    render = _write_executable(tmp_path / "render", 'echo smoke >> "$BIOCO_TEST_EVENTS"\n')
    base_env = {k: v for k, v in os.environ.items() if not k.startswith("BIOCO_")}
    env = base_env | {
        "BIOCO_RELEASE_REPO_ROOT": str(repo),
        "BIOCO_RELEASE_LOG_DIR": str(tmp_path / "logs"),
        "BIOCO_RELEASE_PREFLIGHT_COMMAND": str(preflight),
        "BIOCO_RELEASE_DEPLOY_SCRIPT": str(deploy),
        "BIOCO_RELEASE_SSH_BIN": str(ssh),
        "BIOCO_RELEASE_RENDER_GATE": str(render),
        "BIOCO_RELEASE_TIMESTAMP": "20260817T210000Z",
        "BIOCO_TEST_EVENTS": str(events),
        "BIOCO_WP_HOST": "staging.example.test",
        "BIOCO_WP_USER": "deploy",
        "BIOCO_WP_CONTENT": "/srv/wordpress/wp-content",
        "BIOCO_RELEASE_URL": "https://staging.example.test",
        "BIOCO_TEST_SITEURL": "https://staging.example.test",
    }
    return commit, env, events


def test_release_pipeline_dry_run_is_non_mutating(tmp_path):
    assert SCRIPT.is_file()
    commit, env, events = _fixture(tmp_path)

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode == 0, result.stdout + result.stderr
    assert events.read_text().splitlines() == ["preflight", "identity", "deploy:dry"]
    assert f"commit={commit}" in result.stdout
    assert "mode=dry-run" in result.stdout
    log = Path(env["BIOCO_RELEASE_LOG_DIR"]) / f"20260817T210000Z-{commit}.log"
    assert log.is_file()
    assert "release-status=success" in log.read_text()


def test_release_pipeline_apply_runs_each_step_in_order(tmp_path):
    commit, env, events = _fixture(tmp_path)

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}", "--apply"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode == 0, result.stdout + result.stderr
    assert events.read_text().splitlines() == [
        "preflight",
        "identity",
        "backup",
        "deploy:apply",
        "cache-flush",
        "import",
        "parity",
        "smoke",
        "marker",
    ]
    assert "backup-path=/srv/backups/wordpress-staging/20260817T210000Z-" in result.stdout
    assert f"release-marker={commit}" in result.stdout


def test_release_pipeline_stops_after_first_failed_step(tmp_path):
    commit, env, events = _fixture(tmp_path)
    env["BIOCO_TEST_FAIL_STEP"] = "import"

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}", "--apply"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert events.read_text().splitlines() == [
        "preflight",
        "identity",
        "backup",
        "deploy:apply",
        "cache-flush",
        "import",
    ]
    assert "release-status=failed" in result.stdout
    assert "failed-step=import" in result.stdout
    assert "step=parity" not in result.stdout
    assert "step=release-marker" not in result.stdout
    log = Path(env["BIOCO_RELEASE_LOG_DIR"]) / f"20260817T210000Z-{commit}.log"
    assert "release-status=failed" in log.read_text()
    assert "failed-step=import" in log.read_text()


def test_release_pipeline_rejects_untracked_deploy_inputs(tmp_path):
    commit, env, events = _fixture(tmp_path)
    untracked = Path(env["BIOCO_RELEASE_REPO_ROOT"]) / (
        "wordpress/web/app/mu-plugins/bioco-core/untracked.php"
    )
    untracked.parent.mkdir(parents=True)
    untracked.write_text("<?php echo 'not committed';\n")

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert "untracked release input" in result.stdout
    assert not events.exists()


def test_actions_calls_the_same_release_pipeline():
    workflow = (ROOT / ".github/workflows/deploy-wordpress-staging.yml").read_text()

    assert "wordpress/scripts/release-wordpress-staging.sh" in workflow
    assert "--commit=\"$GITHUB_SHA\"" in workflow
    assert "--apply" in workflow
    assert "composer install" not in workflow
    assert "rsync -avz" not in workflow
    assert "STAGING_SSH_KNOWN_HOSTS" in workflow
    assert "ssh-keyscan" not in workflow
    uses_lines = [line.strip() for line in workflow.splitlines() if line.strip().startswith("- uses:")]
    assert uses_lines
    for line in uses_lines:
        assert re.search(r"@[0-9a-f]{40}\b", line), line


def test_preflight_lints_every_deployed_php_source():
    preflight = (ROOT / "wordpress/scripts/release-wordpress-preflight.sh").read_text()

    for source in (
        "bioco-core",
        "bioco-content",
        "bioco-forms",
        "bioco-import",
        "themes/bioco",
        "themes/bioco-divi",
        "deploy/bioco-mu-loader.php",
    ):
        assert source in preflight


def test_preflight_logs_counts_reported_by_seed_gate():
    preflight = (ROOT / "wordpress/scripts/release-wordpress-preflight.sh").read_text()

    assert "pages=22 blocks=110" not in preflight
    assert 'pages=${page_count} blocks=${block_count}' in preflight


def test_release_marker_is_one_atomic_option_write():
    release = SCRIPT.read_text()

    assert "wp option update bioco_release_marker" in release
    assert "bioco_release_commit" not in release
    assert "bioco_release_backup" not in release


def test_release_pipeline_refuses_a_wrong_site_before_any_mutation(tmp_path):
    commit, env, events = _fixture(tmp_path)
    env["BIOCO_TEST_SITEURL"] = "https://www.bioco.ch"

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}", "--apply"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert events.read_text().splitlines() == ["preflight", "identity"]
    assert "failed-step=identity" in result.stdout
    assert "https://www.bioco.ch" in result.stdout + result.stderr


def test_release_pipeline_rejects_ignored_deploy_inputs(tmp_path):
    commit, env, events = _fixture(tmp_path)
    repo = Path(env["BIOCO_RELEASE_REPO_ROOT"])
    ignored = repo / "wordpress/web/app/mu-plugins/bioco-core/.env"
    ignored.parent.mkdir(parents=True)
    ignored.write_text("BIOCO_SECRET=must-never-ship\n")
    (repo / ".gitignore").write_text(".env\n")
    subprocess.run(["git", "add", ".gitignore"], cwd=repo, check=True)
    subprocess.run(["git", "commit", "-qm", "ignore env"], cwd=repo, check=True)
    commit = subprocess.run(
        ["git", "rev-parse", "HEAD"], cwd=repo, text=True, capture_output=True, check=True
    ).stdout.strip()

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert "untracked release input" in result.stdout
    assert "bioco-core/.env" in result.stdout
    assert not events.exists()


REQUIREMENTS = ROOT / ".github/workflows/requirements-ci.txt"


def test_actions_installs_only_hash_pinned_test_dependencies():
    workflow = (ROOT / ".github/workflows/deploy-wordpress-staging.yml").read_text()

    assert "pip install pytest" not in workflow
    assert "--require-hashes" in workflow
    assert ".github/workflows/requirements-ci.txt" in workflow


def test_ci_requirements_pin_every_dependency_by_hash():
    assert REQUIREMENTS.is_file()
    entries = [
        line.strip()
        for line in REQUIREMENTS.read_text().replace("\\\n", " ").splitlines()
        if line.strip() and not line.strip().startswith("#")
    ]

    assert entries, "requirements file must pin at least pytest"
    for entry in entries:
        assert "==" in entry, entry
        assert "--hash=sha256:" in entry, entry

    pinned = {entry.split("==", 1)[0].lower() for entry in entries}
    assert {"pytest", "iniconfig", "packaging", "pluggy"} <= pinned


def test_release_pipeline_dry_run_refuses_a_wrong_site(tmp_path):
    commit, env, events = _fixture(tmp_path)
    env["BIOCO_TEST_SITEURL"] = "https://www.bioco.ch"

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert events.read_text().splitlines() == ["preflight", "identity"]
    assert "deploy:dry" not in events.read_text()


def test_release_pipeline_rejects_an_unsafe_ssh_port(tmp_path):
    commit, env, events = _fixture(tmp_path)
    env["BIOCO_WP_SSH_PORT"] = "22; rm -rf /"

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert "unsafe release target" in result.stdout
    assert not events.exists()


def test_render_gate_default_follows_the_configured_repo_root():
    release = SCRIPT.read_text()

    assert "${default_repo_root}/tests/" not in release
    assert "${repo_root}/tests/wordpress-staging-render-gate.sh" in release


def test_release_warns_when_no_opcache_reset_is_configured(tmp_path):
    commit, env, events = _fixture(tmp_path)

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}", "--apply"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode == 0, result.stdout + result.stderr
    assert "opcache-reset=skipped" in result.stdout


def test_code_sync_never_ships_gitignored_files():
    deploy = (ROOT / "wordpress/scripts/deploy-wp-code.sh").read_text()

    assert "--filter=:- .gitignore" in deploy
    assert "--exclude=.env" in deploy


def test_release_uses_one_timestamp_for_log_backup_and_marker(tmp_path):
    commit, env, events = _fixture(tmp_path)
    del env["BIOCO_RELEASE_TIMESTAMP"]

    result = subprocess.run(
        [str(SCRIPT), f"--commit={commit}", "--apply"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode == 0, result.stdout + result.stderr
    logs = list(Path(env["BIOCO_RELEASE_LOG_DIR"]).glob("*.log"))
    assert len(logs) == 1
    log_stamp = logs[0].name.split("-")[0]
    backup_stamp = re.search(r"backup-path=\S*/(\d{8}T\d{6}Z)-", result.stdout).group(1)
    assert log_stamp == backup_stamp
    assert f"release-start timestamp={log_stamp}" in result.stdout


def test_backup_is_written_with_a_restrictive_umask():
    release = SCRIPT.read_text()

    assert "umask 077" in release


def test_actions_job_is_hardened_and_time_boxed():
    workflow = (ROOT / ".github/workflows/deploy-wordpress-staging.yml").read_text()

    assert "persist-credentials: false" in workflow
    assert "timeout-minutes:" in workflow


def test_seed_plan_gate_reports_why_it_aborted():
    preflight = (ROOT / "wordpress/scripts/release-wordpress-preflight.sh").read_text()

    # `read` returns 1 when sed matched nothing; under `set -e` that would abort
    # before the explanatory message is ever printed.
    assert "read -r block_count page_count < <(" in preflight
    assert "|| block_count=\"\"" in preflight
