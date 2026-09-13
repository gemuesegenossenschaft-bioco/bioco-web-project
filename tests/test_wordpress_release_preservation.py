import json
import os
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[1]
SCRIPT = ROOT / "wordpress/scripts/release-wordpress-staging.sh"


def _write_executable(path: Path, body: str) -> Path:
    path.write_text(body)
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
    remotes = tmp_path / "remotes.log"
    marker = tmp_path / "marker.log"

    # Honest external-command boundary, validated as an EXACT grammar: the
    # adapter matches the COMPLETE remote command (with the fixture's known
    # dynamic root/backup/marker arguments) — substring matching would let an
    # appended compound command inherit an allowed step's clearance. No state
    # is simulated here; actual WordPress data preservation is verified by
    # the root on staging with before/after data hashes.
    ssh = _write_executable(
        tmp_path / "ssh",
        r'''#!/usr/bin/env bash
printf '%s\n' "$*" >> "$BIOCO_TEST_REMOTES"
cmd="${@: -1}"
wp_root="${BIOCO_WP_CONTENT%/wp-content}"
ts="${BIOCO_RELEASE_TIMESTAMP}"
commit="${BIOCO_TEST_COMMIT}"
backup="${BIOCO_RELEASE_BACKUP_DIR}/${ts}-${commit}.sql"
marker_json="{\"commit\":\"${commit}\",\"backup\":\"${backup}\",\"timestamp\":\"${ts}\",\"verify\":\"passed\",\"smoke\":\"passed\"}"

case "$cmd" in
  *"wp bioco import"*)
    echo "unexpected-import: wp bioco import is forbidden inside a release" >&2
    echo unexpected-import >> "$BIOCO_TEST_EVENTS"
    exit 1
    ;;
  "set -eu; cd '${wp_root}'; wp option get siteurl; wp option get home")
    event=identity
    printf '%s\n%s\n' "$BIOCO_TEST_SITEURL" "$BIOCO_TEST_SITEURL"
    ;;
  "set -eu; umask 077; mkdir -p '${BIOCO_RELEASE_BACKUP_DIR}'; cd '${wp_root}'; wp db export '${backup}' --quiet; test -s '${backup}'")
    event=backup
    ;;
  "set -eu; cd '${wp_root}'; wp cache flush") event=cache-flush ;;
  "set -eu; cd '${wp_root}'; wp bioco verify --runtime")
    event=runtime-verify
    if [[ "${BIOCO_TEST_RUNTIME_VERIFY:-pass}" == "fail" ]]; then
      echo "$event" >> "$BIOCO_TEST_EVENTS"
      echo "FEHLER: Runtime-Pruefung fehlgeschlagen (Testadapter)." >&2
      exit 1
    fi
    ;;
  "set -eu; cd '${wp_root}'; wp option update bioco_release_marker '${marker_json}' --format=json >/dev/null")
    event=marker
    printf '%s\n' "$cmd" >> "$BIOCO_TEST_MARKER"
    ;;
  *)
    echo "unexpected-remote: ${cmd}" >&2
    echo unexpected-ssh >> "$BIOCO_TEST_EVENTS"
    exit 1
    ;;
esac
echo "$event" >> "$BIOCO_TEST_EVENTS"
[[ "${BIOCO_TEST_FAIL_STEP:-}" != "$event" ]]
''',
    )
    preflight = _write_executable(tmp_path / "preflight", 'echo preflight >> "$BIOCO_TEST_EVENTS"\n')
    deploy = _write_executable(
        tmp_path / "deploy",
        'case " $* " in *" --apply "*) echo deploy:apply ;; *) echo deploy:dry ;; esac >> "$BIOCO_TEST_EVENTS"\n',
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
        "BIOCO_RELEASE_TIMESTAMP": "20260913T120000Z",
        "BIOCO_RELEASE_BACKUP_DIR": str(tmp_path / "backups"),
        "BIOCO_TEST_EVENTS": str(events),
        "BIOCO_TEST_REMOTES": str(remotes),
        "BIOCO_TEST_MARKER": str(marker),
        "BIOCO_WP_HOST": "staging.example.test",
        "BIOCO_WP_USER": "deploy",
        "BIOCO_WP_CONTENT": "/srv/wordpress/wp-content",
        "BIOCO_RELEASE_URL": "https://staging.example.test",
        "BIOCO_TEST_SITEURL": "https://staging.example.test",
        "BIOCO_TEST_COMMIT": commit,
    }
    return commit, env, events, remotes, marker


def _run_release(env, *args):
    return subprocess.run(
        [str(SCRIPT), *args],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )


