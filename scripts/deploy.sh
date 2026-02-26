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
rsync -avz --delete --exclude='start.sh' .next/standalone/ "$DEPLOY_HOST:$DEPLOY_DIR/"
rsync -avz --delete .next/static/ "$DEPLOY_HOST:$DEPLOY_DIR/.next/static/"
rsync -avz --delete public/ "$DEPLOY_HOST:$DEPLOY_DIR/public/"

echo "Restarting Node.js..."
ssh "$DEPLOY_HOST" 'pkill -f "node.*server.js.*49152" 2>/dev/null; sleep 1; /home/bioco/bioco-frontend/start.sh'
echo "Deployed $BRANCH."
