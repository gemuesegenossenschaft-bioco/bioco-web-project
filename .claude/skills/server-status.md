# /server-status

Check bioco.ch server health.

## Steps

Run these in parallel:

1. Check Node.js process:
   ```bash
   ssh bioco@193.33.128.160 'pgrep -x next-server && echo "Node.js running" || echo "Node.js NOT running"'
   ```

2. Check local HTTP:
   ```bash
   ssh bioco@193.33.128.160 'curl -s -o /dev/null -w "Local: HTTP %{http_code} (%{time_total}s)\n" http://127.0.0.1:49154/'
   ```

3. Check external HTTP:
   ```bash
   curl -s -o /dev/null -w "External: HTTP %{http_code} | TTFB: %{time_starttransfer}s\n" --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/
   ```

4. Check recent logs:
   ```bash
   ssh bioco@193.33.128.160 'tail -10 /home/bioco/logs/nextjs.log'
   ```

5. Check sharp status:
   ```bash
   ssh bioco@193.33.128.160 'ls /home/bioco/bioco-frontend/node_modules/@img/'
   ```

## Expected healthy state
- 1 next-server process
- Local HTTP 200
- External HTTP 200, TTFB < 200ms
- No sharp errors in log
- `@img/` contains: `colour`, `sharp-linux-x64`, `sharp-libvips-linux-x64`
