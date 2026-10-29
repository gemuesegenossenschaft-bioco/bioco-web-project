#!/usr/bin/env python3
"""Stage one verified WordPress package against an explicit accepted baseline."""
import argparse
from contextlib import redirect_stdout, redirect_stderr
from datetime import datetime, timezone
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys
import tarfile
import tempfile
import uuid

from wordpress_release import ROOT, OWNED, SHA, digest, git, owned_files
from wordpress_staging_lease import run_guarded, staging_lease


SITE = 'https://staging.bioco.ch'


def package_module():
    spec = importlib.util.spec_from_file_location('release_package', ROOT / 'wordpress/scripts/release-wordpress-package.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def installed_paths(files):
    result = {}
    for path, entry in files.items():
        for source, target in OWNED.items():
            if path == source or path.startswith(source + '/'):
                result[target + path[len(source):]] = entry['sha256']
                break
    return dict(sorted(result.items()))


class Staging:
    def __init__(self, args):
        self.args = args
        host, user = (os.environ.get('BIOCO_WP_' + key, '') for key in ('HOST', 'USER'))
        self.content = os.environ.get('BIOCO_WP_CONTENT', '')
        port = os.environ.get('BIOCO_WP_SSH_PORT', '22')
        if (not all(re.fullmatch(r'[A-Za-z0-9._-]+', value) and not value.startswith('-') for value in (host, user))
                or not re.fullmatch(r'/[A-Za-z0-9._/-]+/wp-content', self.content)
                or '..' in Path(self.content).parts
                or not re.fullmatch(r'[0-9]{1,5}', port) or not 1 <= int(port) <= 65535):
            raise ValueError('Unsafe release target or SSH port.')
        self.root = self.content.removesuffix('/wp-content')
        self.ssh = [os.environ.get('BIOCO_RELEASE_SSH_BIN', 'ssh'), '-p', port,
                    '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10', user + '@' + host]
        resolve = os.environ.get('BIOCO_RELEASE_RESOLVE', '')
        self.env = os.environ | {'BIOCO_RELEASE_REPO_ROOT': str(ROOT), 'BIOCO_RELEASE_URL': SITE,
                                 'BIOCO_RELEASE_RENDER_URL': SITE, 'BIOCO_STAGING_URL': SITE,
                                 'BIOCO_RELEASE_RENDER_RESOLVE': resolve, 'BIOCO_STAGING_RESOLVE': resolve}
        self.lease = None
        self.receipt_path = None
        self.evidence = {'commit': args.commit, 'previous': args.expected_baseline,
                         'site': SITE, 'status': 'failed', 'mode': 'apply' if args.apply else 'dry-run'}

    def remote(self, command, *, input_text=None):
        return run_guarded(self.ssh + ['set -eu; ' + command], env=self.env, cwd=ROOT,
                           lease=self.lease, input_text=input_text).strip()

    def wp(self, command):
        return self.remote('cd ' + shlex.quote(self.root) + '; wp ' + command)

    def identity(self):
        values = self.wp('option get siteurl; wp option get home').splitlines()
        if len(values) != 2 or any(value.rstrip('/') != SITE for value in values):
            raise ValueError('Remote WordPress does not match the fixed staging identity.')

    def integration_tip(self):
        tip = run_guarded(['git', 'ls-remote', '--exit-code', 'origin', 'refs/heads/wordpress'],
                          env=self.env, cwd=ROOT, lease=self.lease).strip()
        if tip != self.args.commit + '\trefs/heads/wordpress':
            raise ValueError('Candidate is not the current remote integration tip; fetch and coordinate again.')
        self.evidence.setdefault('gates', {})['integration-tip'] = 'passed'

    def marker(self):
        value = json.loads(self.wp('option get bioco_release_marker --format=json'))
        if (not isinstance(value, dict) or not isinstance(value.get('commit'), str)
                or not SHA.fullmatch(value['commit'])):
            raise ValueError('Staging requires a valid accepted release marker.')
        return value

    def manifest(self):
        sources = [path for path in OWNED.values() if path != 'mu-plugins/bioco-import/content-seed']
        prefix = 'cd ' + shlex.quote(self.content) + '; find ' + ' '.join(shlex.quote(path) for path in sources)
        if self.remote(prefix + ' -type l -print'):
            raise ValueError('Symlink in installed owned code.')
        found = {}
        for line in self.remote(prefix + ' -type f -exec sha256sum {} +').splitlines():
            match = re.fullmatch(r'([0-9a-f]{64})  (.+)', line)
            if not match or match[2] in found:
                raise ValueError('Invalid installed code manifest.')
            found[match[2]] = match[1]
        return dict(sorted(found.items()))

    def compatibility(self):
        plugins = json.loads(self.wp('plugin list --format=json'))
        if not isinstance(plugins, list):
            raise ValueError('Invalid plugin compatibility inventory.')
        return {'wordpress': self.wp('core version'), 'divi': self.wp('theme get Divi --field=version'),
                'plugins': sorted(({'name': p['name'], 'version': p['version'], 'status': p['status']}
                    for p in plugins if p['status'] not in ('must-use', 'dropin')), key=lambda p: p['name'])}

    def fingerprint(self):
        code = (ROOT / 'wordpress/scripts/editorial-fingerprint.php').read_text().removeprefix('<?php')
        value = self.wp('eval ' + shlex.quote(code))
        if not re.fullmatch(r'[0-9a-f]{64}', value):
            raise ValueError('Invalid editorial fingerprint.')
        return value

    def step(self, name, argv):
        self.evidence['step'] = name
        print('step=' + name + ' status=running', flush=True)
        print(run_guarded(argv, env=self.env, cwd=ROOT, lease=self.lease), end='', flush=True)
        self.evidence.setdefault('gates', {})[name] = 'passed'

    def persist_receipt(self):
        """Each attempt owns one private path, independent of the success marker."""
        if self.receipt_path is None:
            return
        path = self.receipt_path
        temporary = path + '.tmp-' + uuid.uuid4().hex
        self.remote('umask 077; mkdir -p ' + shlex.quote(str(Path(path).parent))
                    + '; cat > ' + shlex.quote(temporary)
                    + '; mv ' + shlex.quote(temporary) + ' ' + shlex.quote(path),
                    input_text=json.dumps(self.evidence, separators=(',', ':')) + '\n')

    def execute(self, temporary):
        package = package_module()
        package.clean_candidate(self.args.commit)
        if git('rev-parse', 'refs/remotes/origin/wordpress') != self.args.commit:
            raise ValueError('Candidate is not the accepted origin/wordpress tip; fetch and coordinate first.')
        git('merge-base', '--is-ancestor', self.args.expected_baseline, self.args.commit)
        self.integration_tip()
        self.identity()
        previous = self.marker()
        self.evidence['previous_marker'] = previous
        baseline = installed_paths(package.file_manifest(owned_files(self.args.expected_baseline)))
        candidate = installed_paths(package.file_manifest(owned_files(self.args.commit)))
        self.evidence['candidate_manifest'] = candidate
        observed = self.manifest()
        repeat = previous.get('commit') == self.args.commit
        if (previous.get('commit') != self.args.expected_baseline and not repeat
                or observed != (candidate if repeat else baseline)):
            raise ValueError('Staging marker or installed bytes differ from the accepted baseline.')
        directory = temporary / 'package'
        self.evidence['step'] = 'package'
        if self.args.package_run_id is not None:
            package.verify_ci(self.args.commit, self.args.package_run_id, directory)
            trust = 'trusted-ci'
        else:
            package.build(self.args.commit, directory)
            trust = 'local-full-validation'
        receipt = package.verify(self.args.commit, directory)
        package.clean_candidate(self.args.commit)
        self.evidence.update(package_sha256=receipt['package_sha256'],
                             validation_identity=receipt['validation_identity'], validation=trust)
        if repeat and (previous.get('package_sha256') != receipt['package_sha256']
                       or previous.get('previous') != self.args.expected_baseline
                       or previous.get('verify') != 'passed' or previous.get('smoke') != 'passed'):
            raise ValueError('Repeat invocation does not match a verified staging receipt.')
        self.evidence['compatibility'] = self.compatibility()
        print(json.dumps({'mode': self.evidence['mode'], 'site': SITE, 'commit': self.args.commit,
                          'expected_baseline': self.args.expected_baseline,
                          'package_sha256': receipt['package_sha256'], 'validation': trust,
                          'owned_changed': sorted(set(baseline) ^ set(candidate) |
                              {path for path in baseline.keys() & candidate.keys() if baseline[path] != candidate[path]})},
                         sort_keys=True), flush=True)
        if not self.args.apply:
            self.evidence.update(status='dry-run', installed_manifest=observed)
            print('release-dry-run-complete remote-mutations=0')
            return
        backup = os.environ.get('BIOCO_RELEASE_BACKUP_DIR', str(Path(self.root).parent / 'backups/wordpress-staging'))
        if (not re.fullmatch(r'/[A-Za-z0-9._/-]+', backup) or '..' in Path(backup).parts
                or Path(backup).is_relative_to(Path(self.root))):
            raise ValueError('Backup path must be private and outside WordPress.')
        self.env['BIOCO_RELEASE_BACKUP_DIR'] = backup
        extracted = temporary / 'source'
        extracted.mkdir()
        # verify() checked every member against Git. The private directory is
        # not caller supplied and is never shared between concurrent releases.
        with tarfile.open(directory / 'package.tar.gz') as archive:
            archive.extractall(extracted, filter='data')
        deploy = extracted / 'wordpress/scripts/deploy-wp-code.sh'
        deploy.parent.mkdir(parents=True)
        deploy.write_bytes(git('show', self.args.commit + ':wordpress/scripts/deploy-wp-code.sh', binary=True))
        deploy.chmod(0o700)
        self.env.update(BIOCO_RELEASE_DEPLOY_SCRIPT=str(deploy), BIOCO_RELEASE_PREFLIGHT_COMMAND='/usr/bin/true',
                        BIOCO_RELEASE_TIMESTAMP=self.evidence['timestamp'],
                        BIOCO_RELEASE_LOG_DIR=str(temporary / 'worker-logs'))
        self.evidence['step'] = 'lease'
        with staging_lease(self.ssh) as lease:
            self.lease = lease
            self.integration_tip()
            self.identity()
            if self.marker() != previous or self.manifest() != observed:
                raise ValueError('Staging baseline changed while acquiring the server lease.')
            if self.compatibility() != self.evidence['compatibility']:
                raise ValueError('Staging compatibility changed while acquiring the server lease.')
            self.receipt_path = backup.rstrip('/') + '/receipts/' + self.evidence['attempt_id'] + '.json'
            self.evidence.update(remote_receipt=self.receipt_path, status='running', installed_manifest=observed)
            self.persist_receipt()
            before = self.fingerprint()
            if repeat:
                self.wp('bioco verify --runtime')
                self.step('smoke', [os.environ.get('BIOCO_RELEASE_RENDER_GATE', str(ROOT / 'tests/wordpress-staging-render-gate.sh'))])
            else:
                self.step('code-and-runtime', ['bash', str(ROOT / 'wordpress/scripts/stage-wordpress-code.sh'),
                                              '--commit=' + self.args.commit, '--apply'])
            self.evidence['step'] = 'final-verification'
            installed = self.manifest()
            self.evidence['installed_manifest'] = installed
            if installed != candidate:
                raise ValueError('Installed code differs from the verified package.')
            if self.fingerprint() != before:
                raise ValueError('Editorial fingerprint changed during staging release; acceptance refused.')
            if self.compatibility() != self.evidence['compatibility']:
                raise ValueError('WordPress, Divi or plugin compatibility changed during staging release.')
            self.evidence.update(editorial_fingerprint=before, manifest=digest(installed),
                                 verify='passed', smoke='passed')
            if not repeat:
                marker = {key: self.evidence[key] for key in ('commit', 'previous', 'timestamp', 'site',
                          'package_sha256', 'validation_identity', 'validation', 'compatibility',
                          'editorial_fingerprint', 'manifest', 'verify', 'smoke')}
                marker['backup'] = backup.rstrip('/') + '/' + self.evidence['timestamp'] + '-' + self.args.commit + '.sql'
                marker['receipt'] = self.receipt_path
                self.evidence['step'] = 'release-marker'
                command = 'set -eu; cd ' + shlex.quote(self.root) + '; wp option update bioco_release_marker ' \
                    + shlex.quote(json.dumps(marker, separators=(',', ':'))) + ' --format=json >/dev/null'
                lease.publish(command)
                if self.marker() != marker:
                    raise ValueError('Published staging marker was not retained.')
            self.evidence['status'] = 'success'
            self.persist_receipt()
            print(('release-already-staged' if repeat else 'release-complete') + ' commit=' + self.args.commit)


class Tee:
    def __init__(self, stream, log):
        self.stream, self.log = stream, log

    def write(self, text):
        self.stream.write(text)
        self.log.write(text)
        self.flush()

    def flush(self):
        self.stream.flush()
        self.log.flush()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--expected-baseline', required=True)
    parser.add_argument('--package-run-id', type=int)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    if os.environ.get('BIOCO_RELEASE_URL', SITE).rstrip('/') != SITE:
        raise ValueError('Release requires the fixed staging identity ' + SITE)
    if not SHA.fullmatch(args.commit) or not SHA.fullmatch(args.expected_baseline):
        raise ValueError('Candidate and expected baseline must be full lowercase Git SHAs.')
    stage = Staging(args)
    stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    stage.evidence['timestamp'] = stamp
    log_dir = Path(os.environ.get('BIOCO_RELEASE_LOG_DIR', str(ROOT / 'output/releases')))
    log_dir.mkdir(parents=True, exist_ok=True)
    log_dir.chmod(0o700)
    name = stamp + '-' + args.commit + '-' + uuid.uuid4().hex[:8]
    stage.evidence['attempt_id'] = name
    log_path = log_dir / (name + '.log')
    with log_path.open('x') as log:
        log_path.chmod(0o600)
        with redirect_stdout(Tee(sys.stdout, log)), redirect_stderr(Tee(sys.stderr, log)):
            try:
                with tempfile.TemporaryDirectory(prefix='bioco-stage-') as temporary:
                    stage.execute(Path(temporary))
                print('release-status=' + stage.evidence['status'])
            except Exception as error:
                stage.evidence['status'] = 'failed'
                stage.evidence['error'] = str(error)
                # Observe partial installation without trying to repair or
                # replacing the last successful marker. Recovery is #284.
                stage.lease = None
                try:
                    stage.evidence['installed_manifest'] = stage.manifest()
                except Exception as probe_error:
                    stage.evidence['installed_manifest_error'] = str(probe_error)
                try:
                    stage.persist_receipt()
                except Exception as receipt_error:
                    stage.evidence['remote_receipt_error'] = str(receipt_error)
                print('release-status=failed step=' + stage.evidence.get('step', 'initialization'))
                raise
            finally:
                path = log_dir / (name + '.json')
                with path.open('x') as receipt:
                    path.chmod(0o600)
                    json.dump(stage.evidence, receipt, indent=2)
                    receipt.write('\n')
                print('release-receipt=' + str(path))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, RuntimeError, OSError, subprocess.CalledProcessError) as error:
        print('release-status=failed error=' + str(error), file=sys.stderr)
        raise SystemExit(1)
