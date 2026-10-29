"""Keep the staging server lease alive and publish through its locked session."""
from contextlib import contextmanager
import os
import selectors
import shlex
import signal
import subprocess
import sys
import tempfile


class ServerLease:
    def __init__(self, ssh):
        # The shell and its children inherit flock's descriptor. A marker
        # command therefore cannot outlive the lock protecting that command.
        protocol = ("printf 'READY\\n'; "
                    'while IFS= read -r command; do '
                    'sh -c "$command" </dev/null >&2; result=$?; '
                    "printf 'DONE %s\\n' \"$result\"; done")
        command = 'flock -n /tmp/bioco-wordpress-staging.lock sh -c ' + shlex.quote(protocol)
        self.errors = tempfile.TemporaryFile(mode='w+')
        self.process = subprocess.Popen(ssh + [command], stdin=subprocess.PIPE,
                                        stdout=subprocess.PIPE, stderr=self.errors,
                                        text=True, start_new_session=True)
        self.selector = selectors.DefaultSelector()
        self.selector.register(self.process.stdout, selectors.EVENT_READ)

    def read(self):
        if not self.selector.select(timeout=15):
            raise RuntimeError('Staging server lease did not respond.')
        return self.process.stdout.readline().strip()

    def require(self):
        if self.process.poll() is not None:
            raise RuntimeError('Staging server lease was lost.')

    def publish(self, command):
        self.require()
        if '\n' in command or '\r' in command:
            raise ValueError('Lease command must be one line.')
        self.process.stdin.write(command + '\n')
        self.process.stdin.flush()
        if self.read() != 'DONE 0':
            raise RuntimeError('Marker publication under staging lease failed.')

    def close(self):
        try:
            self.process.stdin.close()
        except BrokenPipeError:
            pass
        try:
            self.process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            os.killpg(self.process.pid, signal.SIGKILL)
            self.process.wait()
        self.selector.close()
        self.process.stdout.close()
        self.errors.close()


@contextmanager
def staging_lease(ssh):
    lease = ServerLease(ssh)
    try:
        if lease.read() != 'READY':
            raise RuntimeError('Could not acquire staging server lease; another release may own it.')
        lease.require()
        yield lease
    finally:
        lease.close()


def run_guarded(argv, *, env, cwd, lease=None, input_text=None):
    """Cancel the whole local transport tree on lost lease; never accept it."""
    if lease:
        lease.require()
    with (tempfile.TemporaryFile(mode='w+') as output,
          tempfile.TemporaryFile(mode='w+') as errors,
          tempfile.TemporaryFile(mode='w+') as source):
        # A file supplies EOF without blocking the lease monitor on a full pipe.
        if input_text is not None:
            source.write(input_text)
            source.seek(0)
        process = subprocess.Popen(argv, cwd=cwd, env=env, stdout=output,
                                    stdin=source if input_text is not None else None,
                                    stderr=errors, text=True, start_new_session=True,
                                    # Keep the holder's input open until an
                                    # in-flight transport exits even if this
                                    # coordinator is killed without cleanup.
                                    pass_fds=(lease.process.stdin.fileno(),) if lease else ())
        try:
            while True:
                if lease:
                    lease.require()
                try:
                    status = process.wait(timeout=0.1)
                    break
                except subprocess.TimeoutExpired:
                    continue
            if lease:
                lease.require()
            output.seek(0)
            result = output.read()
            if status:
                print(result, end='', flush=True)
                raise RuntimeError('Release command failed: ' + str(argv[0]))
            return result
        finally:
            if process.poll() is None:
                os.killpg(process.pid, signal.SIGTERM)
                try:
                    process.wait(timeout=3)
                except subprocess.TimeoutExpired:
                    os.killpg(process.pid, signal.SIGKILL)
                    process.wait()
            # Diagnostics belong in the release log, never in structured stdout
            # such as JSON inventories or the editorial fingerprint digest.
            errors.seek(0)
            print(errors.read(), end='', file=sys.stderr, flush=True)
