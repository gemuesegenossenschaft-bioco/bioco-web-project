# AGENTS.md

## Server Deploy Agent

When deploying to the Novatrend cPanel server:

1. **Always build locally.** Server builds fail (CloudLinux thread limits crash SWC/Rayon).
2. **Always rsync all three directories** in order: `.next/standalone/`, `.next/static/`, `public/`. The first rsync with `--delete` wipes the target; skipping the others loses images/static assets.
3. **Restart Node.js** after deploy: `pkill -f "node.*server.js"; sleep 2; /home/bioco/bioco-frontend/start.sh`
4. **Test via** `http://193.33.128.160:49152` (direct) or `curl --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/` (through Apache proxy).

## Server Setup Agent

When setting up Node.js on Novatrend/CloudLinux cPanel:

- **Do not rely on Passenger.** `mod_passenger` is not loaded in Apache on this server. The cPanel Node.js App UI creates config but Apache ignores it.
- **Use `.htaccess` RewriteRule [P]** to proxy from Apache to Node.js on a high port (49152).
- **Keep cPanel Passenger config** in `.htaccess` (cPanel overwrites it if removed). Add proxy rules above the Passenger block.
- **Env vars in `<IfModule Litespeed>` block** are ignored (server runs Apache, not LiteSpeed). Set env vars in `start.sh` instead.
- **Process keepalive:** use a cron job running `start.sh` every 5 minutes. The script checks `pgrep` before starting.
- **Node version:** use `source /home/bioco/nodevenv/bioco-frontend/18/bin/activate` before any `node`/`npm` commands via SSH.
- **Test from outside:** `127.0.0.1` on the server does NOT resolve to the `bioco.ch` vhost. Always test from a remote machine with `--resolve` or via the direct port.

## CMS Migration Agent

When running ProcessWire migrations:

- Upload `.php` script to `site/templates/` via FTP or rsync
- Create a bootstrap script in `/public_html/cms/` that includes `index.php` and the migration
- Run via `curl https://cms.bioco.ch/bootstrap-foo.php`
- **Delete the bootstrap script immediately** (it exposes PW internals)
- FTP: host `193.33.128.160`, user `gueney@bioco.ch`, FTPS with passive mode
