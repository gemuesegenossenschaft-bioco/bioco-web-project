"""External SSH/rsync/WP-CLI boundary for staging operator tests. No live service."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import shlex
import shutil
import signal
import subprocess
import sys
import time


host = Path(os.environ['BIOCO_TEST_HOST'])
mode, *args = sys.argv[1:]


def event(value):
    with (host / 'events').open('a') as stream:
        stream.write(value + '\n')


if mode == 'ssh':
    command = args[-1]
    # Linux MAX_ARG_STRLEN includes the terminating NUL. Model the deployment
    # host's per-argument bound even when tests run on another operating system.
    if len(command.encode()) >= 128 * 1024:
        raise SystemExit('SSH command exceeds Linux argument limit')
    if command.startswith('flock -n /tmp/bioco-wordpress-staging.lock '):
        lock = (host / 'lock').open('a')
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise SystemExit(1)
        os.set_inheritable(lock.fileno(), True)
        (host / 'holder').write_text(str(os.getpid()))
        os.environ['BIOCO_TEST_LEASE_OWNER'] = str(os.getpid())
        if os.environ.get('TEST_CHANGE_BASELINE'):
            (host / 'marker.json').write_text(json.dumps({'commit': 'b' * 40}))
        if os.environ.get('TEST_MOVE_INTEGRATION'):
            repo = str(host.parent / 'repo')
            baseline = subprocess.check_output(['git', '-C', repo, 'rev-parse', 'HEAD^'], text=True).strip()
            subprocess.run(['git', '-C', repo, 'update-ref', 'refs/heads/wordpress', baseline], check=True)
        os.execvp('sh', shlex.split(command)[3:])
    if ' -type f -exec sha256sum {} +' in command:
        # Execute the Linux hash inventory with actual local fake-host bytes.
        tokens = shlex.split(command.split('; ', 1)[1])
        root = Path(tokens[1].removesuffix(';'))
        sources = tokens[tokens.index('find') + 1:tokens.index('-type')]
        for source in sources:
            path = root / source
            for file in sorted([path] if path.is_file() else path.rglob('*')):
                if file.is_file():
                    print(hashlib.sha256(file.read_bytes()).hexdigest() + '  ' + file.relative_to(root).as_posix())
        raise SystemExit(0)
    raise SystemExit(subprocess.call(['bash', '-c', command]))

if mode == 'rsync':
    source, target = args[-2:]
    target = Path(target.split(':', 1)[1])
    if '--dry-run' in args:
        raise SystemExit(0)
    event('sync')
    source = Path(source)
    if source.is_dir():
        if target.exists():
            shutil.rmtree(target)
        shutil.copytree(source, target)
    else:
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
    if os.environ.get('TEST_PARTIAL_SYNC'):
        raise SystemExit(1)
    if os.environ.get('TEST_LOSE_LEASE'):
        os.kill(int((host / 'holder').read_text()), signal.SIGKILL)
        time.sleep(0.2)
    raise SystemExit(0)

if mode == 'wp':
    args = [value for value in args if value not in ('--skip-plugins', '--skip-themes')]
    if args[:3] in (['option', 'get', 'siteurl'], ['option', 'get', 'home']):
        print(os.environ.get('TEST_SITE_URL', 'https://staging.bioco.ch'))
    elif args[:3] == ['option', 'get', 'bioco_release_marker']:
        print((host / 'marker.json').read_text())
    elif args[:3] == ['option', 'update', 'bioco_release_marker']:
        # Publication must run inside the dedicated holder session.
        assert os.getppid() != 1
        assert os.environ.get('BIOCO_TEST_LEASE_OWNER') == (host / 'holder').read_text()
        os.kill(int((host / 'holder').read_text()), 0)
        if os.environ.get('TEST_FAIL_MARKER'):
            raise SystemExit(1)
        (host / 'marker.json').write_text(args[3])
        event('marker-under-lease')
        print("Success: Updated 'bioco_release_marker' option.")
    elif args[:2] == ['core', 'version']:
        print('6.8.3')
    elif args[:3] == ['theme', 'get', 'Divi']:
        print('5.0.0')
    elif args[:2] == ['plugin', 'list']:
        print(json.dumps([{'name': 'seo', 'status': 'active', 'version': '1.0'}]))
    elif args[:1] == ['eval']:
        if os.environ.get('TEST_FINGERPRINT_STDERR'):
            print('Unsuccessful stat on filename containing newline at ea_php_cli.pm line 87.', file=sys.stderr)
        print(hashlib.sha256((host / 'editorial').read_bytes()).hexdigest())
    elif args[:2] == ['db', 'export']:
        if os.environ.get('TEST_FAIL_BACKUP'):
            raise SystemExit(1)
        Path(args[2]).write_text('private database fixture\n')
        event('backup')
        if os.environ.get('TEST_BLOCK_BACKUP'):
            (host / 'backup-started').touch()
            deadline = time.monotonic() + 15
            while not (host / 'continue').exists() and time.monotonic() < deadline:
                time.sleep(0.02)
    elif args[:3] == ['bioco', 'verify', '--runtime']:
        event('runtime')
        if os.environ.get('TEST_BLOCK_RUNTIME'):
            (host / 'runtime-started').touch()
            deadline = time.monotonic() + 10
            while not (host / 'continue').exists() and time.monotonic() < deadline:
                time.sleep(0.02)
        if os.environ.get('TEST_EDIT_DURING_RUNTIME'):
            (host / 'editorial').write_text('later editor revision')
        if os.environ.get('TEST_TAMPER_AFTER_RUNTIME'):
            (host / 'site/wp-content/mu-plugins/bioco-core/owned.php').write_text('<?php /* unexpected */')
        if os.environ.get('TEST_FAIL_RUNTIME'):
            raise SystemExit(1)
    elif args[:2] == ['bioco', 'editor-routing-guard']:
        event('routing')
    elif args[:2] == ['cache', 'flush']:
        event('cache')
    elif args[:3] == ['bioco', 'import', '--help']:
        pass
    else:
        raise SystemExit('Unexpected WP command: ' + repr(args))
