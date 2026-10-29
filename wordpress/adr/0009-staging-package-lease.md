# Stage verified packages under one server lease

For #281 and #227, keep `release-wordpress-staging.sh` as the operator entry point. A Python coordinator consumes the #280 package and owns the fixed staging identity, expected baseline, server lease and final receipt. The existing shell sequence becomes an internal worker so backups, owned-directory rsync, editor routing, web OPcache and runtime gates keep their existing tests.

Three options were considered against the current SSH/rsync host:

| Option | Caller effort | Host requirements and trade-off |
| --- | --- | --- |
| Add independent guards to the existing shell commands | Small initial change | Baseline, package and failure state would be reconstructed by each caller. Holding a live SSH lease across subprocesses is awkward in the host's shell workflow. |
| One coordinator around the tested worker | One command with candidate and accepted baseline | Uses existing SSH, flock, WP-CLI and rsync. The coordinator owns ordering and receipts; the worker installs extracted, verified package bytes. Chosen. |
| Versioned directories with atomic activation | New package installation and activation contract | Requires proof of host symlinks, WordPress loading and web OPcache. Rsync support alone does not establish atomic activation. Deferred. |

Apply holds `/tmp/bioco-wordpress-staging.lock` through baseline recheck, installation, cache reset, runtime/route/security checks and marker publication. The holder runs in one SSH session. Guarded subprocesses inherit a writer descriptor for that session, keeping the lock alive if the coordinator is killed while a worker is still running. A detected lost holder cancels the local transport tree. Marker publication executes through the holder itself. Contending calls fail instead of waiting with stale assumptions.

Both the previous marker and actual owned-file hashes must match the accepted baseline. Recheck after acquiring the lease. Require that baseline to be an ancestor of the candidate and that the candidate matches the checkout's known `origin/wordpress`. Repeating the exact successful request verifies the installed candidate and runtime without reinstalling or rewriting its marker. An unexplained or partial installation stops; recovery remains #284.

CI waits for the successful package workflow for its exact push SHA, then passes its run ID to the canonical entry point. `workflow_run` is unsuitable here because WordPress integrates on `wordpress` while the repository default is `main`. `STAGING_ACCEPTED_BASELINE` is a coordinated environment variable, never inferred from whatever marker happens to be installed. Manual dispatch supplies that full SHA and a package run ID. Local callers may omit the run ID to run full #280 validation and build a new package in a private temporary directory. Caller-authored passing receipts are not accepted as reusable test evidence.

Private local receipts contain actual installed hashes, package and validation identities, the previous marker, WordPress/Divi/plugin compatibility, gate results and failure state. The successful marker is written only after final code and editorial checks. A partial sync leaves the previous marker intact. The existing editorial fingerprint remains conservative and retains its known coverage limits from #282; ordinary concurrent editorial changes can still prevent acceptance. No database restoration or automatic rollback is added.

Executable fake-host tests establish ordering, collision, holder death, stale state, package reuse and failure behavior. They do not establish real WordPress/Divi editability or host acceptance. Shared staging still requires a coordinated owner, exact merged SHA, accepted baseline, current checks and CodeRabbit agreement. Production promotion remains a separate explicitly approved operation.
