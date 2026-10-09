#!/usr/bin/env python3
"""Read-only HTTP dependency gate, shared by staging and production."""

import argparse
import importlib.util
import json
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import urlsplit
from urllib.request import Request, urlopen

spec = importlib.util.spec_from_file_location(
    'bioco_routing', Path(__file__).with_name('generate-production-routing.py'))
routing = importlib.util.module_from_spec(spec)
spec.loader.exec_module(routing)

PUBLIC_ASSETS = tuple(
    f'/wp-includes/js/dist/vendor/{name}{suffix}.js'
    for name in routing.CORE_VENDOR_NAMES for suffix in ('', '.min')
)
PRIVATE_PATHS = (
    '/wp-config.php', '/.env', '/vendor/autoload.php',
    '/wp-includes/js/dist/vendor/config.php',
    '/wp-includes/js/dist/vendor/react.min.js.map',
    '/_bioco_wp/wp-includes/js/dist/vendor/react.min.js',
    '/_bioco_wp%2fwp-includes/js/dist/vendor/react.min.js',
)


def probe(base_url, path, public):
    row = {'path': path, 'public': public, 'ok': False}
    try:
        with urlopen(Request(base_url + path, headers={
            'User-Agent': 'bioco-editor-assets-gate/1', 'Cache-Control': 'no-cache',
        }), timeout=20) as response:
            body = response.read(4096)
            row.update(status=response.status, url=response.url,
                       content_type=response.headers.get_content_type())
            row['ok'] = (
                public and response.status == 200
                and urlsplit(response.url).netloc == urlsplit(base_url).netloc
                and '/_bioco_wp' not in urlsplit(response.url).path
                and response.headers.get_content_type() in (
                    'application/javascript', 'text/javascript', 'application/x-javascript')
                and bool(body.strip()) and b'<html' not in body.lower()
                and b'<!doctype' not in body.lower()
            )
    except HTTPError as error:
        row.update(status=error.code, url=error.url)
        row['ok'] = not public and error.code in (403, 404)
    except (URLError, TimeoutError, OSError) as error:
        row['error'] = str(error)
    return row


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--url', required=True)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    base_url = args.url.rstrip('/')
    parts = urlsplit(base_url)
    if (parts.scheme not in ('http', 'https') or not parts.netloc or parts.username
            or parts.password or parts.path or parts.query or parts.fragment):
        parser.error('--url must be a site origin without credentials or a path')
    targets = [(path, True) for path in PUBLIC_ASSETS]
    # The internal clone path is production-specific. Other denials apply on both.
    targets += [(path, False) for path in PRIVATE_PATHS
                if parts.hostname == 'bioco.ch' or not path.startswith('/_bioco_wp')]
    with ThreadPoolExecutor(max_workers=6) as pool:
        rows = list(pool.map(lambda target: probe(base_url, *target), targets))
    report = {'url': base_url, 'passed': all(row['ok'] for row in rows), 'assets': rows}
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    for row in rows:
        print(f"editor-asset={'passed' if row['ok'] else 'FAILED'} "
              f"status={row.get('status', 'network-error')} path={row['path']}")
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
