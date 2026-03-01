#!/bin/bash
# Deploy bioco.ch Next.js frontend to cPanel
# Build locally, rsync standalone output, restart Node.js
set -e
BRANCH=${1:-develop}
DEPLOY_HOST="bioco@193.33.128.160"
DEPLOY_DIR="/home/bioco/bioco-frontend"
LOCAL_DIR="$(cd "$(dirname "$0")/../frontend" && pwd)"

echo "Building $BRANCH locally..."
cd "$LOCAL_DIR"
git checkout "$BRANCH"
git pull origin "$BRANCH"
npm ci
npm run build

echo "Uploading standalone output..."
rsync -avzc --delete --exclude='start.sh' .next/standalone/ "$DEPLOY_HOST:$DEPLOY_DIR/"
rsync -avzc --delete .next/static/ "$DEPLOY_HOST:$DEPLOY_DIR/.next/static/"
rsync -avzc --delete public/ "$DEPLOY_HOST:$DEPLOY_DIR/public/"

echo "Restoring sharp Linux bindings..."
ssh "$DEPLOY_HOST" 'cp -r /tmp/sharp-pkg/node_modules/@img/sharp-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ 2>/dev/null || echo "WARN: sharp bindings not found in /tmp"'

echo "Restarting Node.js..."
ssh "$DEPLOY_HOST" 'for p in $(pgrep -f next-server 2>/dev/null); do kill $p; done; sleep 2; rm -f /tmp/bioco-next-start.lock /tmp/bioco-next.pid; rm -rf /home/bioco/bioco-frontend/.next/cache; /home/bioco/bioco-frontend/start.sh'
echo "Deployed $BRANCH."
