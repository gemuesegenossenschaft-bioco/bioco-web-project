# WordPress PR merge gate

WordPress changes merge into `wordpress`. The default branch remains `main`,
which retains the legacy application. Changing the default branch needs a
separate reviewed decision. The staging release still runs on pushes to
`wordpress`; PR validation does not deploy.

## Required checks

`Verify WordPress PR` runs on every `pull_request` into `wordpress`, including
forks. There is no path filter. Every change requires all three job results:

| Required check name | Gate |
| --- | --- |
| `WordPress tests` | Full pytest suite, isolated form browsers, required-coverage report check |
| `WordPress PHP and shell lint` | PHP 8.2 syntax for all mu-plugins and `bioco-divi`; `bash -n` for shell scripts under `scripts/`, `wordpress/`, and `tests/` |
| `WordPress generation and conformance` | Design-system bundle drift, Divi manifest contract, hardcoded-content baseline, seed plan, seed mirror equality |

The jobs use SHA-pinned actions and `contents: read`. Checkout does not retain
credentials. The PR workflow has no secrets, deployment environment, SSH key,
server command, or implementation-agent action. Dependencies use the staging
release's `requirements-ci.txt` with `--require-hashes`. PHP has ext-dom;
Chromium, Node, and Apache provide the browser, JavaScript, and routing runtimes.
Failure artifacts retain JUnit XML and `output/` test evidence for 14 days,
with the PR head SHA in each artifact name. A newer run cancels the previous
run for the same PR.

The only generator currently supporting `--check` is
`wordpress/scripts/build-divi-design-system.py`; CI runs that mode without
rewriting its bundle. Native-module and routing generators have no check mode
at this base revision. Existing pytest modules execute the routing generator
and test native metadata/runtime contracts; they do not establish full native
regeneration equivalence. Add a read-only native drift gate when its generator
supports one. CI must not regenerate native modules in place to hide drift.
The existing `conformance.sh` emits a diagnostic manifest and reports some
failures without a failing exit code. The PR gate instead runs the failing
content, seed, and design-system checkers directly.

## Required test coverage

All changes run the same coverage, including documentation and workflow changes.
The explicit module list and three operator-only node exclusions live in
`.github/workflows/wordpress-required-tests.json`. Update that policy when
adding or replacing coverage; removing a required module requires review.

| Surface | Required evidence in `WordPress tests` |
| --- | --- |
| Browser behavior | Chromium consent and intercepted Matomo/map requests, depot popup escaping, local screenshot capture, isolated six-form lifecycle fixtures |
| Accessibility | Chromium button contrast, keyboard focus and mobile-menu scrolling; navigation focus, Escape, reduced-motion and ARIA contracts; static palette contrast |
| Security | Real PHP capability/nonce checks, REST/login restrictions, newsletter transport capture and CSV escaping, membership validation; browser consent and fail-closed form behavior |
| Routes | Generated production-rule contracts, content route contracts, real local Apache editor/private-resource denial and nested rewrite regressions, editor guard installation fixtures |
| Integration contracts | Script dependency resolution, intercepted Turnstile and form requests, Matomo command order, membership handoff; no live transport |

`BIOCO_APACHE_TESTS=1` and `BIOCO_BUTTON_BROWSER_TESTS=1` make missing runtimes
fail. The full suite runs with the form module's default opt-out, then a second
run sets `BIOCO_FORMS_BROWSER_TESTS=1` and executes its isolated fixtures.
`check-required-tests.py` combines both JUnit reports. A required module with
no executed passing test fails. Any required test left skipped fails too;
a skip in the first run is accepted only if that exact test passes in the
second. Missing/malformed reports and failed required tests fail closed.
A later passing result cannot erase a failure.

Three named form proofs need a separately provisioned WordPress/Divi instance:

- `test_wordpress_runtime_emits_runtime_before_adapters_without_blocking_turnstile`
- `test_wordpress_real_page_mounts_runtime_and_adapters`
- `test_wordpress_hung_turnstile_blocks_native_get_and_api_submit`

