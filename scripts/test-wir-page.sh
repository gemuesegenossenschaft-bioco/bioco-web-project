#!/bin/bash
# Integration test: verify /wir page renders correctly after migration + deploy.
# Run against production: bash scripts/test-wir-page.sh
# Run against local:      bash scripts/test-wir-page.sh local

set -euo pipefail

if [ "${1:-}" = "local" ]; then
  URL="http://127.0.0.1:3000/wir"
  CURL_OPTS=""
  echo "Testing LOCAL: $URL"
else
  URL="https://bioco.ch/wir"
  CURL_OPTS="--resolve bioco.ch:443:193.33.128.160"
  echo "Testing PRODUCTION: $URL"
fi

PASS=0
FAIL=0

check() {
  local label="$1"
  local result="$2"
  if [ "$result" = "1" ]; then
    echo "  PASS: $label"
    PASS=$((PASS + 1))
  else
    echo "  FAIL: $label"
    FAIL=$((FAIL + 1))
  fi
}

BODY=$(curl $CURL_OPTS -s "$URL")
HTTP=$(curl $CURL_OPTS -s -o /dev/null -w "%{http_code}" "$URL")

echo ""
echo "HTTP status: $HTTP"
check "HTTP 200" "$([ "$HTTP" = "200" ] && echo 1 || echo 0)"

# SSR content check: sections rendered server-side (not empty main)
HAS_SECTIONS=$(echo "$BODY" | grep -c 'cms-section\|data-ve-section-id\|class="cms-' || true)
check "SSR sections rendered (count: $HAS_SECTIONS)" "$([ "$HAS_SECTIONS" -gt 0 ] && echo 1 || echo 0)"

# Note: BAILOUT_TO_CLIENT_SIDE_RENDERING is expected (useSearchParams triggers it)
# What matters is that the Suspense fallback renders content, not an empty div

# Expected section content
echo ""
echo "Section content checks:"
for title in "Gemüsegenossenschaft" "Geisshof" "Mission" "Gotti" "Geschichte" "Mitmachen" "kennenlernen"; do
  FOUND=$(echo "$BODY" | grep -ci "$title" || true)
  check "Contains '$title' ($FOUND)" "$([ "$FOUND" -gt 0 ] && echo 1 || echo 0)"
done

# Images from CMS (in HTML or JS bundles)
echo ""
echo "Media checks:"
IMG_COUNT=$(echo "$BODY" | grep -oi 'cms\.bioco\.ch/site/assets' | wc -l | tr -d ' ')
check "CMS images referenced (count: $IMG_COUNT, expect >= 1)" "$([ "$IMG_COUNT" -ge 1 ] && echo 1 || echo 0)"

# Timeline elements (rendered client-side, check for data in page payload)
TIMELINE=$(echo "$BODY" | grep -oi 'timeline' | wc -l | tr -d ' ')
check "Timeline references (count: $TIMELINE, expect >= 6)" "$([ "$TIMELINE" -ge 6 ] && echo 1 || echo 0)"

# No error boundary
echo ""
echo "Error checks:"
HAS_ERROR=$(echo "$BODY" | grep -c '>Fehler</h1>' || true)
check "No error boundary" "$([ "$HAS_ERROR" = "0" ] && echo 1 || echo 0)"

HAS_STALE=$(echo "$BODY" | grep -c 'Etwas ist schiefgelaufen' || true)
check "No stale error message" "$([ "$HAS_STALE" = "0" ] && echo 1 || echo 0)"

# Summary
echo ""
echo "Results: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ] && echo "ALL TESTS PASSED" || echo "SOME TESTS FAILED"
exit "$FAIL"
