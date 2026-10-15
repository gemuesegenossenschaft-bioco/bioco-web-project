# Verified WordPress release packages

Scope: #280, part of #226 and delivery plan #278. This decision adds package creation and evidence verification. Staging consumption is #281; concurrent editing is blocked by #282. Existing release commands retain their full preflight and editorial guards.

## Choice

Three designs were compared:

1. Cache test results beside the checkout. This is small, but a caller can change the code or author a passing receipt. It provides no trustworthy link to the bytes installed.
2. Package the exact Git objects and publish the package from a successful integration workflow. A release can independently compare the artifact, receipt and source before using the same bytes. This fits the current SSH/rsync host and is the chosen first slice.
3. Install version directories and atomically switch the active release. This may avoid partial file copies, but requires proof about the host's symlinks, WordPress loading and web OPcache. Packaging does not imply that activation is atomic. That decision remains with #281.

The package module owns the source inventory and content identity. Its operator interface is `release-wordpress-package.py build`, `verify`, and `verify-ci`. Tests drive that interface with a small Git repository and a fake GitHub transport. The remote transport is the replaceable seam; hash computation, archive parsing and validation decisions run for real.

## Evidence contract

`build --commit=<full-sha> --output=<new-directory>` requires the clean candidate, runs the existing release preflight plus generation and shell checks, and writes `package.tar.gz` and `receipt.json`. Only tracked owned code is packed. Git modes, bytes and archive metadata are deterministic. Symlinks, secret filenames and untracked inputs inside owned sources are rejected.

The receipt records the full commit/tree, file and package hashes, dependency inputs, validation policy, actual local toolchain versions, checks and provenance. A validation identity binds the commit/tree, dependencies, policy, toolchain and check outcomes. Missing toolchain fields or changes without a matching identity fail integrity verification; that identity is not proof of test execution. It contains no site data or credentials. Installed WordPress, Divi and regular-plugin versions belong to the target compatibility proof in #281, not to this local receipt.

`verify --commit=<full-sha> --directory=<package-directory>` checks integrity against the Git candidate. It cannot prove that someone ran the tests. A caller-created receipt remains untrusted.

`verify-ci --commit=<full-sha> --run-id=<id> --output=<new-directory>` obtains the artifact through GitHub. It requires a successful current attempt of this repository's package workflow on the `wordpress` branch, with the exact candidate SHA. PR/fork/other-workflow runs, stale attempts, missing or expired artifacts and altered bytes fail. The downloaded ZIP must match GitHub's artifact digest; the archive and receipt must also match the Git objects and current run identity. Only then is the result reported as `trusted-ci`.

The integration workflow has read-only repository permissions and no deployment credentials. It packages the merged commit, since a PR or squash merge may produce different bytes. PR validation still applies to the current PR head. Offline full validation remains available when trusted CI evidence cannot be obtained.

## Limits and next consumers

The receipt permits reuse of the recorded local checks for its exact inputs. It does not supply CodeRabbit agreement, a staging receipt, target compatibility, editorial-write attribution or human release approval. Those remain separate requirements. Expired artifacts require a new verified build.

During this first slice the existing staging workflow still reruns preflight. #281 must consume the successful package run before that repetition can be removed. #282 must prove a replacement for the global editorial fingerprint before continuous-editing acceptance changes. #283 binds explicit production approval to the resulting package, candidate, baseline and action. No production deployment is authorized by creating or verifying a package.

GitHub artifact metadata and download behavior follow the [Actions artifact API](https://docs.github.com/en/rest/actions/artifacts). The scope and blocker links follow [delivery plan #278](https://github.com/gemuesegenossenschaft-bioco/bioco-web-project/issues/278).