They remain visible as operator follow-ups in the coverage log and are
explicitly deselected from the second run. Passing CI does not establish these
runtime proofs, a deployed Divi editing session, whole-site WCAG compliance,
production visual parity, Matomo admin settings, real mail delivery, or live
integration acceptance. Reviewers must request the applicable separate evidence
when a change affects those surfaces. Use unpublished verification copies and
captured transports; never submit production forms or consume live DOI tokens.
CI tests use PHP fixtures, intercepted browser traffic, local HTTP servers,
and temporary directories. Do not run the staging render shell gate, an HTTP
probe against bioco.ch, a deployment script, or live integration submissions in
this PR workflow.

## Current-commit review acceptance

Before merging, the reviewer records the PR's full current head SHA and verifies:

1. All three required checks succeeded for the latest PR head and current base.
   A pending, cancelled, missing, skipped, or failed job blocks merge. GitHub
   tests the synthetic PR merge revision by default; associate that run with
   its `pull_request.head.sha` and base revision. Artifact names retain the
   head SHA. Do not use a successful run from an earlier PR commit.
2. CodeRabbit completed a review of that exact head commit. Check the review's
   `commit_id`, reviewer identity (`coderabbitai[bot]`), review link, and findings.
   A queued, rate-limited, errored, or absent review does not qualify. A generic
   successful `CodeRabbit` check/status or summary alone is insufficient.
3. No blocking CodeRabbit finding remains unresolved. Critical/major findings,
   security or data-loss findings, explicit requests for changes, and findings
   a maintainer marks blocking block merge. Resolve them in code and obtain a
   fresh current-commit review, or record a maintainer's specific, evidence-based
   disposition of a false positive. Merely clicking "Resolve conversation"
   does not establish that a blocker is fixed.
4. A maintainer approves the current commit after checking the review and any
   required operator evidence. Record `head SHA`, check-run links, CodeRabbit
   review link/commit, blocker dispositions, and operator evidence in the PR.
   A new push invalidates earlier approval/evidence and requires this review
   again, even when the changed files appear unrelated.

`.coderabbit.yaml` already enables automatic review for `main` and `wordpress`.
Its `request_changes_workflow: false` means its generic status cannot enforce
this acceptance rule. These workflows enforce test coverage only. The maintainer
review and repository protection must enforce the review decision; do not claim
automatic CodeRabbit blocker enforcement from this patch.

## Admin follow-up: apply protection

Read-only inspection on 2026-10-10 found:

- Repository visibility `public`; default branch `main`.
- `GET /repos/gemuesegenossenschaft-bioco/bioco-web-project/branches/wordpress/protection`
  returned HTTP 404 with `Branch not protected`.
- `GET /repos/gemuesegenossenschaft-bioco/bioco-web-project/rules/branches/wordpress`
  returned `[]`; the repository ruleset listing was empty.

No settings were changed. An admin must create an active protection rule for
the exact branch `wordpress` with these settings:

| Setting | Required value |
| --- | --- |
| Require a pull request before merging | On |
| Required approving reviews | 1 maintainer approval |
| Dismiss stale approvals on new commits | On |
| Require approval of the most recent reviewable push | On; approver differs from the last pusher |
| Require conversation resolution before merging | On |
| Require status checks before merging | On; the three exact names above, from GitHub Actions |
| Require branches to be up to date before merging | On (strict status checks) |
| Allow bypassing PR/check requirements | Off; no bypass actors |
| Include administrators / do not allow bypassing the above settings | On |
| Allow force pushes | Off |
| Allow branch deletion | Off |

Wait for the three check names to appear from a real PR before selecting them
in protection settings. Do not select a generic CodeRabbit success as a
substitute for the review rule. Keep `main` and its existing settings unchanged.
Do not enable a merge queue without adding and validating `merge_group` support
in a separate change.

