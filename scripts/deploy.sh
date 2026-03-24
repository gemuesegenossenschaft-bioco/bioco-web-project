#!/bin/bash
# Deploy bioco.ch Next.js frontend + CMS templates to cPanel
# Build locally, rsync standalone output, restore sharp, restart Node.js
set -e
BRANCH=${1:-main}
DEPLOY_HOST="bioco@193.33.128.160"
DEPLOY_DIR="/home/bioco/bioco-frontend"
CMS_DIR="/home/bioco/public_html/cms/site/templates"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$LOCAL_DIR/frontend"

echo "=== Building $BRANCH locally ==="
cd "$FRONTEND_DIR"
git checkout "$BRANCH"
git pull origin "$BRANCH"
npm ci
npm run build

echo "=== Uploading standalone output ==="
rsync -avzc --delete --exclude='start.sh' .next/standalone/ "$DEPLOY_HOST:$DEPLOY_DIR/"
rsync -avzc --delete .next/static/ "$DEPLOY_HOST:$DEPLOY_DIR/.next/static/"
rsync -avzc --delete public/ "$DEPLOY_HOST:$DEPLOY_DIR/public/"

echo "=== Restoring sharp Linux bindings ==="
ssh "$DEPLOY_HOST" '
  cp -r /tmp/sharp-pkg/node_modules/@img/sharp-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ 2>/dev/null || echo "WARN: sharp-linux-x64 not found"
  cp -r /tmp/sharp-pkg/node_modules/@img/sharp-libvips-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ 2>/dev/null || echo "WARN: sharp-libvips-linux-x64 not found"
  rm -rf /home/bioco/bioco-frontend/node_modules/@img/sharp-darwin-arm64 /home/bioco/bioco-frontend/node_modules/@img/sharp-libvips-darwin-arm64 2>/dev/null
'

echo "=== Uploading CMS templates + hooks ==="
rsync -avzc "$LOCAL_DIR/site/templates/admin.js" "$LOCAL_DIR/site/templates/api.php" "$LOCAL_DIR/site/templates/api-events.php" "$LOCAL_DIR/site/templates/visual-editor.php" "$DEPLOY_HOST:$CMS_DIR/"
rsync -avzc "$LOCAL_DIR/site/ready.php" "$DEPLOY_HOST:/home/bioco/public_html/cms/site/ready.php"

echo "=== Restarting Node.js ==="
ssh "$DEPLOY_HOST" '
  for p in $(pgrep -x next-server 2>/dev/null); do kill $p; done
  sleep 3
  rm -f /tmp/bioco-next-start.lock /tmp/bioco-next.pid
  rm -rf /home/bioco/bioco-frontend/.next/cache
  /home/bioco/bioco-frontend/start.sh
'

echo "=== Verifying ==="
sleep 3
ssh "$DEPLOY_HOST" 'curl -s -o /dev/null -w "Local: HTTP %{http_code}\n" http://127.0.0.1:49154/'
echo "Deployed $BRANCH."
