#!/bin/bash
set -e
echo "=== Phase 5: Deploy Staging Test ==="
echo "Run this after SSH into cPanel."

HOST=${1:-bioco.ch}

echo "[1] Checking repo exists..."
test -d /home/bioco/bioco-web-project/.git && echo "OK: repo cloned" || { echo "FAIL: repo not found"; exit 1; }

echo "[2] Checking Node.js app dir..."
test -d /home/bioco/bioco-frontend && echo "OK: app dir exists" || { echo "FAIL: app dir missing"; exit 1; }

echo "[3] Checking standalone server.js..."
test -f /home/bioco/bioco-frontend/server.js && echo "OK: server.js deployed" || { echo "FAIL: server.js missing"; exit 1; }

echo "[4] Testing frontend health..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$HOST/")
if [ "$STATUS" = "200" ]; then
  echo "OK: https://$HOST/ returned 200"
else
  echo "FAIL: https://$HOST/ returned $STATUS"
fi

echo "[5] Testing CMS API health..."
CMS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://cms.$HOST/api/health")
if [ "$CMS_STATUS" = "200" ]; then
  echo "OK: CMS API health returned 200"
else
  echo "FAIL: CMS API health returned $CMS_STATUS"
fi

echo "=== Phase 5 checks complete ==="
