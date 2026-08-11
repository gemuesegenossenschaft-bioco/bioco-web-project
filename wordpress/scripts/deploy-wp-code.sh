#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORDPRESS_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

host="${BIOCO_WP_HOST:-}"
user="${BIOCO_WP_USER:-}"
wp_content="${BIOCO_WP_CONTENT:-}"
ssh_port="${BIOCO_WP_SSH_PORT:-22}"
apply=0
mode="DRY-RUN"
mode_started=0

usage() {
  cat <<'EOF'
Usage:
  wordpress/scripts/deploy-wp-code.sh \
    --host=<ssh-host> \
    --user=<ssh-user> \
    --wp-content=<absolute-path-to-wp-content> \
    [--ssh-port=<port>] [--apply]

Options:
  --host=         SSH host. Env: BIOCO_WP_HOST
  --user=         SSH user. Env: BIOCO_WP_USER
  --wp-content=   Absolute path to the vanilla WordPress wp-content directory.
                  Env: BIOCO_WP_CONTENT
  --ssh-port=     SSH port, default 22. Env: BIOCO_WP_SSH_PORT
  --apply         Write changes. Without this flag every rsync uses --dry-run.
  --help          Show this help.

Safety:
  Only the four bioco mu-plugin directories, the two bioco theme directories,
  the top-level bioco loader, and the importer content-seed directory are
  transferred. WordPress core, uploads, regular plugins, Divi, and foreign
  themes are never synced or deleted.
EOF
}

finish() {
  local status="$?"
  if [[ "${mode_started}" == "1" ]]; then
    if [[ "${status}" == "0" ]]; then
      printf '\n========== %s COMPLETE: deployment command finished successfully ==========\n' "${mode}"
    else
      printf '\n========== %s ABORTED: deployment command exited with status %s ==========\n' "${mode}" "${status}" >&2
    fi
  fi
}
trap finish EXIT

for argument in "$@"; do
  case "${argument}" in
    --host=*)
      host="${argument#*=}"
      ;;
    --user=*)
      user="${argument#*=}"
      ;;
    --wp-content=*)
      wp_content="${argument#*=}"
      ;;
    --ssh-port=*)
      ssh_port="${argument#*=}"
      ;;
    --apply)
      apply=1
      mode="APPLY"
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      printf 'ERROR: Unknown argument: %s\n\n' "${argument}" >&2
      usage >&2
      exit 2
      ;;
  esac
done

mode_started=1
if [[ "${apply}" == "1" ]]; then
  printf '========== APPLY MODE: REMOTE FILES WILL BE CHANGED ==========\n'
else
  printf '========== DRY-RUN MODE: NO REMOTE FILES WILL BE CHANGED ==========\n'
fi

missing=0
for required_name in host user wp_content; do
  case "${required_name}" in
    host) required_value="${host}" ;;
    user) required_value="${user}" ;;
    wp_content) required_value="${wp_content}" ;;
  esac
  if [[ -z "${required_value}" ]]; then
    printf 'ERROR: Missing required parameter --%s (or matching environment variable).\n' "${required_name//_/-}" >&2
    missing=1
  fi
done
if [[ "${missing}" == "1" ]]; then
  printf '\n' >&2
  usage >&2
  exit 2
fi

while [[ "${wp_content}" != "/" && "${wp_content}" == */ ]]; do
  wp_content="${wp_content%/}"
done

if [[ -z "${wp_content}" || "${wp_content}" == "/" ]]; then
  printf 'ERROR: --wp-content must be the absolute wp-content directory, never empty or /.\n' >&2
  exit 2
fi
if [[ "${wp_content}" == */wp-content/uploads ]]; then
  printf 'ERROR: Refusing --wp-content ending in /wp-content/uploads. Pass the wp-content directory itself.\n' >&2
  exit 2
fi
if [[ "${wp_content}" != /* || "${wp_content}" != */wp-content ]]; then
  printf 'ERROR: --wp-content must be an absolute path ending exactly in wp-content.\n' >&2
  exit 2
fi
if [[ ! "${host}" =~ ^[A-Za-z0-9._-]+$ ]]; then
  printf 'ERROR: --host contains unsupported characters.\n' >&2
  exit 2
fi
if [[ ! "${user}" =~ ^[A-Za-z0-9._-]+$ ]]; then
  printf 'ERROR: --user contains unsupported characters.\n' >&2
  exit 2
fi
if [[ ! "${wp_content}" =~ ^/[A-Za-z0-9._/-]+$ ]]; then
  printf 'ERROR: --wp-content contains unsupported characters.\n' >&2
  exit 2
fi
if [[ ! "${ssh_port}" =~ ^[0-9]+$ ]] || (( ssh_port < 1 || ssh_port > 65535 )); then
  printf 'ERROR: --ssh-port must be an integer from 1 to 65535.\n' >&2
  exit 2
