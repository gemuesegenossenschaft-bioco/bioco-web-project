#!/usr/bin/env bash
#
# Conformance manifest for the theme-agnostic restructure (GitHub #101).
#
# Emits a stable, sorted manifest to stdout covering:
#   (a) every block name declared in block.json, wherever it lives under web/app
#   (b) every ACF field-group key from every acf-json/ directory under web/app
#   (c) every bioco_* function definition across theme + mu-plugins PHP
#   (d) every --wp--[a-z0-9-]* custom-property reference in CSS/render.php that
#       is NOT defined by theme.json's generated preset/custom set (must be
#       empty once bioco-core/assets/bioco-tokens.css exists and is complete)
#   (e) a count of render.php / view.js files under web/app
#
# Each manifest line is "<value>\t<file>" so a before/after diff can `cut -f1`
# to compare the VALUE set only (ignoring which file/path currently owns it) —
# see the diff invocation printed at the end of this script's --help text.
#
# Uses jq when available (theme.json / *.json parsing) and falls back to grep
# otherwise. php -l is run (if php is available) as an informational syntax
# check on stderr only — it does not affect the manifest on stdout.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ "${1:-}" == "--help" ]]; then
  cat <<'EOF'
Usage: scripts/conformance.sh > manifest.txt

To compare two manifests by VALUE SET ONLY (ignoring which file/path currently
owns each entry — paths are expected to differ before/after the move):

  for section in A-BLOCK-NAMES B-ACF-GROUP-KEYS C-BIOCO-FUNCTIONS D-UNDEFINED-WP-VARS; do
    echo "=== $section ==="
    diff \
      <(awk -v s="## $section" 'BEGIN{p=0} $0==s{p=1;next} /^## /{p=0} p && NF{print $1}' manifest-before.txt | sort -u) \
      <(awk -v s="## $section" 'BEGIN{p=0} $0==s{p=1;next} /^## /{p=0} p && NF{print $1}' manifest-after.txt | sort -u)
  done
EOF
  exit 0
fi

HAVE_JQ=0
command -v jq >/dev/null 2>&1 && HAVE_JQ=1
HAVE_PHP=0
command -v php >/dev/null 2>&1 && HAVE_PHP=1

# ---------------------------------------------------------------------------
# Informational only: php -l over every touched PHP file (stderr, not manifest)
# ---------------------------------------------------------------------------
if [[ "$HAVE_PHP" == "1" ]]; then
  while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null 2>/tmp/conformance-php-lint-err || {
      echo "PHP SYNTAX ERROR: $f" >&2
      cat /tmp/conformance-php-lint-err >&2
    }
  done < <(find web/app -type f -name '*.php' -print0)
  rm -f /tmp/conformance-php-lint-err
fi

# ---------------------------------------------------------------------------
# (a) block names
# ---------------------------------------------------------------------------
echo "## A-BLOCK-NAMES"
while IFS= read -r -d '' f; do
  if [[ "$HAVE_JQ" == "1" ]]; then
    name=$(jq -r '.name // empty' "$f" 2>/dev/null || true)
  else
    name=$(grep -oE '"name"[[:space:]]*:[[:space:]]*"bioco/[a-z0-9-]+"' "$f" | head -1 | sed -E 's/.*"(bioco\/[a-z0-9-]+)"/\1/')
  fi
  [[ -n "$name" ]] && printf '%s\t%s\n' "$name" "$f"
done < <(find web/app -type f -name 'block.json' -print0) | sort -u

# ---------------------------------------------------------------------------
# (b) ACF group keys
# ---------------------------------------------------------------------------
echo "## B-ACF-GROUP-KEYS"
while IFS= read -r -d '' f; do
  if [[ "$HAVE_JQ" == "1" ]]; then
    key=$(jq -r '.key // empty' "$f" 2>/dev/null || true)
  else
    key=$(grep -oE '"key"[[:space:]]*:[[:space:]]*"group_[A-Za-z0-9_]+"' "$f" | head -1 | sed -E 's/.*"(group_[A-Za-z0-9_]+)"/\1/')
  fi
  [[ -n "$key" ]] && printf '%s\t%s\n' "$key" "$f"
done < <(find web/app -path '*/acf-json/*.json' -type f -print0) | sort -u

# ---------------------------------------------------------------------------
# (c) bioco_* function definitions
# ---------------------------------------------------------------------------
echo "## C-BIOCO-FUNCTIONS"
find web/app \( -path '*/themes/*' -o -path '*/mu-plugins/*' \) -type f -name '*.php' -print0 \
  | xargs -0 grep -noE '^function[[:space:]]+bioco_[A-Za-z0-9_]+' 2>/dev/null \
  | while IFS=: read -r file lineno match; do
      fn=$(echo "$match" | sed -E 's/^function[[:space:]]+//')
      printf '%s\t%s:%s\n' "$fn" "$file" "$lineno"
    done | sort -u

# ---------------------------------------------------------------------------
# (d) undefined --wp--* var references
# ---------------------------------------------------------------------------
echo "## D-UNDEFINED-WP-VARS"

