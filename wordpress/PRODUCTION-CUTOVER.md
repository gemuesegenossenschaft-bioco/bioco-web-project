# Production cutover review

These helpers generate config and preserve recovered content. They do not connect
to a server, deploy, seed content, alter staging releases, or change old root files.
Production operations belong to the orchestrator.

Use the vanilla clone at `/home/bioco/wordpress-production` and the symlink
`/home/bioco/public_html/_bioco_wp` pointing there. Use the existing database with
separate `bcp20261009_` InnoDB tables. Keep database credentials and real Turnstile
keys outside this public repository. Set WordPress `home` and `siteurl` to
`https://bioco.ch`, environment to `production`, and `blog_public` to `1`.
Recommended private wp-config values:

```php
define('WP_HOME', 'https://bioco.ch');
define('WP_SITEURL', 'https://bioco.ch');
define('WP_CONTENT_URL', 'https://bioco.ch/wp-content');
define('WP_ENVIRONMENT_TYPE', 'production');
```

Do not put `/_bioco_wp` into either WordPress URL. Do not copy the clone's default
WordPress rewrite block into the root config. Keep the current and older recovered
texts, media, drafts, and private backups. No forced seed import is part of cutover.

Generate the root config for review, outside the document root:

```sh
python3 wordpress/scripts/generate-production-routing.py > /private/review/production.htaccess
```

The parent root config uses `FilesMatch` with `AddHandler application/x-httpd-ea-php81`.
Configure the private production WordPress child's `.htaccess` explicitly:

```apache
<FilesMatch "\.(?:php|php8|phtml)$">
    SetHandler application/x-httpd-alt-php82
</FilesMatch>
<FilesMatch "^(?:wp-config|wp-settings)\.php$">
    Require all denied
</FilesMatch>
```

The orchestrator has configured this explicit `SetHandler` in production. A plain
child `AddHandler` alone may be overridden by the parent's `FilesMatch` handler.
The child also denies requests for `wp-config.php` and `wp-settings.php`.
Install the private clone guard in the child's `.htaccess` **outside** the
`# BEGIN WordPress` / `# END WordPress` markers, preserving the handler above:

```sh
python3 wordpress/scripts/generate-production-routing.py --private-child-guard
```

WordPress can regenerate its own rewrite block during administration. Child
`RewriteEngine` rules override root rules, so the root guard alone cannot protect
direct `/_bioco_wp` requests. The child guard uses the original request and
`InheritDownBefore` to remain effective with descendant rewrite rules. Public URLs
rewritten into the clone remain accessible. Back up both files, install atomically,
and rerun the editor asset gate after an admin login or permalink update.
Do not switch root PHP globally. Preserve CMS and other vhost local handlers.
Check Matomo and ProcessWire before and after the switch; the generator does not
install child handlers. The CLI PHP version cannot establish the web handler.
Production has used this root routing since 9 October 2026.

Apache 2.4 rules route core directories and PHP endpoints into the clone before
any dormant root files can run. `/wp-admin` redirects to the public slash URL.
`THE_REQUEST` and `REDIRECT_STATUS` guards distinguish direct internal-path access
from rewrite rounds. `[END]` stops repeated WordPress rewrites. Child apps and
other hosts use `[L]` so their per-directory rules can still run. Robots and
sitemap requests go through production WordPress, bypassing the old robots symlink.
Other frontend paths use the clone's index. `/cms`, `/matomo`, `/cloud`, `/rezepte`,
`/wiki`, `/.well-known`, `/images`, and `/_next/static` pass through; other vhosts pass through; www redirects
to apex. Existing directories and root PHP files remain on disk.

Default deny policy v1 is defined in `generate-production-routing.py`. It denies
internal clone URLs, dot files except `.well-known`, config/source manifests,
private/vendor/backup trees, and database/archive/backup/log extensions. Archive
extensions also block public ZIP downloads; review legitimate download needs before
activation. Preserve required existing cPanel/security directives after reviewing
the combined file. Do not append an old Next catch-all or old WordPress rewrite
block that would compete with this routing.