fi
for required_command in ssh rsync; do
  if ! command -v "${required_command}" >/dev/null 2>&1; then
    printf 'ERROR: Required local command is unavailable: %s\n' "${required_command}" >&2
    exit 1
  fi
done

local_sources=(
  "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-core"
  "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-content"
  "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-forms"
  "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-import"
  "${WORDPRESS_DIR}/web/app/themes/bioco"
  "${WORDPRESS_DIR}/web/app/themes/bioco-divi"
  "${WORDPRESS_DIR}/deploy/bioco-mu-loader.php"
  "${WORDPRESS_DIR}/content-seed"
)
for local_source in "${local_sources[@]}"; do
  if [[ ! -e "${local_source}" ]]; then
    printf 'ERROR: Required local source is missing: %s\n' "${local_source}" >&2
    exit 1
  fi
done

ssh_target="${user}@${host}"
ssh_args=(-p "${ssh_port}" -o BatchMode=yes -o ConnectTimeout=10)
rsync_ssh="ssh -p ${ssh_port} -o BatchMode=yes -o ConnectTimeout=10"
wp_root="${wp_content%/wp-content}"

remote_quote() {
  printf '%q' "$1"
}

run_remote() {
  local remote_command="$1"
  ssh "${ssh_args[@]}" "${ssh_target}" "${remote_command}"
}

printf '\nPreflight checks:\n'
if run_remote "true"; then
  printf 'PASS: SSH connection to %s on port %s works.\n' "${ssh_target}" "${ssh_port}"
else
  printf 'ERROR: SSH connection to %s on port %s failed.\n' "${ssh_target}" "${ssh_port}" >&2
  exit 1
fi

version_file="$(remote_quote "${wp_root}/wp-includes/version.php")"
uploads_dir="$(remote_quote "${wp_content}/uploads")"
plugins_dir="$(remote_quote "${wp_content}/plugins")"
if ! run_remote "test -f ${version_file}"; then
  printf 'ERROR: %s/wp-includes/version.php is missing; target is not a confirmed WordPress install.\n' "${wp_root}" >&2
  exit 1
fi
printf 'PASS: WordPress core marker exists.\n'
if ! run_remote "test -d ${uploads_dir}"; then
  printf 'ERROR: %s/uploads is missing. Refusing to deploy.\n' "${wp_content}" >&2
  exit 1
fi
printf 'PASS: %s/uploads exists and will NOT be touched.\n' "${wp_content}"
if ! run_remote "test -d ${plugins_dir}"; then
  printf 'ERROR: %s/plugins is missing. Refusing to deploy.\n' "${wp_content}" >&2
  exit 1
fi
printf 'PASS: %s/plugins exists and will NOT be touched.\n' "${wp_content}"

rsync_common=(-avzc --exclude=.git --exclude=.DS_Store --exclude=node_modules --exclude='*.log')
if [[ "${apply}" == "0" ]]; then
  rsync_common+=(--dry-run)
else
  mu_plugins_dir="$(remote_quote "${wp_content}/mu-plugins")"
  themes_dir="$(remote_quote "${wp_content}/themes")"
  run_remote "mkdir -p ${mu_plugins_dir} ${themes_dir}"
fi

sync_owned_dir() {
  local source_dir="$1"
  local destination_dir="$2"
  case "${destination_dir}" in
    "${wp_content}/mu-plugins/bioco-core"|\
    "${wp_content}/mu-plugins/bioco-content"|\
    "${wp_content}/mu-plugins/bioco-forms"|\
    "${wp_content}/mu-plugins/bioco-import"|\
    "${wp_content}/mu-plugins/bioco-import/content-seed"|\
    "${wp_content}/themes/bioco"|\
    "${wp_content}/themes/bioco-divi")
      ;;
    *)
      printf 'ERROR: Refusing --delete outside the owned-directory allowlist: %s\n' "${destination_dir}" >&2
      exit 1
      ;;
  esac
  printf '\nSyncing owned directory: %s -> %s:%s\n' "${source_dir}" "${ssh_target}" "${destination_dir}"
  rsync "${rsync_common[@]}" --delete -e "${rsync_ssh}" "${source_dir}/" "${ssh_target}:${destination_dir}/"
}

sync_file() {
  local source_file="$1"
  local destination_file="$2"
  printf '\nSyncing owned file: %s -> %s:%s\n' "${source_file}" "${ssh_target}" "${destination_file}"
  rsync "${rsync_common[@]}" -e "${rsync_ssh}" "${source_file}" "${ssh_target}:${destination_file}"
}

