#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="${BIOCO_RELEASE_REPO_ROOT:-$(cd "${script_dir}/../.." && pwd)}"
cd "${repo_root}"

echo "gate=pytest status=running"
PYTHONDONTWRITEBYTECODE=1 pytest -p no:cacheprovider tests/ -q
echo "gate=pytest status=passed"

echo "gate=hardcoded-content status=running"
php wordpress/scripts/check-hardcoded-content.php
echo "gate=hardcoded-content status=passed"

echo "gate=seed-plan status=running"
seed_plan_output="$(php wordpress/scripts/check-seed-plan.php)"
printf '%s\n' "${seed_plan_output}"
read -r block_count page_count < <(
  printf '%s\n' "${seed_plan_output}" \
    | sed -n 's/^Geplante Bloecke: \([0-9][0-9]*\), Seiten: \([0-9][0-9]*\)$/\1 \2/p'
)
if [[ -z "${block_count:-}" || -z "${page_count:-}" ]]; then
  echo "ERROR: seed-plan gate did not report block/page counts." >&2
  exit 1
fi
diff -ru wordpress/content-seed cms/content-seed
echo "gate=seed-plan status=passed pages=${page_count} blocks=${block_count}"

echo "gate=php-lint status=running"
php_source_dirs=(
  wordpress/web/app/mu-plugins/bioco-core
  wordpress/web/app/mu-plugins/bioco-content
  wordpress/web/app/mu-plugins/bioco-forms
  wordpress/web/app/mu-plugins/bioco-import
  wordpress/web/app/themes/bioco
  wordpress/web/app/themes/bioco-divi
)
for php_source_dir in "${php_source_dirs[@]}"; do
  while IFS= read -r -d '' php_file; do
    php -l "${php_file}" >/dev/null
  done < <(find "${php_source_dir}" -type f -name '*.php' -print0)
done
php -l wordpress/deploy/bioco-mu-loader.php >/dev/null
echo "gate=php-lint status=passed"
