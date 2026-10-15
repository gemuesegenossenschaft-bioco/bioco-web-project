"""Owned WordPress source identity, shared by packaging and promotion."""
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import subprocess


ROOT = Path(__file__).resolve().parents[2]
SHA = re.compile(r'^[0-9a-f]{40}$')
OWNED = {
    'wordpress/web/app/mu-plugins/bioco-core': 'mu-plugins/bioco-core',
    'wordpress/web/app/mu-plugins/bioco-content': 'mu-plugins/bioco-content',
    'wordpress/web/app/mu-plugins/bioco-forms': 'mu-plugins/bioco-forms',
    'wordpress/web/app/mu-plugins/bioco-import': 'mu-plugins/bioco-import',
    'wordpress/web/app/themes/bioco': 'themes/bioco',
    'wordpress/web/app/themes/bioco-divi': 'themes/bioco-divi',
    'wordpress/deploy/bioco-mu-loader.php': 'mu-plugins/bioco-mu-loader.php',
    'wordpress/content-seed': 'mu-plugins/bioco-import/content-seed',
}


def git(*args, binary=False):
    result = subprocess.run(['git', *args], cwd=ROOT, capture_output=True, check=True)
    return result.stdout if binary else result.stdout.decode().strip()


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def digest(data):
    return sha256(json.dumps(data, sort_keys=True, separators=(',', ':')).encode())


def owned_files(commit):
    """Read Git objects, never an ignored or locally substituted release input."""
    if not SHA.fullmatch(commit):
        raise ValueError('Commit must be a full lowercase Git SHA.')
    files = {}
    for row in git('ls-tree', '-rz', commit, '--', *OWNED, binary=True).split(b'\0'):
        if not row:
            continue
        metadata, name = row.split(b'\t', 1)
        mode, kind, oid = metadata.decode().split()
        path = name.decode()
        leaf = PurePosixPath(path).name
        if (path.startswith('/') or '..' in PurePosixPath(path).parts or '\\' in path
                or any(ord(char) < 32 for char in path)
                or leaf.startswith('.env') or leaf == 'wp-config.php'
                or leaf.endswith(('.key', '.pem', '.sql', '.log'))):
            raise ValueError('Unsafe owned source path: ' + path)
        if mode not in ('100644', '100755') or kind != 'blob':
            raise ValueError('Unsupported owned source: ' + path)
        files[path] = {'mode': int(mode[-3:], 8), 'data': git('cat-file', 'blob', oid, binary=True)}
    for source in OWNED:
        if not any(path == source or path.startswith(source + '/') for path in files):
            raise ValueError('Missing owned source: ' + source)
    return dict(sorted(files.items()))


def file_manifest(files):
    return {name: {'sha256': sha256(item['data']), 'mode': item['mode']}
            for name, item in files.items()}