sync_owned_dir "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-core" "${wp_content}/mu-plugins/bioco-core"
sync_owned_dir "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-content" "${wp_content}/mu-plugins/bioco-content"
sync_owned_dir "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-forms" "${wp_content}/mu-plugins/bioco-forms"
sync_owned_dir "${WORDPRESS_DIR}/web/app/mu-plugins/bioco-import" "${wp_content}/mu-plugins/bioco-import"
sync_owned_dir "${WORDPRESS_DIR}/web/app/themes/bioco" "${wp_content}/themes/bioco"
sync_owned_dir "${WORDPRESS_DIR}/web/app/themes/bioco-divi" "${wp_content}/themes/bioco-divi"
sync_file "${WORDPRESS_DIR}/deploy/bioco-mu-loader.php" "${wp_content}/mu-plugins/bioco-mu-loader.php"

# This transfer must remain after bioco-import: that directory sync uses
# --delete and would otherwise remove the deployed content-seed directory.
sync_owned_dir "${WORDPRESS_DIR}/content-seed" "${wp_content}/mu-plugins/bioco-import/content-seed"

if [[ "${apply}" == "0" ]]; then
  printf '\nDry-run complete. No post-deploy verification ran because nothing was written.\n'
  exit 0
fi

printf '\nPost-deploy verification:\n'
verification_failures=0

verify_remote_file() {
  local label="$1"
  local remote_file="$2"
  local quoted_file
  quoted_file="$(remote_quote "${remote_file}")"
  if run_remote "test -f ${quoted_file}"; then
    printf 'PASS: %s\n' "${label}"
  else
    printf 'FAIL: %s\n' "${label}" >&2
    verification_failures=$((verification_failures + 1))
  fi
}

verify_remote_dir() {
  local label="$1"
  local remote_dir="$2"
  local quoted_dir
  quoted_dir="$(remote_quote "${remote_dir}")"
  if run_remote "test -d ${quoted_dir}"; then
    printf 'PASS: %s\n' "${label}"
  else
    printf 'FAIL: %s\n' "${label}" >&2
    verification_failures=$((verification_failures + 1))
  fi
}

verify_remote_file "mu-plugin loader is present" "${wp_content}/mu-plugins/bioco-mu-loader.php"
verify_remote_file "bioco-core main file is present" "${wp_content}/mu-plugins/bioco-core/bioco-core.php"
verify_remote_file "bioco-content main file is present" "${wp_content}/mu-plugins/bioco-content/bioco-content.php"
verify_remote_file "bioco-forms main file is present" "${wp_content}/mu-plugins/bioco-forms/bioco-forms.php"
verify_remote_file "bioco-import main file is present" "${wp_content}/mu-plugins/bioco-import/bioco-import.php"

local_seed_count="$(find "${WORDPRESS_DIR}/content-seed" -maxdepth 1 -type f -name '*.json' | wc -l | tr -d '[:space:]')"
seed_dir="$(remote_quote "${wp_content}/mu-plugins/bioco-import/content-seed")"
remote_seed_count="$(run_remote "find ${seed_dir} -maxdepth 1 -type f -name '*.json' | wc -l" | tr -d '[:space:]')"
if [[ "${remote_seed_count}" == "${local_seed_count}" ]]; then
  printf 'PASS: seed count matches (%s JSON files).\n' "${local_seed_count}"
else
  printf 'FAIL: seed count mismatch (local %s, remote %s).\n' "${local_seed_count}" "${remote_seed_count}" >&2
  verification_failures=$((verification_failures + 1))
fi

verify_remote_dir "wp-content/uploads still exists; media was not deleted" "${wp_content}/uploads"

wp_root_quoted="$(remote_quote "${wp_root}")"
if run_remote "command -v wp >/dev/null 2>&1"; then
  if siteurl_output="$(run_remote "cd ${wp_root_quoted} && wp --skip-plugins --skip-themes option get siteurl")"; then
    printf 'PASS: WP-CLI siteurl: %s\n' "${siteurl_output}"
  else
    printf 'FAIL: WP-CLI could not read siteurl.\n' >&2
    verification_failures=$((verification_failures + 1))
  fi
  if run_remote "cd ${wp_root_quoted} && wp bioco import --help >/dev/null"; then
    printf 'PASS: WP-CLI command wp bioco import is registered.\n'
  else
    printf 'FAIL: WP-CLI command wp bioco import is not registered.\n' >&2
    verification_failures=$((verification_failures + 1))
  fi
else
  printf 'SKIP: WP-CLI is not installed; deploy verification continues without it.\n'
  printf '      Manual alternative: create/edit pages in wp-admin or install WP-CLI before using the scripted importer.\n'
fi

if (( verification_failures > 0 )); then
  printf '\nFAIL: %s post-deploy verification check(s) failed.\n' "${verification_failures}" >&2
  exit 1
fi

printf '\nNext commands (run in this order):\n'
printf '  ssh -p %q %q "cd %q && wp bioco import"\n' "${ssh_port}" "${ssh_target}" "${wp_root}"
printf '  ssh -p %q %q "cd %q && wp bioco import --apply"\n' "${ssh_port}" "${ssh_target}" "${wp_root}"
