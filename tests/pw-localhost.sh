#!/bin/bash
set -e
echo "=== Phase 2: ProcessWire Localhost Test ==="
echo "Run this on the cPanel server after deployment."

echo "[1] Testing health endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/cms/api/health)
if [ "$STATUS" = "200" ]; then
  echo "OK: /api/health returned 200"
else
  echo "FAIL: /api/health returned $STATUS"
  exit 1
fi

echo "[2] Testing content endpoint..."
BODY=$(curl -s http://localhost/cms/api/content/homepage)
if echo "$BODY" | grep -q "hero"; then
  echo "OK: /api/content/homepage contains hero data"
else
  echo "FAIL: /api/content/homepage missing hero data"
  exit 1
fi

echo "[3] Checking CORS headers (should be absent for localhost)..."
CORS=$(curl -s -D - -o /dev/null http://localhost/cms/api/health | grep -i "access-control-allow-origin" || true)
echo "CORS header: ${CORS:-none (expected for same-origin)}"

echo "=== All Phase 2 checks passed ==="
