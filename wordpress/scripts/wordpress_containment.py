"""One reviewed document-containment projection; never a general drift override."""
import hashlib
import json
import os
from pathlib import Path
import shlex
import stat

OPERATION = '1e2a432153ee36dcad0423a078a5f02471a82c5d3d31420b9a931fe464d4a18c'
QUARANTINE = '/home/bioco/.private/member-document-containment-20261015-thread42'
PREFIX = 'mu-plugins/bioco-import/content-seed/documents/'
PROTECTED = frozenset({
    '1833504d7aa115dddc0a5ce13dd93ddf154bcc3356801de104d7fdd81021a651',
    '3e0848acd1c69aa0d78c593f1c3040b1f7e826d678b80b3f53757fe72b8f5627',
    'c033418188915baedbdfeb6852f3e67db60d43c378d00abf986d9847fc3b6b5d',
})


def sha(data):
    return hashlib.sha256(data).hexdigest()


# Executed read-only over the existing SSH seam. Tests execute these same bytes
# against real temporary files, independently of the canonical transport tests.
REMOTE_PROOF = r'''
import hashlib,json,os,stat,sys
from pathlib import Path
# BIOCO_CONTAINMENT_PROOF
request=json.loads(sys.argv[1]); root=Path(request['quarantine'])
def require(ok):
    if not ok: raise RuntimeError('Private containment proof failed')
def secure(path, mode, directory=False):
    require(path.resolve()==path and not path.is_symlink())
    info=path.stat()
    require(info.st_uid==os.getuid() and stat.S_IMODE(info.st_mode)==mode)
    require(stat.S_ISDIR(info.st_mode) if directory else stat.S_ISREG(info.st_mode))
def digest(path):
    value=hashlib.sha256()
    with path.open('rb') as stream:
        for data in iter(lambda:stream.read(1048576),b''):value.update(data)
    return value.hexdigest()
secure(root,0o700,True)
secure(root/'before.json',0o600); secure(root/'journal.jsonl',0o600)
items=json.loads((root/'before.json').read_text())
require(len(items)==17 and len({i['path'] for i in items})==17)
require(hashlib.sha256(json.dumps(items,sort_keys=True,separators=(',',':')).encode()).hexdigest()==request['operation'])
journal=[json.loads(line) for line in (root/'journal.jsonl').read_text().splitlines()]
require(len(journal)==34)
for index,item in enumerate(items):
    source=Path(item['path'])
    require(source.is_absolute() and source.resolve()==source and not source.exists() and not source.is_symlink())
    expected={'source':str(source),'backup':'backup-%02d.pdf'%index,'quarantine':'quarantine-%02d.pdf'%index,'sha256':item['sha256'],'original_mode':item['mode']}
    require(journal[index*2]==dict(expected,phase='intent'))
    require(journal[index*2+1]==dict(expected,phase='complete'))
    for name in (expected['backup'],expected['quarantine']):
        path=root/name; secure(path,0o600)
        require(path.stat().st_size==item['bytes'] and digest(path)==item['sha256'])
print(json.dumps({'operation':request['operation'],'source_paths_absent':17,'private_files_verified':34}))
'''


def protected_files(manifest):
    return {path: value for path, value in manifest.items() if value in PROTECTED}


class ContainmentPolicy:
    """Hide private proof and sanitized rollback behind one fixed policy."""

    def __init__(self, path, commit, baseline_sha, baseline, candidate, target):
        info = path.stat()
        if path.is_symlink() or not stat.S_ISREG(info.st_mode) or info.st_uid != os.getuid() or info.st_mode & 0o077:
            raise RuntimeError('Containment evidence must be a private regular file.')
        data = path.read_bytes()
        value = json.loads(data)
        removed = protected_files(baseline)
        expected = {'schema': 1, 'commit': commit, 'accepted_baseline': baseline_sha,
                    'host': target['HOST'], 'content': target['CONTENT'],
                    'operation_manifest_sha256': OPERATION, 'quarantine': QUARANTINE,
                    'owned_removed': removed}
        if value != expected or len(removed) != 3 or set(removed.values()) != PROTECTED \
                or any(not name.startswith(PREFIX) for name in removed) \
                or any(name in candidate for name in removed) or protected_files(candidate):
            raise RuntimeError('Containment evidence does not match the exact reviewed deletions.')
        self.evidence_sha256 = sha(data)
        self.removed = removed
        self.baseline_sha = baseline_sha

    def verify_remote(self, ssh, target):
        request = json.dumps({'operation': OPERATION, 'quarantine': QUARANTINE}, separators=(',', ':'))
        result = json.loads(ssh(target, 'python3 -c ' + shlex.quote(REMOTE_PROOF) + ' ' + shlex.quote(request)))
        if result != {'operation': OPERATION, 'source_paths_absent': 17, 'private_files_verified': 34}:
            raise RuntimeError('Private containment proof is incomplete.')

    def project(self, manifest):
        return {path: value for path, value in manifest.items() if path not in self.removed}

    def sanitize(self, directory):
        """Remove only pinned blobs from a PRIVATE rollback extraction."""
        for relative, expected in self.removed.items():
            source = Path(directory) / 'wordpress/content-seed' / relative.removeprefix('mu-plugins/bioco-import/content-seed/')
            if source.is_symlink() or sha(source.read_bytes()) != expected:
                raise RuntimeError('Rollback protected document differs from its pinned identity.')
            source.unlink()

    def receipt(self):
        return {'kind': 'contained-baseline-projection', 'source_commit': self.baseline_sha,
                'operation_manifest_sha256': OPERATION,
                'evidence_sha256': self.evidence_sha256, 'protected_file_count': 3}
