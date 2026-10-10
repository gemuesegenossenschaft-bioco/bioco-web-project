#!/usr/bin/env bash
# Internal staging-release step; never use as a separate deployment entry point.
set -euo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ssh_bin="${BIOCO_RELEASE_SSH_BIN:-ssh}"
curl_bin="${BIOCO_RELEASE_CURL_BIN:-curl}"
wp_root="${BIOCO_WP_CONTENT%/wp-content}"
site_url="${BIOCO_RELEASE_URL:-https://staging.bioco.ch}"
if [[ ! "${BIOCO_WP_HOST}" =~ ^[A-Za-z0-9._-]+$ || ! "${BIOCO_WP_USER}" =~ ^[A-Za-z0-9._-]+$ \
  || ! "${wp_root}" =~ ^/[A-Za-z0-9._/-]+$ || ! "${BIOCO_WP_SSH_PORT:-22}" =~ ^[0-9]{1,5}$ \
  || ! "${site_url}" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?/?$ ]]; then
  echo 'ERROR: unsafe web OPcache target.' >&2; exit 2
fi
nonce="$(php -r 'echo bin2hex(random_bytes(16));')"
token="$(php -r 'echo bin2hex(random_bytes(32));')"
expires="$(php -r 'echo time()+300;')"
probe_path="${wp_root}/bioco-opcache-${nonce}.php"
ssh_args=(-p "${BIOCO_WP_SSH_PORT:-22}" -o BatchMode=yes -o ConnectTimeout=10 "${BIOCO_WP_USER}@${BIOCO_WP_HOST}")
cleanup() {
  "${ssh_bin}" "${ssh_args[@]}" "set -eu; rm -f -- '${probe_path}'; test ! -e '${probe_path}'"
}
# Install the trap before upload, including interrupted/partially written files.
trap cleanup EXIT
trap 'exit 1' HUP INT TERM
{
  printf '<?php $biocoOpcacheToken = '\''%s'\''; $biocoOpcacheExpires = %s;\n' "${token}" "${expires}"
  sed '1d' "${script_dir}/web-opcache-probe.php"
} | "${ssh_bin}" "${ssh_args[@]}" "set -eu; umask 077; cat > '${probe_path}'"
"${curl_bin}" -fsS --max-time 30 --request POST \
  -H "X-Bioco-Opcache-Token: ${token}" "${site_url%/}/bioco-opcache-${nonce}.php" \
  | php -r '$v=json_decode(stream_get_contents(STDIN),true); if(!is_array($v)||($v["ok"]??false)!==true||!is_int($v["invalidated"]??null))exit(1); echo "web-opcache invalidated=".$v["invalidated"].PHP_EOL;'
cleanup
trap - EXIT