GitHub documents protected branches for public repositories on Free plans in
its [protected branch documentation](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches).
This repo's missing protection is observed; no hosting/plan restriction has
been established. The admin must record the saved settings and effective rules.
If the account or hosting UI/API refuses a setting, retain the exact error,
plan/hosting limitation, and compensating manual rule. Do not mark enforcement
complete while it is unavailable. GitHub protection cannot infer unresolved
blocking review semantics from a generic success status.

## Admin follow-up: prove the hosted gate

Local tests execute the real authorization script and report checker, and run
the lint commands against temporary valid/invalid files. They prove ordinary
assignment does not authorize an agent, write permission is checked, malformed
or skipped required coverage fails, and invalid PHP/shell syntax fails.
They do not prove hosted PR checks or repository merge enforcement.

Local validation on 2026-10-10:

- `python3 -m pytest tests/test_wordpress_pr_gates.py -q`: 30 passed.
- All workflow YAML parsed with Python PyYAML; actionlint 1.7.12 passed all
  workflow files (`actionlint -shellcheck= .github/workflows/*.yml`). Shell
  syntax was checked separately with `bash -n`.
- The real workflow PHP/shell lint and generation/conformance commands passed.
- `python3 -m pytest tests -q`: 828 passed, 55 skipped, 2 failed, 10 errors.
  All failures/errors were existing Chromium tests whose launch was denied by
  the macOS sandbox (`bootstrap_check_in`, Mach-port permission denied).
  Full-suite green verification remains pending on a browser-capable runner;
  no browser assertion was weakened or skipped to hide this environment limit.

After applying protection, use a disposable PR targeting `wordpress`:

1. Record its head/base SHA and all three passing check URLs, artifacts if any,
   current-commit CodeRabbit review, and maintainer acceptance. Verify the
   merge UI shows the configured checks are required.
2. Push a controlled PHP syntax error in an owned fixture file or temporarily
   mark a required test module entirely skipped on that disposable branch.
   Retain the failing check URL/JUnit or lint artifact and the blocked merge UI.
   Do not merge the controlled defect.
3. Remove the defect in a new commit. Verify the earlier green result/review
   cannot satisfy the new head. Obtain fresh checks, CodeRabbit evidence, and
   approval. Retain both head SHAs and links; close the PR without merging if
   it was only a proof fixture.

Record the applied settings and passing/failing PR evidence with issue #226.
These hosted proofs and settings are follow-ups, not completed code acceptance.

## Implementation-agent authorization

`claude.yml` authorizes only a human repository writer/admin/maintainer's
explicit `@claude` issue opening, issue/PR comment, or PR review, or that human's
assignment of an issue to the designated login `claude[bot]`. A mention must
be a separate token. Assigning an ordinary maintainer never starts Claude,
even when the issue body already mentions it. Association alone is insufficient:
the read-only authorization job verifies actual repository permission with
GitHub's API and denies the request if verification fails.

Only the authorized agent job receives contents, pull-request, and issue write
permissions plus the OIDC permission required by the action's default GitHub App
authentication. It does not receive `actions: read` or a non-writer bypass.
`claude-task.yml` remains an explicit `workflow_dispatch` request available to
repository writers; it has the same job-scoped permissions and pinned actions.
See the action's [authentication requirements](https://github.com/anthropics/claude-code-action/blob/1d6de8cb0c237e7c15e9e1bdf973826ebae490cc/docs/faq.md).
The designated bot account must be installed and assignable before assignment
requests can work. An admin must verify that operational setup; changing the
allowlisted login requires a reviewed workflow change.

Issue/comment events load their workflow from the default branch. Because this
lane targets `wordpress` while `main` remains default, a maintainer must backport
the Claude workflow hardening to `main` through a separate reviewed PR before
claiming those event triggers are hardened on GitHub. Preserve the legacy
application and default branch during that backport. This lane changes code
only and does not activate the event workflow on `main`.
