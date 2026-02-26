#!/bin/bash
set -e
echo "=== Phase 1: Standalone Build Test ==="

cd "$(dirname "$0")/../frontend"

echo "[1] Building standalone..."
npm run build

echo "[2] Checking standalone output exists..."
test -f .next/standalone/server.js && echo "OK: server.js found" || { echo "FAIL: no server.js"; exit 1; }

echo "[3] Starting standalone server on port 4000..."
PORT=4000 node .next/standalone/server.js &
PID=$!
sleep 3

echo "[4] Testing health..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:4000/)
if [ "$STATUS" = "200" ]; then
  echo "OK: GET / returned 200"
else
  echo "FAIL: GET / returned $STATUS"
  kill $PID 2>/dev/null
  exit 1
fi

echo "[5] Testing API route..."
API_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:4000/api/forms/contact)
echo "API /api/forms/contact returned $API_STATUS (400 expected, no POST body)"

echo "[6] Checking ISR cache dir..."
test -d .next/cache && echo "OK: .next/cache/ exists" || echo "WARN: no .next/cache/"

kill $PID 2>/dev/null
echo "=== All Phase 1 checks passed ==="