DEFINED_VARS_FILE=$(mktemp)
trap 'rm -f "$DEFINED_VARS_FILE"' EXIT

# Defined-by-theme.json (the canonical token source, extracted the same way
# bioco-tokens.css was derived — see HARDCASES.md Hard Case 1).
THEME_JSON="web/app/themes/bioco/theme.json"
if [[ -f "$THEME_JSON" && "$HAVE_JQ" == "1" ]]; then
  # Run each axis as its own jq call (a single comma-joined filter is fragile
  # across jq versions when mixing generators with string concatenation).
  jq -r '(.settings.color.palette // [])[].slug | "--wp--preset--color--" + .' "$THEME_JSON" >> "$DEFINED_VARS_FILE" 2>/dev/null || true
  jq -r '(.settings.typography.fontFamilies // [])[].slug | "--wp--preset--font-family--" + .' "$THEME_JSON" >> "$DEFINED_VARS_FILE" 2>/dev/null || true
  jq -r '(.settings.typography.fontSizes // [])[].slug | "--wp--preset--font-size--" + .' "$THEME_JSON" >> "$DEFINED_VARS_FILE" 2>/dev/null || true
  jq -r '(.settings.spacing.spacingSizes // [])[].slug | "--wp--preset--spacing--" + .' "$THEME_JSON" >> "$DEFINED_VARS_FILE" 2>/dev/null || true
  jq -r '(.settings.custom // {}) | to_entries[] | .key as $k | (.value | to_entries[]) | "--wp--custom--" + $k + "--" + .key' "$THEME_JSON" >> "$DEFINED_VARS_FILE" 2>/dev/null || true
  printf -- '--wp--style--global--content-size\n--wp--style--global--wide-size\n' >> "$DEFINED_VARS_FILE"
fi

# Defined-by bioco-tokens.css, wherever it currently lives (pre-move: absent).
while IFS= read -r -d '' f; do
  grep -oE -- '--wp--[a-zA-Z0-9-]+[[:space:]]*:' "$f" | sed -E 's/[[:space:]]*:$//' >> "$DEFINED_VARS_FILE"
done < <(find web/app -type f -name 'bioco-tokens.css' -print0)

sort -u -o "$DEFINED_VARS_FILE" "$DEFINED_VARS_FILE"

REFERENCED_VARS_FILE=$(mktemp)
# Scan *.css AND *.php (not just render.php): shared helper functions such as
# bioco_render_events_list()/bioco_render_map_block() echo inline
# style="...var(--wp--...)" strings too, and once moved they live in
# includes/helpers.php, not a file named render.php. bioco-tokens.css is
# excluded: it's the DEFINITIONS source (scanned separately above), and its
# own explanatory comments (e.g. "-> --wp--preset--color--{slug}") would
# otherwise read as bogus "referenced" vars.
find web/app -type f \( -name '*.css' -o -name '*.php' \) ! -name 'bioco-tokens.css' -print0 \
  | xargs -0 grep -ohoE -- '--wp--[a-zA-Z0-9-]+' 2>/dev/null \
  | sort -u > "$REFERENCED_VARS_FILE"

comm -23 "$REFERENCED_VARS_FILE" "$DEFINED_VARS_FILE" | while read -r v; do
  [[ -n "$v" ]] && printf '%s\t(undefined)\n' "$v"
done
rm -f "$REFERENCED_VARS_FILE"

# ---------------------------------------------------------------------------
# (e) render.php / view.js counts
# ---------------------------------------------------------------------------
echo "## E-RENDER-VIEW-COUNTS"
render_count=$(find web/app -type f -name 'render.php' | wc -l | tr -d ' ')
view_count=$(find web/app -type f -name 'view.js' | wc -l | tr -d ' ')
printf 'render.php\t%s\n' "$render_count"
printf 'view.js\t%s\n' "$view_count"

# ---------------------------------------------------------------------------
# (f) seed -> block plan coverage
# ---------------------------------------------------------------------------
# Emits the per-block plan counts so a mapping change shows up as a manifest
# diff, not just as a pass/fail. check-seed-plan.php is the authoritative gate
# (it also asserts every referenced block dir and ACF group file exists); this
# only records its shape. Run from the wordpress/ directory, same as the rest.
echo "## F-SEED-PLAN"
if php scripts/check-seed-plan.php > /tmp/bioco-seed-plan.$$ 2>&1; then
  sed -n '/^Verwendete Bloecke:/,/^$/p' /tmp/bioco-seed-plan.$$ | sed '1d;/^$/d' | sed -E 's/^[[:space:]]+//; s/[[:space:]]+/\t/'
  grep -E '^Geplante Bloecke:' /tmp/bioco-seed-plan.$$ | tr -d ' '
  printf 'gate\tOK\n'
else
  printf 'gate\tFAIL\n'
  grep -E '^  - ' /tmp/bioco-seed-plan.$$ || true
fi
rm -f /tmp/bioco-seed-plan.$$
