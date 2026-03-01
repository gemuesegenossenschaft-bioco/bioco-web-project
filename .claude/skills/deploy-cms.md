# /deploy-cms

CMS-only deploy. Uploads ProcessWire template files without rebuilding the frontend.

Use when only `site/templates/` files changed (api.php, api-events.php, admin.js).

## Steps

1. Rsync CMS templates:
   ```bash
   rsync -avzc site/templates/admin.js site/templates/api.php site/templates/api-events.php bioco@193.33.128.160:/home/bioco/public_html/cms/site/templates/
   ```

2. Verify API:
   ```bash
   curl -s --resolve bioco.ch:443:193.33.128.160 https://bioco.ch/cms/api/events/ | python3 -m json.tool | head -5
   ```

No restart needed. PHP changes take effect immediately.
