# /deploy

Full deploy of bioco.ch frontend + CMS to Novatrend cPanel.

## Steps

1. Build frontend locally:
   ```bash
   cd frontend && npm ci && npm run build
   ```

2. Rsync standalone (exclude start.sh):
   ```bash
   rsync -avzc --delete --exclude='start.sh' frontend/.next/standalone/ bioco@193.33.128.160:/home/bioco/bioco-frontend/
   ```

3. Rsync static + public:
   ```bash
   rsync -avzc --delete frontend/.next/static/ bioco@193.33.128.160:/home/bioco/bioco-frontend/.next/static/
   rsync -avzc --delete frontend/public/ bioco@193.33.128.160:/home/bioco/bioco-frontend/public/
   ```

4. Restore sharp Linux bindings (REQUIRED, rsync --delete wipes them):
   ```bash
   ssh bioco@193.33.128.160 'cp -r /tmp/sharp-pkg/node_modules/@img/sharp-linux-x64 /tmp/sharp-pkg/node_modules/@img/sharp-libvips-linux-x64 /home/bioco/bioco-frontend/node_modules/@img/ && rm -rf /home/bioco/bioco-frontend/node_modules/@img/sharp-darwin-arm64 /home/bioco/bioco-frontend/node_modules/@img/sharp-libvips-darwin-arm64 2>/dev/null'
   ```

5. Rsync CMS templates:
   ```bash
   rsync -avzc site/templates/admin.js site/templates/api.php site/templates/api-events.php bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/
   ```

6. Restart Node.js (use `pgrep -x`, never `-f`):
   ```bash
   ssh bioco@193.33.128.160 'for p in $(pgrep -x next-server); do kill $p; done; sleep 3; rm -f /tmp/bioco-next-start.lock /tmp/bioco-next.pid; rm -rf /home/bioco/bioco-frontend/.next/cache; /home/bioco/bioco-frontend/start.sh'
   ```

7. Verify (wait a few seconds for startup):
   ```bash
   ssh bioco@193.33.128.160 'curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:49154/'
   curl -s -o /dev/null -w "HTTP %{http_code} | TTFB: %{time_starttransfer}s" --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/
   ```

## Troubleshooting

- **503 after deploy**: check `ssh bioco@193.33.128.160 'tail -20 /home/bioco/logs/nextjs.log'`. Likely sharp missing or port conflict.
- **EADDRINUSE**: old process alive. `pgrep -x next-server` to find PID, kill it, wait 3s.
- **sharp error in log**: restore both `sharp-linux-x64` AND `sharp-libvips-linux-x64`.

## Alternative

Run the deploy script directly:
```bash
scripts/deploy.sh main
```
