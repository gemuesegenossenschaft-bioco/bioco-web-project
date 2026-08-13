#!/usr/bin/env bash
set -euo pipefail

base_url=${BIOCO_STAGING_URL:-https://staging.bioco.ch}
resolve=${BIOCO_STAGING_RESOLVE:-staging.bioco.ch:443:193.33.128.160}
routes=(
  / /abos/ /aktuelles/ /anmeldung/ /anmeldung-danke/ /bioco-werden/
  /datenschutz/ /gemuese/ /impressum/ /kontakt/ /kundenportal/ /mitmachen/
  /newsletter/ /solawi/ /standorte-depots/ /statuten/ /tag-der-offenen-tuer/
  /warteliste/ /wir/ /intranet/
)

response=$(mktemp "${TMPDIR:-/tmp}/bioco-wp-render.XXXXXX")
trap 'rm -f -- "$response"' EXIT
curl_args=(-fsS --connect-timeout 5 --max-time 30)
if [[ -n $resolve ]]; then curl_args+=(--resolve "$resolve"); fi

for route in "${routes[@]}"; do
  code=$(curl "${curl_args[@]}" -o "$response" -w '%{http_code}' "${base_url}${route}")
  [[ $code == 200 ]] || { echo "FAIL ${route}: HTTP ${code}" >&2; exit 1; }
  python3 - "$response" "$route" <<'PY'
import sys
from html.parser import HTMLParser

class MainContent(HTMLParser):
    def __init__(self):
        super().__init__()
        self.depth = 0
        self.ignored = 0
        self.content = False

    def handle_starttag(self, tag, attrs):
        if tag == "main":
            self.depth += 1
            return
        if not self.depth:
            return
        if tag in {"script", "style", "template"}:
            self.ignored += 1
            return
        if not self.ignored and tag in {"img", "video", "audio", "iframe"}:
            attributes = dict(attrs)
            if attributes.get("src"):
                self.content = True

    def handle_endtag(self, tag):
        if self.depth and tag in {"script", "style", "template"} and self.ignored:
            self.ignored -= 1
        if tag == "main" and self.depth:
            self.depth -= 1

    def handle_data(self, data):
        if self.depth and not self.ignored and data.strip():
            self.content = True

parser = MainContent()
parser.feed(open(sys.argv[1], encoding="utf-8").read())
if not parser.content:
    raise SystemExit(f"FAIL {sys.argv[2]}: empty <main>")
PY
  printf 'PASS %s\n' "$route"
done

echo "WORDPRESS_STAGING_RENDER_GATE: PASS (${#routes[@]} routes)"
