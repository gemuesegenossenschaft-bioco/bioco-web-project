#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
default_repo_root="$(cd "${script_dir}/../.." && pwd)"
repo_root="${BIOCO_RELEASE_REPO_ROOT:-${default_repo_root}}"
log_dir="${BIOCO_RELEASE_LOG_DIR:-${repo_root}/output/releases}"
preflight_command="${BIOCO_RELEASE_PREFLIGHT_COMMAND:-${script_dir}/release-wordpress-preflight.sh}"
deploy_script="${BIOCO_RELEASE_DEPLOY_SCRIPT:-${script_dir}/deploy-wp-code.sh}"
ssh_bin="${BIOCO_RELEASE_SSH_BIN:-ssh}"
render_gate="${BIOCO_RELEASE_RENDER_GATE:-${repo_root}/tests/wordpress-staging-render-gate.sh}"
timestamp="${BIOCO_RELEASE_TIMESTAMP:-$(date -u +%Y%m%dT%H%M%SZ)}"

commit=""
apply=0
for argument in "$@"; do
  case "${argument}" in
    --commit=*) commit="${argument#*=}" ;;
    --apply) apply=1 ;;
    --help)
      echo "Usage: $0 --commit=<full-sha> [--apply]"
      exit 0
      ;;
    *) echo "ERROR: unknown argument: ${argument}" >&2; exit 2 ;;
  esac
done

if [[ ! "${commit}" =~ ^[0-9a-f]{40}$ ]]; then
  echo "ERROR: --commit must be a full lowercase Git SHA." >&2
  exit 2
fi

mkdir -p "${log_dir}"
log_file="${log_dir}/${timestamp}-${commit}.log"
# Re-exec through a foreground tee so the EXIT trap's release-status line is
# always flushed to the log; a backgrounded process substitution can lose it.
if [[ -z "${BIOCO_RELEASE_LOGGING:-}" ]]; then
  export BIOCO_RELEASE_LOGGING=1
  set -o pipefail
  "$0" "$@" 2>&1 | tee -a "${log_file}"
  exit "${PIPESTATUS[0]}"
fi
current_step="initialization"

finish() {
  local status="$?"
  if [[ "${status}" == "0" ]]; then
    echo "release-status=success"
  else
    echo "release-status=failed exit=${status} failed-step=${current_step}"
  fi
}
trap finish EXIT

mode="dry-run"
[[ "${apply}" == "1" ]] && mode="apply"
echo "release-start timestamp=${timestamp} mode=${mode} commit=${commit}"

rsync_sources=(
  wordpress/web/app/mu-plugins/bioco-core
  wordpress/web/app/mu-plugins/bioco-content
  wordpress/web/app/mu-plugins/bioco-forms
  wordpress/web/app/mu-plugins/bioco-import
  wordpress/web/app/themes/bioco
  wordpress/web/app/themes/bioco-divi
  wordpress/deploy/bioco-mu-loader.php
  wordpress/content-seed
)

# Everything the release reads: the synced sources plus the gates that decide
# whether the sync may happen at all.
release_inputs=(
  "${rsync_sources[@]}"
  wordpress/scripts
  cms/content-seed
  tests
  .github/workflows/deploy-wordpress-staging.yml
  .github/workflows/requirements-ci.txt
)

assert_fixed_commit() {
  local actual_commit untracked shipped
  actual_commit="$(git -C "${repo_root}" rev-parse HEAD)"
  if [[ "${actual_commit}" != "${commit}" ]]; then
    echo "ERROR: checkout ${actual_commit} does not match requested commit ${commit}." >&2
    exit 1
  fi
  if ! git -C "${repo_root}" diff --quiet HEAD -- || ! git -C "${repo_root}" diff --cached --quiet HEAD --; then
    echo "ERROR: tracked working tree changes would make this release differ from commit ${commit}." >&2
    exit 1
  fi
  untracked="$(git -C "${repo_root}" ls-files --others --exclude-standard -- \
    "${release_inputs[@]}")"
  if [[ -n "${untracked}" ]]; then
    echo "ERROR: untracked release input would make this release differ from commit ${commit}:" >&2
    echo "${untracked}" >&2
    exit 1
  fi
  shipped="$(git -C "${repo_root}" ls-files --others -- "${rsync_sources[@]}")"
  if [[ -n "${shipped}" ]]; then
    echo "ERROR: untracked release input inside a synced source would be shipped to the server:" >&2
    echo "${shipped}" >&2
    exit 1
  fi
}

assert_fixed_commit

for required_name in BIOCO_WP_HOST BIOCO_WP_USER BIOCO_WP_CONTENT; do
  if [[ -z "${!required_name:-}" ]]; then
    echo "ERROR: ${required_name} is required." >&2
    exit 2
  fi
done
if [[ ! "${BIOCO_WP_HOST}" =~ ^[A-Za-z0-9._-]+$ ]] \
  || [[ ! "${BIOCO_WP_USER}" =~ ^[A-Za-z0-9._-]+$ ]] \
  || [[ ! "${BIOCO_WP_CONTENT}" =~ ^/[A-Za-z0-9._/-]+/wp-content$ ]] \
  || [[ ! "${BIOCO_WP_SSH_PORT:-22}" =~ ^[0-9]{1,5}$ ]] \
  || [[ ! "${timestamp}" =~ ^[0-9]{8}T[0-9]{6}Z$ ]]; then
  echo "ERROR: unsafe release target or timestamp." >&2
  exit 2
