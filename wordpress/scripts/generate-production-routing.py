#!/usr/bin/env python3
"""Print reviewable Apache 2.4 config. Never edits a server or document root."""

import argparse
import json
import re
from pathlib import Path

REDIRECT_MANIFEST = Path(__file__).resolve().parents[1] / 'web/app/mu-plugins/bioco-core/content/redirects.json'
LEAFLET_PATTERN = (
    r'wp-content/mu-plugins/bioco-core/assets/vendor/leaflet/'
    r'(?:leaflet\.(?:css|js)|images/(?:layers(?:-2x)?|marker-icon(?:-2x)?|marker-shadow)\.png)'
)
# WordPress ships these browser dependencies under a directory named vendor.
# Keep the exception file-specific: it must never expose arbitrary vendor trees.
CORE_VENDOR_NAMES = (
    'lodash', 'moment', 'react', 'react-dom', 'react-jsx-runtime',
    'react-jsx-runtime-19', 'regenerator-runtime', 'wp-polyfill',
    'wp-polyfill-dom-rect', 'wp-polyfill-element-closest', 'wp-polyfill-fetch',
    'wp-polyfill-formdata', 'wp-polyfill-inert', 'wp-polyfill-node-contains',
    'wp-polyfill-object-fit', 'wp-polyfill-url',
)
CORE_VENDOR_PATTERN = (
    r'wp-includes/js/dist/vendor/(?:' + '|'.join(CORE_VENDOR_NAMES) + r')(?:\.min)?\.js'
)
EDITOR_PLUGIN_ASSET_PATTERN = (
    r'wp-content/plugins/seo-by-rank-math/vendor/cmb2/cmb2/'
    r'(?:css/cmb2\.min\.css|js/cmb2\.min\.js)'
)
# THE_REQUEST survives internal rewrites and DirectoryIndex. Match encoded letters
# as well: RewriteRule receives decoded paths, but THE_REQUEST does not.
INTERNAL_REQUEST_PATTERN = (
    r'\s+(?:https?://[^/\s]+)?/(?:/|%2f)*'
    r'(?:_|%5f)(?:b|%62)(?:i|%69)(?:o|%6f)(?:c|%63)(?:o|%6f)'
    r'(?:_|%5f)(?:w|%77)(?:p|%70)(?:/|%2f|\s|\?)'
)


def generate_private_child_guard():
    """Install outside WordPress markers, preserving PHP handlers and WP rules."""
    return '\n'.join((
        '# BEGIN bioco private clone guard',
        '<IfModule mod_rewrite.c>',
        'RewriteEngine On',
        # Descendant .htaccess rules must not override this immutable guard.
        'RewriteOptions InheritDownBefore',
        f'RewriteCond %{{THE_REQUEST}} {INTERNAL_REQUEST_PATTERN} [NC]',
        'RewriteRule ^ - [F,END]',
        '</IfModule>',
        '# END bioco private clone guard',
        '',
    ))


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
        f"RewriteCond %{{THE_REQUEST}} {INTERNAL_REQUEST_PATTERN} [NC]",
        "RewriteRule ^ - [F,END]",
    ]
    for pattern in (LEAFLET_PATTERN, CORE_VENDOR_PATTERN, EDITOR_PLUGIN_ASSET_PATTERN):
        lines += [
            f"RewriteRule ^_bioco_wp/{pattern}$ - [END]",
            "RewriteCond %{HTTP_HOST} ^www\\.bioco\\.ch(?::[0-9]+)?$ [NC]",
            f"RewriteRule ^{pattern}$ https://bioco.ch%{{REQUEST_URI}} [R=301,END,NE]",
            f"RewriteRule ^({pattern})$ /_bioco_wp/$1 [END]",
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
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--private-child-guard', action='store_true',
                        help='Print the guard required in the private WordPress .htaccess')
    args = parser.parse_args()
    print(generate_private_child_guard() if args.private_child_guard else generate(), end="")
