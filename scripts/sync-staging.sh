#!/bin/bash
#
# Sync Production CMS to Staging
# Usage: ./scripts/sync-staging.sh
#

set -e

echo "🔄 Syncing Production → Staging (bioco.ch → staging.bioco.ch)"
echo ""

# Safety check
read -p "⚠️  This will OVERWRITE staging database with production data. Continue? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "❌ Aborted."
    exit 1
fi

SERVER="bioco@193.33.128.160"

# Get MySQL credentials from config.php
echo "🔑 Reading MySQL credentials..."
ssh $SERVER "
DB_USER=\$(grep 'dbUser' /home/bioco/public_html/cms/site/config.php | sed \"s/.*'\([^']*\)'.*/\1/\")
DB_PASS=\$(grep 'dbPass' /home/bioco/public_html/cms/site/config.php | grep -v getenv | sed \"s/.*'\([^']*\)'.*/\1/\")

# 1. Backup staging database
echo '📦 Backing up staging database...'
mysqldump -u\$DB_USER -p\$DB_PASS bioco_staging > ~/backups/staging_backup_\$(date +%Y%m%d_%H%M%S).sql
echo '✅ Backup saved to ~/backups/'

# 2. Export production database
echo '📤 Exporting production database...'
mysqldump -u\$DB_USER -p\$DB_PASS bioco_cms > /tmp/prod_export.sql

# 3. Import to staging
echo '📥 Importing to staging database...'
mysql -u\$DB_USER -p\$DB_PASS bioco_staging < /tmp/prod_export.sql
"

# 4. Sync CMS files (exclude config, cache, sessions, uploaded files)
echo "📂 Syncing CMS templates and modules..."
ssh $SERVER "
rsync -av --delete \
  --exclude=site/config.php \
  --exclude=site/config-dev.php \
  --exclude=site/assets/cache/* \
  --exclude=site/assets/logs/* \
  --exclude=site/assets/sessions/* \
  --exclude=site/assets/backups/* \
  --exclude=site/assets/files/* \
  --exclude=site/assets/images/* \
  /home/bioco/public_html/cms/site/ \
  /home/bioco/public_html/bioco_staging/site/
"

# 5. Clear staging cache
echo "🧹 Clearing staging cache..."
ssh $SERVER "rm -rf /home/bioco/public_html/bioco_staging/site/assets/cache/*"

# 6. Clean up temp files
echo "🗑️  Cleaning up..."
ssh $SERVER "rm -f /tmp/prod_export.sql"

echo ""
echo "✅ Staging sync complete!"
echo ""
echo "Staging is now a copy of production:"
echo "  - Database: bioco_staging (same content as bioco_cms)"
echo "  - CMS files: synced (templates, modules, etc.)"
echo "  - Config: independent (still points to bioco_staging database)"
echo ""
echo "You can now:"
echo "  1. Edit content at https://staging.bioco.ch/processwire/"
echo "  2. Test without affecting production"
echo "  3. Pull data anytime by running this script again"
echo ""
