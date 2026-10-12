"""Explicit private-capture operator gate. See tests/README.md for the command.

The filename keeps these cases out of automatic CI discovery. When explicitly
selected, all four cases require their captures and fail rather than skip.
"""
import hashlib
import json
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from test_wordpress_price_adoption import run


@pytest.mark.parametrize('environment', ['staging', 'production'])
@pytest.mark.parametrize('kind,sha', [
    ('table', 'd2c2b7dbc54c38295e698de0e4e0d5e4a34c8571fef5b3ec5e2855a4c7bd7d3c'),
    ('calculator', 'b02c4e235128e159438277ac2601e5944cb0d7c64af521fd84fa9f478fec39e2'),
])
def test_captured_saved_page(environment, kind, sha):
    directory = Path(os.environ.get('BIOCO_PRICE_CAPTURE_DIR',
        Path.home() / '.cache/bioco-editor-202')).expanduser()
    path = directory / f'{environment}-price-pages.json'
    assert path.is_file(), f'Required private saved page snapshot missing: {path}'
    records = json.loads(path.read_text())
    record = next(row for row in records if str(row['ID']) == ('6' if kind == 'table' else '10'))
    result = run(kind, source=record, body_hash=sha,
        site='https://bioco.ch/' if environment == 'production' else 'https://staging.bioco.ch/')
    assert 'error' not in result, result
    assert result['roundtrip']
    assert hashlib.sha256(record['post_content'].encode()).hexdigest() == sha