def test_staging_release_runs_only_the_allowed_remote_commands(tmp_path):
    commit, env, events, remotes, marker = _fixture(tmp_path)

    result = _run_release(env, f"--commit={commit}", "--apply")

    assert result.returncode == 0, result.stdout + result.stderr
    ts = "20260913T120000Z"
    ssh_prefix = "-p 22 -o BatchMode=yes -o ConnectTimeout=10 deploy@staging.example.test"
    wp_root = "/srv/wordpress"
    backup_dir = tmp_path / "backups"
    backup = f"{backup_dir}/{ts}-{commit}.sql"
    marker_json = json.dumps(
        {"commit": commit, "backup": backup, "timestamp": ts, "verify": "passed", "smoke": "passed"},
        separators=(",", ":"),
    )
    # The COMPLETE remote-command grammar, from the real run, with the
    # fixture's known dynamic root/backup/marker arguments — in order.
    expected_remotes = [
        f"set -eu; cd '{wp_root}'; wp option get siteurl; wp option get home",
        f"set -eu; umask 077; mkdir -p '{backup_dir}'; cd '{wp_root}'; wp db export '{backup}' --quiet; test -s '{backup}'",
        f"set -eu; cd '{wp_root}'; wp cache flush",
        f"set -eu; cd '{wp_root}'; wp bioco verify --runtime",
        f"set -eu; cd '{wp_root}'; wp option update bioco_release_marker '{marker_json}' --format=json >/dev/null",
    ]
    remote_commands = remotes.read_text().splitlines()
    # The importer is never part of a release; runtime verification replaces
    # the seed-parity check. Actual content preservation is checked by the
    # root on staging (before/after data hashes), not simulated here.
    assert not any("wp bioco import" in line for line in remote_commands)
    assert remote_commands == [f"{ssh_prefix} {line}" for line in expected_remotes], remote_commands
    assert events.read_text().splitlines() == [
        "preflight",
        "identity",
        "backup",
        "deploy:apply",
        "cache-flush",
        "runtime-verify",
        "smoke",
        "marker",
    ]
    assert "release-status=success" in result.stdout
    assert "release-marker=" in result.stdout


def test_staging_release_fails_on_unexpected_import_command(tmp_path):
    # Any regression reintroducing the importer trips the dedicated
    # unexpected-import failure before anything else can run.
    commit, env, events, remotes, marker = _fixture(tmp_path)
    release = SCRIPT.read_text()
    patched = release.replace(
        'run_remote "cd \'${wp_root}\'; wp bioco verify --runtime"',
        'run_remote "cd \'${wp_root}\'; wp bioco import --apply --force"',
    )
    assert patched != release, "release contract changed; update this test"
    script = tmp_path / "release-with-import.sh"
    script.write_text(patched)
    script.chmod(0o755)

    result = subprocess.run(
        [str(script), f"--commit={commit}", "--apply"],
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
        "unexpected-import",
    ]
    assert "step=runtime-verify status=passed" not in result.stdout
    assert "step=release-marker" not in result.stdout


def test_staging_release_aborts_when_runtime_verification_fails(tmp_path):
    commit, env, events, remotes, marker = _fixture(tmp_path)
    env["BIOCO_TEST_RUNTIME_VERIFY"] = "fail"

    result = _run_release(env, f"--commit={commit}", "--apply")

    assert result.returncode != 0
    assert "failed-step=runtime-verify" in result.stdout
    assert "step=smoke" not in result.stdout
    assert "step=release-marker" not in result.stdout
    assert events.read_text().splitlines() == [
        "preflight",
        "identity",
        "backup",
        "deploy:apply",
        "cache-flush",
        "runtime-verify",
    ]
    assert not marker.exists() or marker.read_text() == ""


def test_staging_release_fails_on_an_unexpected_remote_command(tmp_path):
    # The allowed remote-command boundary is fail-closed: any command outside
    # the adapter's grammar (here: a planted extra remote call) must abort
    # the release, not be silently recorded and passed.
    commit, env, events, remotes, marker = _fixture(tmp_path)
    release = SCRIPT.read_text()
    patched = release.replace(
        "run_remote \"cd '${wp_root}'; wp cache flush\"",
        "run_remote \"cd '${wp_root}'; wp db optimize\"",
    )
    assert patched != release, "release contract changed; update this test"
    script = tmp_path / "release-with-extra-ssh.sh"
    script.write_text(patched)
    script.chmod(0o755)

    result = subprocess.run(
        [str(script), f"--commit={commit}", "--apply"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert "failed-step=cache-flush" in result.stdout
    assert events.read_text().splitlines() == [
        "preflight",
        "identity",
        "backup",
        "deploy:apply",
        "unexpected-ssh",
    ]
    assert "step=release-marker" not in result.stdout
    assert not marker.exists() or marker.read_text() == ""


def test_staging_release_rejects_a_compound_remote_command(tmp_path):
    # A suffix appended to an allowed command must NOT inherit its clearance:
    # with substring matching, `wp cache flush; wp post update 42
    # --post_content=SEED` passed as "cache-flush" and destroyed the post.
    # The exact grammar must reject the compound command.
    commit, env, events, remotes, marker = _fixture(tmp_path)
    release = SCRIPT.read_text()
    patched = release.replace(
        "run_remote \"cd '${wp_root}'; wp cache flush\"",
        "run_remote \"cd '${wp_root}'; wp cache flush; wp post update 42 --post_content=SEED\"",
    )
    assert patched != release, "release contract changed; update this test"
    script = tmp_path / "release-with-compound-ssh.sh"
    script.write_text(patched)
    script.chmod(0o755)

    result = subprocess.run(
        [str(script), f"--commit={commit}", "--apply"],
        cwd=ROOT,
        env=env,
        text=True,
        capture_output=True,
    )

    assert result.returncode != 0
    assert "failed-step=cache-flush" in result.stdout
    assert events.read_text().splitlines() == [
        "preflight",
        "identity",
        "backup",
        "deploy:apply",
        "unexpected-ssh",
    ]
    assert "step=smoke" not in result.stdout
    assert "step=release-marker" not in result.stdout
    assert not marker.exists() or marker.read_text() == ""


def test_staging_release_dry_run_never_touches_the_site(tmp_path):
    commit, env, events, remotes, marker = _fixture(tmp_path)

    result = _run_release(env, f"--commit={commit}")

    assert result.returncode == 0, result.stdout + result.stderr
    assert events.read_text().splitlines() == ["preflight", "identity", "deploy:dry"]
    assert not any("wp bioco" in line for line in remotes.read_text().splitlines())
