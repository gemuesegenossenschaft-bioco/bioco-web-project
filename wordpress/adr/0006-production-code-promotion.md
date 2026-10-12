# Production WordPress code promotion

The public site already serves WordPress. This route promotes owned code after a reviewed staging release. It never repeats traffic cutover, rewrites Apache routing, imports seeds, sends forms, or restores a database.

## Designs considered

1. Add a URL argument to `release-wordpress-staging.sh`. This reuses every step but lets a staging command point at production. A mistaken or hostile URL would cross the environment boundary.
2. Copy the staging script into a production script. Fixed identities are clear, but the two preflight, sync, cache, and smoke implementations would drift.
3. Use a fixed production entry point and reuse the existing preflight, owned-code sync, web-cache reset, and route smoke commands. The wrapper adds promotion evidence, separate credentials, byte-level manifests, and a remote lease. This is the chosen design. The staging entry point remains unchanged.

## Operator contract

`release-wordpress-production.py` accepts full `--commit` and `--expected-baseline` SHAs, `--review-evidence=/private/path.json`, and optional `--apply`. It checks out the candidate at `HEAD` and the known `origin/wordpress` tip. Its production URL is fixed to `https://bioco.ch`; staging is fixed to `https://staging.bioco.ch`. The production and staging SSH settings are separate (`BIOCO_PROD_{HOST,USER,CONTENT,SSH_PORT}` and `BIOCO_STAGE_{HOST,USER,CONTENT,SSH_PORT}`). Apply additionally requires `BIOCO_PROD_BACKUP_DIR` outside the WordPress directory.

The private evidence JSON binds `commit`, `accepted_baseline`, merged `review_pr`, a specific `review_url` for a CodeRabbit comment or review, `coderabbit_agreed: true`, and `blocking_findings: []`. The command verifies the merged PR SHA, three successful required PR jobs and the linked CodeRabbit author through GitHub. The `coderabbit_agreed` and empty-blocker statements are the release owner's review of that linked text; the bot's generic success status alone is insufficient. The command reruns the current checkout's release preflight. An initial unmarked production installation requires `--bootstrap-baseline` plus `bootstrap_baseline: true` in the evidence. Its code must still exactly match the expected baseline.

Dry run reads both WordPress identities and markers, compares staging code with the candidate and production code with the accepted baseline, and prints the changed owned paths and manifest digests. It does not write remotely. Apply holds a server `flock` lease across the final baseline check, editorial fingerprint, private DB backup, owned-code sync, web-cache reset, runtime verification, 22-route smoke/editor-asset gate, final fingerprint and code checks, and release marker write. The marker records SHA, baseline, staging SHA, manifest, backup path, review evidence digest and verification result. Failure leaves no successful new marker. The operator must inspect the failed step and use the explicit rollback path if code was partly synced.

`--rollback` previews restoration of the accepted baseline's owned code. With `--apply`, it holds the same lease, accepts only files from the candidate/baseline manifests, extracts the baseline Git tree to a temporary local directory, syncs only owned code, resets cache, verifies runtime/routes and unchanged editorial fingerprint, then writes a rollback marker. It does not import a database. No production routing or seed import command runs in either direction.

The workflow dispatch on `wordpress` uses the `production` environment and separate SSH secrets. Its inputs include the accepted baseline and review evidence, with an explicit `apply` switch. The server lease coordinates that workflow with local invocations of this entry point. Other direct SSH writers must still honor the separate shared-writer policy in #227.

## Remaining acceptance work

Fake SSH tests establish command order, conflict refusal, lock lifetime and code-only rollback. They cannot prove the current host paths, licensed Divi behavior, live redirects or mail safety. The release owner must first verify the dry run against the actual accepted production marker/code and exact staging receipt, inspect the CodeRabbit link and current test result, and test rollback on an isolated WordPress setup. #228 remains open until production and rollback receipts, editorial comparisons, canonical redirects, editor assets, and security probes are recorded for the merged candidate. The root release hold and single-writer coordination still apply.
