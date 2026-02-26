#!/bin/bash
set -e
echo "=== Phase 4: Security Headers Test ==="

URL=${1:-http://localhost:3000}

echo "[1] Fetching headers from $URL..."
HEADERS=$(curl -s -D - -o /dev/null "$URL")

check_header() {
  if echo "$HEADERS" | grep -qi "$1"; then
    echo "OK: $1 present"
  else
    echo "FAIL: $1 missing"
    FAILED=1
  fi
}

check_header "X-Content-Type-Options"
check_header "X-Frame-Options"
check_header "X-XSS-Protection"
check_header "Referrer-Policy"
check_header "Permissions-Policy"

echo "[2] Testing redirect /ernte -> /gemuese..."
REDIR=$(curl -s -o /dev/null -w "%{http_code}" "$URL/ernte")
if [ "$REDIR" = "308" ] || [ "$REDIR" = "301" ]; then
  echo "OK: /ernte redirects ($REDIR)"
else
  echo "WARN: /ernte returned $REDIR (expected 301/308)"
fi

echo "[3] Testing redirect /depots -> /standorte-depots..."
REDIR=$(curl -s -o /dev/null -w "%{http_code}" "$URL/depots")
if [ "$REDIR" = "308" ] || [ "$REDIR" = "301" ]; then
  echo "OK: /depots redirects ($REDIR)"
else
  echo "WARN: /depots returned $REDIR (expected 301/308)"
fi

if [ -n "$FAILED" ]; then
  echo "=== Some headers missing ==="
  exit 1
fi
echo "=== All Phase 4 checks passed ==="
