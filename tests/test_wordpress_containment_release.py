"""Execute canonical promotion and the private-file proof for protected PDFs."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys

import pytest
from test_wordpress_production_promotion import fixture, command, manifest

ROOT = Path(__file__).parents[1]
sys.path.insert(0, str(ROOT / 'wordpress/scripts'))
from wordpress_containment import OPERATION, PROTECTED, QUARANTINE, REMOTE_PROOF


def contained_fixture(tmp_path):
    script, env, _, _, review, events, marker = fixture(tmp_path)
    repo = script.parents[2]
    removed = {}
    names = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only',
        '594f31f9de0c96bcb3a6d4ae73356a2dd603bd21', '--', 'wordpress/content-seed/documents'], cwd=ROOT, text=True).splitlines()
    for name in names:
        data = subprocess.check_output(['git', 'show', '594f31f9de0c96bcb3a6d4ae73356a2dd603bd21:' + name], cwd=ROOT)
        sha = hashlib.sha256(data).hexdigest()
        if sha in PROTECTED:
            path = repo / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(data)
            removed[name.replace('wordpress/content-seed/', 'mu-plugins/bioco-import/content-seed/')] = sha
    deploy = script.parent / 'deploy-wp-code.sh'
    deploy.write_text(deploy.read_text().replace('echo deploy-wp-code.sh',
        'if [ "${BIOCO_RELEASE_ROLLBACK:-0}" = 1 ]; then\n'
        '  for file in "$(dirname "$0")"/../content-seed/documents/*.pdf; do\n'
        '    [ ! -f "$file" ] || { echo "rollback republished a PDF" >&2; exit 71; }\n'
        '  done\nfi\necho deploy-wp-code.sh'))
    command(['git', 'add', '.'], repo); command(['git', 'commit', '-qm', 'protected baseline'], repo)
    baseline = command(['git', 'rev-parse', 'HEAD'], repo)
    for path in (repo / 'wordpress/content-seed/documents').glob('*.pdf'): path.unlink()
    (repo / 'wordpress/web/app/mu-plugins/bioco-core/owned.txt').write_text('contained candidate\n')
    command(['git', 'add', '.'], repo); command(['git', 'commit', '-qm', 'safe candidate'], repo)
    candidate = command(['git', 'rev-parse', 'HEAD'], repo)
    command(['git', 'update-ref', 'refs/remotes/origin/wordpress', candidate], repo)
    baseline_map = manifest(repo, baseline)
    candidate_map = manifest(repo, 'candidate')
    for key, value in [('BIOCO_TEST_PROD_MANIFEST', baseline_map), ('BIOCO_TEST_BASELINE', baseline_map),
                       ('BIOCO_TEST_STAGE_MANIFEST', candidate_map), ('BIOCO_TEST_CANDIDATE', candidate_map)]:
        Path(env[key]).write_text(json.dumps(value))
    marker.write_text(json.dumps({'commit': baseline}))
    Path(env['BIOCO_TEST_STAGE_MARKER']).write_text(json.dumps({'commit': candidate, 'verify': 'passed', 'smoke': 'passed'}))
    env['BIOCO_TEST_CANDIDATE_SHA'] = candidate
    proof = tmp_path / 'proof.json'; proof.write_text(json.dumps({'operation': OPERATION, 'source_paths_absent': 17, 'private_files_verified': 34}))
    env['BIOCO_TEST_PROOF'] = str(proof)
    ssh = Path(env['BIOCO_PRODUCTION_SSH_BIN'])
    ssh.write_text(ssh.read_text().replace("elif 'wp eval ' in cmd:", "elif 'BIOCO_CONTAINMENT_PROOF' in cmd:print(Path(os.environ['BIOCO_TEST_PROOF']).read_text())\nelif 'wp eval ' in cmd:"))
    policy = tmp_path / 'containment.json'
    policy.write_text(json.dumps({'schema': 1, 'commit': candidate, 'accepted_baseline': baseline,
        'host': 'prod.test', 'content': '/srv/prod/wp-content', 'operation_manifest_sha256': OPERATION,
        'quarantine': QUARANTINE, 'owned_removed': removed}))
    policy.chmod(0o600)
    review.write_text(json.dumps({'commit': candidate, 'accepted_baseline': baseline,
        'coderabbit_agreed': True, 'blocking_findings': [], 'review_pr': 258,
        'review_url': 'https://github.com/gemuesegenossenschaft-bioco/bioco-web-project/pull/258#issuecomment-123',
        'containment_evidence_sha256': hashlib.sha256(policy.read_bytes()).hexdigest()}))
    return script, env, candidate, baseline, review, events, marker, policy, removed


def run(values, *, apply=False, rollback=False, policy=True):
    script, env, candidate, baseline, review, _, _, evidence, _ = values
    return subprocess.run([str(script), '--commit=' + candidate, '--expected-baseline=' + baseline,
        '--review-evidence=' + str(review), *(['--containment-evidence=' + str(evidence)] if policy else []),
        *(['--apply'] if apply else []), *(['--rollback'] if rollback else [])],
        env=env, text=True, capture_output=True)


def test_contained_forward_and_rollback_preserve_private_documents(tmp_path):
    values = contained_fixture(tmp_path)
    result = run(values)
    assert result.returncode == 0, result.stderr
    assert 'contained-baseline-projection' in result.stdout
    assert not values[5].exists() or 'wp db export' not in values[5].read_text()
    result = run(values, apply=True)
    assert result.returncode == 0, result.stderr + result.stdout
    assert json.loads(values[6].read_text())['commit'] == values[2]
    result = run(values, apply=True, rollback=True)
    assert result.returncode == 0, result.stderr + result.stdout
    receipt = json.loads(values[6].read_text())
    assert receipt['commit'] == values[3]
    assert receipt['baseline_projection']['source_commit'] == values[3]
    assert receipt['baseline_projection']['protected_file_count'] == 3
    assert 'wp db import' not in values[5].read_text()


@pytest.mark.parametrize('change', ['missing', 'receipt', 'proof', 'drift', 'reappeared', 'candidate', 'baseline'])
def test_containment_conflicts_stop_before_copy(tmp_path, change):
    values = contained_fixture(tmp_path)
    _, env, _, _, review, events, _, evidence, removed = values
    if change == 'receipt': evidence.write_text(evidence.read_text() + ' ')
    if change == 'proof': Path(env['BIOCO_TEST_PROOF']).write_text('{}')
    if change in ('drift', 'reappeared'):
        path = Path(env['BIOCO_TEST_PROD_MANIFEST']); value = json.loads(path.read_text())
        value.update({'themes/bioco/unexpected.php': 'b' * 64} if change == 'drift' else removed)
        path.write_text(json.dumps(value))
    if change == 'candidate':
        data = json.loads(evidence.read_text()); data['commit'] = '0' * 40; evidence.write_text(json.dumps(data))
    if change == 'baseline':
        data = json.loads(evidence.read_text()); data['accepted_baseline'] = '0' * 40; evidence.write_text(json.dumps(data))
    result = run(values, apply=True, rollback=True, policy=change != 'missing')
    assert result.returncode != 0
    assert not events.exists() or 'deploy-wp-code.sh' not in events.read_text()


@pytest.mark.parametrize('renamed', [False, True])
def test_candidate_cannot_republish_protected_content(tmp_path, renamed):
    values = list(contained_fixture(tmp_path))
    script, env, _, baseline, review, events, _, evidence, removed = values
    repo = script.parents[2]
    relative = next(iter(removed)).replace('mu-plugins/bioco-import/content-seed/', 'wordpress/content-seed/')
    data = subprocess.check_output(['git', 'show', baseline + ':' + relative], cwd=repo)
    destination = repo / ('wordpress/content-seed/documents/renamed.pdf' if renamed else relative)
    destination.write_bytes(data)
    command(['git', 'add', '.'], repo); command(['git', 'commit', '-qm', 'unsafe candidate'], repo)
    candidate = command(['git', 'rev-parse', 'HEAD'], repo)
    command(['git', 'update-ref', 'refs/remotes/origin/wordpress', candidate], repo)
    values[2] = candidate
    env['BIOCO_TEST_CANDIDATE_SHA'] = candidate
    policy = json.loads(evidence.read_text()); policy['commit'] = candidate; evidence.write_text(json.dumps(policy))
    reviewed = json.loads(review.read_text()); reviewed.update(commit=candidate,
        containment_evidence_sha256=hashlib.sha256(evidence.read_bytes()).hexdigest())
    review.write_text(json.dumps(reviewed))
    result = run(values, apply=True)
    assert result.returncode != 0
    assert 'protected' in result.stderr.lower()
    assert not events.exists() or 'deploy-wp-code.sh' not in events.read_text()


@pytest.mark.parametrize('fault', ['corrupt', 'missing', 'mode', 'source', 'symlink', 'journal'])
def test_remote_proof_executes_real_files_and_rejects_corruption(tmp_path, fault):
    root = tmp_path / 'private'; root.mkdir(mode=0o700)
    items = []; journal = []
    for index in range(17):
        data = ('private-' + str(index)).encode(); source = tmp_path / ('source-%02d.pdf' % index)
        item = {'path': str(source), 'sha256': hashlib.sha256(data).hexdigest(), 'bytes': len(data), 'mode': '644'}
        items.append(item)
        entry = {'source': str(source), 'backup': 'backup-%02d.pdf' % index, 'quarantine': 'quarantine-%02d.pdf' % index,
                 'sha256': item['sha256'], 'original_mode': item['mode']}
        journal += [dict(entry, phase='intent'), dict(entry, phase='complete')]
        for name in (entry['backup'], entry['quarantine']):
            path = root / name; path.write_bytes(data); path.chmod(0o600)
    for name, content in [('before.json', json.dumps(items)), ('journal.jsonl', ''.join(json.dumps(i) + '\n' for i in journal))]:
        path = root / name; path.write_text(content); path.chmod(0o600)
    operation = hashlib.sha256(json.dumps(items, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
    args = [sys.executable, '-c', REMOTE_PROOF, json.dumps({'quarantine': str(root), 'operation': operation})]
    result = subprocess.run(args, capture_output=True, text=True)
    assert result.returncode == 0, result.stderr
    assert json.loads(result.stdout)['private_files_verified'] == 34
    if fault == 'corrupt': (root / 'backup-04.pdf').write_bytes(b'changed')
    if fault == 'missing': (root / 'quarantine-04.pdf').unlink()
    if fault == 'mode': (root / 'backup-04.pdf').chmod(0o644)
    if fault == 'source': Path(items[4]['path']).write_bytes(b'reappeared')
    if fault == 'symlink':
        (root / 'backup-04.pdf').unlink(); (root / 'backup-04.pdf').symlink_to(root / 'quarantine-04.pdf')
    if fault == 'journal': (root / 'journal.jsonl').write_text('')
    assert subprocess.run(args, capture_output=True).returncode != 0


@pytest.mark.parametrize('fault', ['proof-at-lease', 'proof-final', 'manifest-at-lease', 'manifest-final'])
def test_changed_proof_or_manifest_never_publishes_success(tmp_path, fault):
    values = contained_fixture(tmp_path)
    _, env, _, baseline, _, events, marker, _, _ = values
    ssh = Path(env['BIOCO_PRODUCTION_SSH_BIN']); source = ssh.read_text()
    if fault.startswith('proof-'):
        env['BIOCO_TEST_PROOF_COUNT'] = str(tmp_path / 'proof-count')
        env['BIOCO_TEST_PROOF_FAIL'] = '2' if fault == 'proof-at-lease' else '3'
        source = source.replace("elif 'BIOCO_CONTAINMENT_PROOF' in cmd:print(Path(os.environ['BIOCO_TEST_PROOF']).read_text())", """elif 'BIOCO_CONTAINMENT_PROOF' in cmd:
 counter=Path(os.environ['BIOCO_TEST_PROOF_COUNT']); count=int(counter.read_text())+1 if counter.exists() else 1; counter.write_text(str(count))
 print('{}' if count==int(os.environ['BIOCO_TEST_PROOF_FAIL']) else Path(os.environ['BIOCO_TEST_PROOF']).read_text())""")
    else:
        mutate = "data=json.loads(Path(os.environ['BIOCO_TEST_PROD_MANIFEST']).read_text()); data['themes/bioco/unexpected.php']='b'*64; Path(os.environ['BIOCO_TEST_PROD_MANIFEST']).write_text(json.dumps(data))"
        if fault == 'manifest-at-lease':
            source = source.replace("if 'flock -n' in cmd:\n", "if 'flock -n' in cmd:\n " + mutate + '\n')
        else:
            source = source.replace("elif 'wp db export' in cmd or", "elif 'wp bioco verify --runtime' in cmd:\n " + mutate + "\nelif 'wp db export' in cmd or")
    ssh.write_text(source)
    result = run(values, apply=True)
    assert result.returncode != 0, result.stdout
    assert json.loads(marker.read_text()) == {'commit': baseline}
    if fault.endswith('lease'):
        assert 'deploy-wp-code.sh' not in events.read_text()
    else:
        assert 'deploy-wp-code.sh' in events.read_text()
        assert 'wp option update bioco_release_marker' not in events.read_text()
