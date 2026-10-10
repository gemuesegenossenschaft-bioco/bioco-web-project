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
Do not switch root PHP globally. Preserve CMS and other vhost local handlers.
Check Matomo and ProcessWire before and after the switch; the generator does not
install child handlers. The CLI PHP version cannot establish the web handler.
The public root `.htaccess` has not been switched yet.

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

## Retention and decommission gate

WordPress has served production since 9 October 2026. Retain the Next server on
port 49154, its watchdog, ProcessWire, the previous root routing file and private
recovery backups for at least 30 days, through 8 November 2026. Extend retention
while any gate below is open. This checklist does not authorize deletion.

- [ ] Confirm current production registrations, editorial content and uploads are
      covered by a scheduled off-server backup. Restore a retrieved backup to an
      isolated target and compare records and files (#153).
- [ ] Rehearse both routing states in an isolated Apache setup. Record the saved
      routing file, commands and response bodies. Never restore an older database
      over accepted registrations (#100).
- [ ] Verify production mail inbox delivery, Matomo counting and rollback health.
      A successful transport return or an HTTP 200 is insufficient.
- [ ] Obtain the owner's approval that WordPress owns the retained content and
      that remaining migration, editing and delivery defects are accepted.
- [ ] After those gates and retention pass, inventory references to
      `cms.bioco.ch`, ProcessWire APIs, legacy assets and the Next port. Redirect
      public links and retain required media before removing any service.
- [ ] With a dedicated approved maintenance window, remove the Next watchdog
      cron, stop its one verified worker, then retire `start.sh`, `healthcheck.sh`,
      sharp bindings and the old frontend directory. Verify WordPress routes,
      admin, forms, assets and independent vhosts afterward.
- [ ] Export the final ProcessWire database, content and media privately. Remove
      its vhost and credentials only after the owner accepts that archive. Keep
      `docs.bioco.ch` and Matomo independent of this cleanup.
- [ ] Record the operator, UTC time, retained backup location, smoke results and
      recovery procedure. Do not put credentials or private backup URLs in Git.

## Operations added by the remaining-ticket pass

The consent controls, newsletter administration and hardening ship as owned
mu-plugin code. Use the canonical staging release for code deployment. A code
release does not overwrite page content or install Composer dependencies.

Initialize the editable consent copy explicitly after deploying the code:

```sh
wp --path=<staging-wordpress-root> --user=<administrator> bioco consent
wp --path=<staging-wordpress-root> --user=<administrator> bioco consent --apply
```

Saved text wins on subsequent setup runs, including an intentionally empty
value. Editors can fill the fields under Datenschutz-Texte. Missing configuration
keeps maps and analytics disabled. Consent is stored locally for 180 days. The
visitor can reject both categories, choose either category, or withdraw consent
through the persistent settings button. Addresses remain visible without maps.
Matomo uses its documented [consent API](https://developer.matomo.org/guides/tracking-consent)
to stop subsequent tracking after withdrawal.

Newsletter administrators use Newsletter > Versenden / Export. Confirmed test
subscribers use the existing double-opt-in flow. Every message contains a visible
unsubscribe link and the [one-click unsubscribe headers](https://www.rfc-editor.org/rfc/rfc8058.html).
GET displays a confirmation; POST performs unsubscribe. A new opt-in invalidates
earlier links. CSV export and sending require administrator permission and a
nonce. Mail is plain text through the existing WP Mail SMTP transport.

Bulk mail uses single cron events in batches of 20. Each campaign records sent,
failed, skipped, pending and uncertain outcomes. A transport crash leaves a
`sending` record; no automatic resend occurs. An abandoned campaign lock is left
in place for manual investigation. Verify that the worker stopped and inspect
delivery records before removing `bioco_newsletter_lock_<campaign-id>` and
rescheduling `bioco_newsletter_batch`. Record inbox receipt separately from
transport acceptance. Configure the provider's DKIM signature to cover the
unsubscribe headers before real bulk sending.

The security module denies anonymous REST user enumeration while retaining
authenticated editor routes, limits failed login attempts per connected peer,
and disables XML-RPC authentication. Generated production routing also denies
executable upload paths. Installing the code does not replace production routing
or change database grants; verify those independently before closing #163.

Composer now owns Rank Math and has a lockfile. Dependency updates run in a
private checkout with `composer install --no-dev`, backups and a staging check,
followed by the approved dependency release. Do not run `composer update` on
production or use the owned-code sync script as a dependency installer. No
dependency installation on a server is claimed by this change. The locked audit
reported no vulnerability advisories and the pre-existing abandoned
`roots/wp-password-bcrypt` package; resolving that warning remains part of #163.
