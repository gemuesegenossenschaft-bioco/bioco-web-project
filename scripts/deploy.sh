#!/bin/bash
# Deploy bioco.ch Next.js frontend + CMS templates to cPanel
# Build locally, rsync standalone output, restore sharp, restart Node.js
set -e
BRANCH=${1:-}
DEPLOY_HOST="bioco@193.33.128.160"
DEPLOY_DIR="/home/bioco/bioco-frontend"
CMS_DIR="/home/bioco/public_html/cms/site/templates"
CMS_MODULES_DIR="/home/bioco/public_html/cms/site/modules"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$LOCAL_DIR/frontend"

if [ -n "$BRANCH" ]; then
  echo "=== Updating $BRANCH locally ==="
  git -C "$LOCAL_DIR" checkout "$BRANCH"
  git -C "$LOCAL_DIR" pull origin "$BRANCH"
else
  BRANCH="$(git -C "$LOCAL_DIR" rev-parse --abbrev-ref HEAD)"
  echo "=== Building current worktree ($BRANCH) ==="
fi

echo "=== Building locally ==="
cd "$FRONTEND_DIR"
npm ci
npm run build

echo "=== Uploading standalone output ==="
rsync -avzc --delete \
  --filter='P /start.sh' \
  --filter='P /healthcheck.sh' \
  --exclude='start.sh' \
  --exclude='healthcheck.sh' \
  .next/standalone/ "$DEPLOY_HOST:$DEPLOY_DIR/"
rsync -avzc --delete .next/static/ "$DEPLOY_HOST:$DEPLOY_DIR/.next/static/"
rsync -av --delete public/ "$DEPLOY_HOST:$DEPLOY_DIR/public/"

echo "=== Restoring sharp Linux bindings ==="
ssh "$DEPLOY_HOST" '
  cp -r /tmp/sharp-pkg/node_modules/@img/sharp-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ 2>/dev/null || echo "WARN: sharp-linux-x64 not found"
  cp -r /tmp/sharp-pkg/node_modules/@img/sharp-libvips-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ 2>/dev/null || echo "WARN: sharp-libvips-linux-x64 not found"
  rm -rf /home/bioco/bioco-frontend/node_modules/@img/sharp-darwin-arm64 /home/bioco/bioco-frontend/node_modules/@img/sharp-libvips-darwin-arm64 2>/dev/null
'

echo "=== Uploading healthcheck script ==="
rsync -avzc "$LOCAL_DIR/scripts/healthcheck.sh" "$DEPLOY_HOST:$DEPLOY_DIR/healthcheck.sh"
ssh "$DEPLOY_HOST" "chmod +x $DEPLOY_DIR/healthcheck.sh && test -x $DEPLOY_DIR/healthcheck.sh && bash -n $DEPLOY_DIR/healthcheck.sh"
ssh "$DEPLOY_HOST" 'grep -F '\''kill "$oldpid"'\'' /home/bioco/bioco-frontend/start.sh >/dev/null'

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
ssh "$DEPLOY_HOST" "mkdir -p $CMS_MODULES_DIR/ProcessContentPlanning"
rsync -avzc \
  "$LOCAL_DIR/site/modules/ProcessContentPlanning/ProcessContentPlanning.module.php" \
  "$LOCAL_DIR/site/modules/ProcessContentPlanning/planning.css" \
  "$DEPLOY_HOST:$CMS_MODULES_DIR/ProcessContentPlanning/"

echo "=== Removing stale legacy planning module path ==="
ssh "$DEPLOY_HOST" '
  legacy_module="/home/bioco/public_html/cms/site/modules/ProcessContentPlanning.module.php"
  legacy_css="/home/bioco/public_html/cms/site/modules/planning.css"
  if [ -f "$legacy_module" ]; then
    cp "$legacy_module" "$legacy_module.bak.$(date +%Y%m%d%H%M%S)"
    rm -f "$legacy_module"
  fi
  if [ -f "$legacy_css" ]; then
    cp "$legacy_css" "$legacy_css.bak.$(date +%Y%m%d%H%M%S)"
    rm -f "$legacy_css"
  fi
'

echo "=== Refreshing ProcessWire modules ==="
ssh "$DEPLOY_HOST" 'php <<'"'"'PHP'"'"'
<?php
chdir("/home/bioco/public_html/cms");
$_SERVER["HTTP_HOST"] = "cms.bioco.ch";
$_SERVER["REQUEST_URI"] = "/";
include "index.php";
$admin = $users->get("roles=superuser");
if ($admin->id) {
    $users->setCurrentUser($admin);
}
$modules->refresh();
$module = $modules->get("ProcessContentPlanning");
$ref = new ReflectionClass($module);
$path = $ref->getFileName();
echo "Loaded module: {$path}\n";
if ($path !== "/home/bioco/public_html/cms/site/modules/ProcessContentPlanning/ProcessContentPlanning.module.php") {
    fwrite(STDERR, "Unexpected module path\n");
    exit(1);
}
PHP'

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
ssh "$DEPLOY_HOST" '
  set -e
  local_status=$(curl -s -o /tmp/bioco-deploy-root.html -w "%{http_code}" http://127.0.0.1:49154/)
  echo "Local: HTTP $local_status"
  test "$local_status" = "200"
  ! grep -q -E "Fehler|Etwas ist schiefgelaufen" /tmp/bioco-deploy-root.html
  test -x /home/bioco/bioco-frontend/healthcheck.sh
  /home/bioco/bioco-frontend/healthcheck.sh
  worker_count=$(ps -eo comm,args | awk "\$1 ~ /^next-server/ {count++} END {print count+0}")
  echo "Workers: $worker_count"
  test "$worker_count" = "1"
  ps -eo pid,ppid,comm,args | awk "\$3 ~ /^next-server/ {print}"
'
external_status=$(curl -s -o /tmp/bioco-deploy-external.html -w "%{http_code}" --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/)
echo "External: HTTP $external_status"
test "$external_status" = "200"
! grep -q -E "Fehler|Etwas ist schiefgelaufen" /tmp/bioco-deploy-external.html
ssh "$DEPLOY_HOST" 'test ! -f /home/bioco/public_html/cms/site/modules/ProcessContentPlanning.module.php'
ssh "$DEPLOY_HOST" 'test ! -f /home/bioco/public_html/cms/site/modules/planning.css'
echo "Deployed $BRANCH."
