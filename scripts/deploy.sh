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
rsync -avzc --delete --exclude='start.sh' --exclude='healthcheck.sh' .next/standalone/ "$DEPLOY_HOST:$DEPLOY_DIR/"
rsync -avzc --delete .next/static/ "$DEPLOY_HOST:$DEPLOY_DIR/.next/static/"
rsync -avzc --delete public/ "$DEPLOY_HOST:$DEPLOY_DIR/public/"

echo "=== Restoring sharp Linux bindings ==="
ssh "$DEPLOY_HOST" '
  cp -r /tmp/sharp-pkg/node_modules/@img/sharp-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ 2>/dev/null || echo "WARN: sharp-linux-x64 not found"
  cp -r /tmp/sharp-pkg/node_modules/@img/sharp-libvips-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ 2>/dev/null || echo "WARN: sharp-libvips-linux-x64 not found"
  rm -rf /home/bioco/bioco-frontend/node_modules/@img/sharp-darwin-arm64 /home/bioco/bioco-frontend/node_modules/@img/sharp-libvips-darwin-arm64 2>/dev/null
'

echo "=== Uploading healthcheck script ==="
rsync -avzc "$LOCAL_DIR/scripts/healthcheck.sh" "$DEPLOY_HOST:$DEPLOY_DIR/healthcheck.sh"
ssh "$DEPLOY_HOST" "chmod +x $DEPLOY_DIR/healthcheck.sh"

echo "=== Uploading CMS templates + hooks ==="
rsync -avzc \
  "$LOCAL_DIR/site/templates/admin.js" \
  "$LOCAL_DIR/site/templates/api.php" \
  "$LOCAL_DIR/site/templates/api-events.php" \
  "$LOCAL_DIR/site/templates/visual-editor.php" \
  "$LOCAL_DIR/site/templates/visual-editor-focus-fields.json" \
  "$LOCAL_DIR/site/templates/internal-doc.php" \
  "$LOCAL_DIR/site/templates/internal_docs_container.php" \
  "$LOCAL_DIR/site/templates/internal_docs_root.php" \
  "$DEPLOY_HOST:$CMS_DIR/"
rsync -avzc "$LOCAL_DIR/site/ready.php" "$DEPLOY_HOST:/home/bioco/public_html/cms/site/ready.php"

echo "=== Uploading CMS modules (internal docs + planning) ==="
rsync -avzc "$LOCAL_DIR/site/modules/ProcessContentPlanning/ProcessContentPlanning.module.php" \
  "$DEPLOY_HOST:/home/bioco/public_html/cms/site/modules/ProcessContentPlanning/"

echo "=== Uploading one-off CMS CLI scripts ==="
ssh "$DEPLOY_HOST" "mkdir -p /home/bioco/public_html/cms/cms"
rsync -avzc "$LOCAL_DIR/cms/setup-internal-docs.php" "$LOCAL_DIR/cms/import-bioco-doku.php" \
  "$DEPLOY_HOST:/home/bioco/public_html/cms/cms/"

echo "=== Restarting Node.js ==="
ssh "$DEPLOY_HOST" '
  # Primary kill path.
  for p in $(pgrep -a next-server 2>/dev/null | awk "{print \$1}"); do kill "$p"; done
  # Fallback: catch workers primary matcher can miss on this host.
  for p in $(ps -eo pid,comm | awk "\$2 ~ /^next-server/ {print \$1}"); do kill "$p"; done
  sleep 3
  rm -f /tmp/bioco-next-start.lock /tmp/bioco-next.pid
  rm -rf /home/bioco/bioco-frontend/.next/cache
  /home/bioco/bioco-frontend/start.sh
'

echo "=== Verifying ==="
sleep 3
ssh "$DEPLOY_HOST" 'curl -s -o /dev/null -w "Local: HTTP %{http_code}\n" http://127.0.0.1:49154/'
ssh "$DEPLOY_HOST" 'echo "Workers:"; ps -eo pid,ppid,comm,args | awk "\$3 ~ /^next-server/ {print}"'
echo "Deployed $BRANCH."
