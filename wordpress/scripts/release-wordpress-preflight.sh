#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="${BIOCO_RELEASE_REPO_ROOT:-$(cd "${script_dir}/../.." && pwd)}"
cd "${repo_root}"

echo "gate=pytest status=running"
report_dir="output/releases/preflight"
mkdir -p "${report_dir}"
rm -f "${report_dir}/pytest.xml" "${report_dir}/forms.xml"
BIOCO_APACHE_TESTS=1 BIOCO_BUTTON_BROWSER_TESTS=1 BIOCO_FORMS_BROWSER_TESTS=0 PYTHONDONTWRITEBYTECODE=1 \
  python3 -m pytest -p no:cacheprovider tests/ -q --junitxml="${report_dir}/pytest.xml"
echo "gate=pytest status=passed"

echo "gate=forms-browser status=running"
BIOCO_APACHE_TESTS=1 BIOCO_BUTTON_BROWSER_TESTS=1 BIOCO_FORMS_BROWSER_TESTS=1 PYTHONDONTWRITEBYTECODE=1 \
  python3 -m pytest -p no:cacheprovider tests/test_wordpress_forms_lifecycle.py -q \
    --deselect=tests/test_wordpress_forms_lifecycle.py::test_wordpress_runtime_emits_runtime_before_adapters_without_blocking_turnstile \
    --deselect=tests/test_wordpress_forms_lifecycle.py::test_wordpress_real_page_mounts_runtime_and_adapters \
    --deselect=tests/test_wordpress_forms_lifecycle.py::test_wordpress_hung_turnstile_blocks_native_get_and_api_submit \
    --junitxml="${report_dir}/forms.xml"
echo "gate=forms-browser status=passed"

echo "gate=required-tests status=running"
python3 .github/workflows/check-required-tests.py "${report_dir}/pytest.xml" "${report_dir}/forms.xml"
echo "gate=required-tests status=passed"

echo "gate=hardcoded-content status=running"
php wordpress/scripts/check-hardcoded-content.php
echo "gate=hardcoded-content status=passed"

echo "gate=seed-plan status=running"
seed_plan_output="$(php wordpress/scripts/check-seed-plan.php)"
printf '%s\n' "${seed_plan_output}"
read -r block_count page_count < <(
  printf '%s\n' "${seed_plan_output}" \
    | sed -n 's/^Geplante Bloecke: \([0-9][0-9]*\), Seiten: \([0-9][0-9]*\)$/\1 \2/p'
) || block_count=""
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

echo "gate=divi-design-system-contract status=running"
python3 wordpress/scripts/check-divi-design-system.py
# TODO(#134): add --require-exports once authenticated staging exports are available.
echo "gate=divi-design-system-contract status=passed"
