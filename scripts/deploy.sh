#!/bin/bash
set -e
BRANCH=${1:-develop}
REPO="/home/bioco/bioco-web-project"
DEPLOY="/home/bioco/bioco-frontend"

cd "$REPO"
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

cd frontend
npm ci --omit=dev
npm run build

rm -rf "$DEPLOY/.next"
cp -r .next/standalone/* "$DEPLOY/"
cp -r .next/static "$DEPLOY/.next/static"
cp -r public "$DEPLOY/public"

mkdir -p "$DEPLOY/tmp"
touch "$DEPLOY/tmp/restart.txt"
echo "Deployed $BRANCH. Passenger restarting."