fi

current_step="preflight"
echo "step=${current_step} status=running"
"${preflight_command}"
echo "step=preflight status=passed"
assert_fixed_commit

deploy_args=(
  "--host=${BIOCO_WP_HOST}"
  "--user=${BIOCO_WP_USER}"
  "--wp-content=${BIOCO_WP_CONTENT}"
  "--ssh-port=${BIOCO_WP_SSH_PORT:-22}"
)
[[ "${apply}" == "1" ]] && deploy_args+=(--apply)

ssh_args=(-p "${BIOCO_WP_SSH_PORT:-22}" -o BatchMode=yes -o ConnectTimeout=10)
ssh_target="${BIOCO_WP_USER}@${BIOCO_WP_HOST}"
wp_root="${BIOCO_WP_CONTENT%/wp-content}"
backup_dir="${BIOCO_RELEASE_BACKUP_DIR:-$(dirname "${wp_root}")/backups/wordpress-staging}"
if [[ ! "${backup_dir}" =~ ^/[A-Za-z0-9._/-]+$ ]]; then
  echo "ERROR: BIOCO_RELEASE_BACKUP_DIR must be an absolute safe path." >&2
  exit 2
fi
backup_path="${backup_dir}/${timestamp}-${commit}.sql"

run_remote() {
  "${ssh_bin}" "${ssh_args[@]}" "${ssh_target}" "set -eu; $1"
}

expected_site_url="${BIOCO_RELEASE_URL:-https://staging.bioco.ch}"
expected_site_url="${expected_site_url%/}"

current_step="identity"
echo "step=${current_step} status=running expected-site-url=${expected_site_url}"
identity_output="$(run_remote "cd '${wp_root}'; wp option get siteurl; wp option get home")"
identity_values=()
while IFS= read -r identity_line; do
  [[ -n "${identity_line}" ]] && identity_values+=("${identity_line}")
done <<< "${identity_output}"
if [[ "${#identity_values[@]}" != "2" ]]; then
  echo "ERROR: staging identity probe did not return siteurl and home." >&2
  exit 1
fi
for identity_value in "${identity_values[@]}"; do
  if [[ "${identity_value%/}" != "${expected_site_url}" ]]; then
    echo "ERROR: remote WordPress at ${wp_root} reports '${identity_value}', expected ${expected_site_url}; refusing to touch it." >&2
    exit 1
  fi
done
echo "step=identity status=passed site-url=${expected_site_url}"

if [[ "${apply}" == "0" ]]; then
  current_step="code-sync"
  echo "step=${current_step} status=running mode=${mode}"
  "${deploy_script}" "${deploy_args[@]}"
  echo "step=code-sync status=passed mode=${mode}"
  echo "release-dry-run-complete remote-mutations=0"
  exit 0
fi

current_step="backup"
echo "step=${current_step} status=running backup-path=${backup_path}"
run_remote "mkdir -p '${backup_dir}'; cd '${wp_root}'; wp db export '${backup_path}' --quiet; test -s '${backup_path}'"
echo "step=backup status=passed backup-path=${backup_path}"

current_step="code-sync"
echo "step=${current_step} status=running mode=${mode}"
"${deploy_script}" "${deploy_args[@]}"
echo "step=code-sync status=passed mode=${mode}"

current_step="cache-flush"
echo "step=${current_step} status=running"
run_remote "cd '${wp_root}'; wp cache flush"
# PHP-FPM keeps compiled bytecode for freshly rsynced mu-plugin sources; a CLI
# opcache_reset() cannot touch the web worker, so this needs an HTTP endpoint.
if [[ -n "${BIOCO_RELEASE_OPCACHE_URL:-}" && -n "${BIOCO_RELEASE_OPCACHE_TOKEN:-}" ]]; then
  curl -fsS -H "X-Bioco-Opcache-Token: ${BIOCO_RELEASE_OPCACHE_TOKEN}" \
    "${BIOCO_RELEASE_OPCACHE_URL}" >/dev/null
  echo "step=cache-flush status=passed opcache-reset=applied"
else
  echo "WARNING: BIOCO_RELEASE_OPCACHE_URL/TOKEN unset; the web PHP worker may serve stale bytecode."
  echo "step=cache-flush status=passed opcache-reset=skipped"
fi

current_step="import"
echo "step=${current_step} status=running"
run_remote "cd '${wp_root}'; wp bioco import --apply --force"
echo "step=import status=passed"

current_step="parity"
echo "step=${current_step} status=running"
run_remote "cd '${wp_root}'; wp bioco verify"
echo "step=parity status=passed"

current_step="smoke"
echo "step=${current_step} status=running"
BIOCO_STAGING_URL="${expected_site_url}" \
BIOCO_STAGING_RESOLVE="${BIOCO_RELEASE_RESOLVE:-}" \
  "${render_gate}"
echo "step=smoke status=passed"

current_step="release-marker"
echo "step=${current_step} status=running"
marker_json="{\"commit\":\"${commit}\",\"backup\":\"${backup_path}\",\"timestamp\":\"${timestamp}\",\"parity\":\"passed\",\"smoke\":\"passed\"}"
run_remote "cd '${wp_root}'; wp option update bioco_release_marker '${marker_json}' --format=json >/dev/null"
echo "step=release-marker status=passed release-marker=${commit} backup-path=${backup_path}"

echo "release-complete commit=${commit} backup-path=${backup_path} release-marker=${commit}"
