#!/usr/bin/env python3
"""Bundle the Divi seed contract for both Bedrock and vanilla WordPress."""
import argparse
from pathlib import Path

root = Path(__file__).resolve().parents[1]
source = root / 'design-system/v1/manifest.json'
target = root / 'web/app/mu-plugins/bioco-core/assets/design-system.json'
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--check', action='store_true')
args = parser.parse_args()
if args.check:
    if not target.exists() or target.read_bytes() != source.read_bytes():
        raise SystemExit('Stale design-system bundle; run wordpress/scripts/build-divi-design-system.py')
else:
    target.write_bytes(source.read_bytes())