The orchestrator's switch sequence:

1. Save the current root `.htaccess` and dedicated production database/files
   backups in a private directory outside `public_html`. Keep Next on `:49154`
   alive. Keep staging and the original recovery untouched.
2. Verify symlink ownership, Apache symlink permissions, clone core files, private
   config, production URLs/tables, media, mail transport, and real Turnstile.
   Enable the local adapter only in production. See [membership adapters](web/app/mu-plugins/bioco-forms/MEMBERSHIP.md).
3. Validate the generated config on the actual Apache host, including existing
   directives. Stage the reviewed file in the same filesystem as root `.htaccess`
   and atomically rename it over the root config. Never replace old root directories.
4. Check `/`, `/wir/`, `/wp-admin`, `/wp-admin/`, `/wp-login.php` including redirect
   chains, core assets, REST membership/replay, robots and sitemap, direct/encoded
   internal-path denial, sensitive files, www, CMS, Matomo, independent paths,
   PHP handler selection, and public indexing. Confirm no `/_bioco_wp` canonical
   redirects or login/admin loops. Inspect notification states in admin.
5. Roll back by atomically restoring the saved `.htaccess`. Next remains available
   on its existing port. Retain production tables/files and any new registrations;
   never restore an older database over accepted registrations.

Unit tests execute the generator and a limited rule evaluator. They cannot prove
Apache URL decoding, DirectoryIndex, symlink access, vhost inheritance, PHP handler
selection, WordPress canonical behavior, InnoDB behavior, or mail delivery. Actual
Apache/WordPress integration remains an orchestrator gate.

# Recovered CMS archive

The supplied safe sample has `page`, `seo.title`, `seo.description`, and `sections`
plus arbitrary source fields. This helper is for future manual archival. Current
recovery and SEO preservation are handled separately by the orchestrator; do not
rerun an import for cutover. Supply an explicit private curated directory containing
only per-page section JSON in this shape. Do not pass the complete recovery
`cms-api` directory: homepage and collection snapshots have different shapes and
are deliberately rejected. Curate copies outside the public repository; never
modify recovered originals. It resolves existing WordPress pages by `page`
path. Unknown shapes/pages abort validation before any writes. It never creates
pages or changes `post_content`.

Run through WP-CLI on the production clone, using the released helper path:

```sh
wp --path=/home/bioco/wordpress-production eval-file /private/code/wordpress/scripts/archive-cms-source.php /private/recovery/curated-pages
wp --path=/home/bioco/wordpress-production eval-file /private/code/wordpress/scripts/archive-cms-source.php /private/recovery/curated-pages apply
```

Without `apply` this prints counts only. With `apply` it adds the complete original
JSON to protected hash-versioned `_bioco_cms_source_*` post meta. Repeated source
files do not replace prior versions. Only empty `rank_math_title` and
`rank_math_description` fields receive nonempty CMS values. Existing SEO survives;
conflicting CMS values remain in the archive. Canonical and robots values are
archived only. Archive meta is never registered for public REST access. Backups
must stay private; do not commit recovery files. A write failure can leave a
partial archival run; inspect and rerun, since completed entries are preserved.

# Cookieless Matomo

Set private `BIOCO_MATOMO_URL=https://matomo.bioco.ch` and
`BIOCO_MATOMO_SITE_ID=1` for the WordPress web process to retain the current
tracker. Both are required. Tracking stays off by default, on staging, and outside
production `bioco.ch`. bioco-core enqueues its native script with JSON-encoded
inline config. The queue sets tracker URL and site ID, disables cookies, then
tracks the page and enables link tracking before loading async `matomo.js`.
No form payload or personal-data events are added. Verify the web environment and
tracker request in the orchestrator's browser integration check.

This document describes operations, not a completed migration. The orchestrator
writes the German team handoff after observing actual migration results.
