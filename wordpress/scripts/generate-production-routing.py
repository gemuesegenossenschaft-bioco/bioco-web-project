#!/usr/bin/env python3
"""Print reviewable Apache 2.4 config. Never edits a server or document root."""

import json
import re
from pathlib import Path

REDIRECT_MANIFEST = Path(__file__).resolve().parents[1] / 'web/app/mu-plugins/bioco-core/content/redirects.json'
LEAFLET_PATTERN = (
    r'wp-content/mu-plugins/bioco-core/assets/vendor/leaflet/'
    r'(?:leaflet\.(?:css|js)|images/(?:layers(?:-2x)?|marker-icon(?:-2x)?|marker-shadow)\.png)'
)


def asset_redirect_rules():
    """Only static PDF redirects under wp-content/uploads need Apache routing."""
    rules = []
    for redirect in json.loads(REDIRECT_MANIFEST.read_text(encoding='utf-8')):
        source = redirect['source']
        if not source.startswith('/wp-content/'):
            continue
        destination = redirect['destination']
        for path in (source, destination):
            if (not re.fullmatch(r'/(?:[\w.-]+(?:/[\w.-]+)*/?)?', path)
                    or any(part in ('.', '..') for part in path.split('/'))):
                raise ValueError(f'Invalid asset redirect path: {path!r}')
        if (not source.startswith('/wp-content/uploads/') or not source.endswith('.pdf')
                or source == destination or redirect.get('permanent') is not True):
            raise ValueError(f'Unsupported asset redirect: {source!r}')
        rules.append(f'RewriteRule ^{re.escape(source.lstrip("/"))}$ {destination} [R=301,END]')
    return rules


DENY_VERSION = 1
# Versioned policy: dot files, private/config/source trees, database/archive backups.
DENY_PATTERNS = (
    r"(^|/)\.(?!well-known(?:/|$))",
    r"(^|/)(?:wp-config(?:-sample)?\.php|wp-settings\.php|composer\.(?:json|lock)|package(?:-lock)?\.json)(?:$|[./~])",
    r"(^|/)(?:vendor|node_modules|private|backups?|cms-api)(?:/|$)",
    r"(?:\.(?:sql(?:\.gz)?|bak|backup|old|orig|save|swp|log|ini|env|zip|tar(?:\.gz)?|tgz|7z)|~)$",
)
CORE_ENDPOINTS = (
    "index", "wp-activate", "wp-blog-header", "wp-comments-post", "wp-cron",
    "wp-links-opml", "wp-load", "wp-login", "wp-mail", "wp-signup",
    "wp-trackback", "xmlrpc",
)


def generate():
    lines = [
        "# bioco production routing. Apache 2.4; review before atomic switch.",
        f"# Default deny policy v{DENY_VERSION}.",
        "Options -Indexes -MultiViews", "RewriteEngine On",
        # Shared document roots must not capture CMS/Matomo or other vhosts.
        "RewriteCond %{HTTP_HOST} !^(?:www\\.)?bioco\\.ch(?::[0-9]+)?$ [NC]",
        "RewriteRule ^ - [L]",
        # Apache matches decoded paths. Block encoded direct requests too;
        # internal redirect rounds carry REDIRECT_STATUS.
        "RewriteCond %{ENV:REDIRECT_STATUS} ^$",
        "RewriteRule ^_bioco_wp(?:/|$) - [F,END,NC]",
        # THE_REQUEST is unchanged by internal rewrite/DirectoryIndex rounds.
        "RewriteCond %{THE_REQUEST} \\s/+_bioco_wp(?:[/\\s?]|%[0-9a-f]{2}) [NC]",
        "RewriteRule ^ - [F,END]",
    ]
    # Only these shipped Leaflet files may bypass the vendor denial.
    lines += [
        f"RewriteRule ^_bioco_wp/{LEAFLET_PATTERN}$ - [END]",
        "RewriteCond %{HTTP_HOST} ^www\\.bioco\\.ch(?::[0-9]+)?$ [NC]",
        f"RewriteRule ^{LEAFLET_PATTERN}$ https://bioco.ch%{{REQUEST_URI}} [R=301,END,NE]",
        f"RewriteRule ^({LEAFLET_PATTERN})$ /_bioco_wp/$1 [END]",
    ]
    lines += [f"RewriteRule {pattern} - [F,END,NC]" for pattern in DENY_PATTERNS]
    lines += [
        "RewriteCond %{HTTP_HOST} ^www\\.bioco\\.ch(?::[0-9]+)?$ [NC]",
        "RewriteRule ^ https://bioco.ch%{REQUEST_URI} [R=301,END,NE]",
        # Internal rounds cannot enter the frontend rewrite again.
        "RewriteRule ^_bioco_wp(?:/|$) - [END]",
        "RewriteRule ^(?:cms|matomo|cloud|rezepte|wiki|images|\\.well-known)(?:/|$) - [L]",
        "RewriteRule ^_next/static(?:/|$) - [L]",
        # DirectorySlash must never redirect the browser to the internal path.
        "RewriteRule ^wp-admin$ /wp-admin/ [R=301,END]",
        *asset_redirect_rules(),
        "RewriteRule ^(wp-admin|wp-content|wp-includes)(/.*)?$ /_bioco_wp/$1$2 [END]",
        "RewriteRule ^(" + "|".join(CORE_ENDPOINTS) + r")\.php$ /_bioco_wp/$1.php [END]",
        # These must beat dormant files/symlinks in public_html.
        "RewriteRule ^(?:robots\\.txt|(?:wp-)?sitemap(?:[^/]*)\\.(?:xml|xsl))$ /_bioco_wp/index.php [END]",
        "RewriteRule ^ /_bioco_wp/index.php [END]",
        "", "# Preserve old root PHP 8.1. The private WP child owns its PHP 8.2 handler.",
        '<FilesMatch "\\.(?:php|php8|phtml)$">', "  AddHandler application/x-httpd-ea-php81 .php .php8 .phtml", "</FilesMatch>",
    ]
    return "\n".join(lines) + "\n"


if __name__ == "__main__":
    print(generate(), end="")
