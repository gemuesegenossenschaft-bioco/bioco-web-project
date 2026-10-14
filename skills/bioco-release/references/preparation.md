# Current preparation commands

Run from the Bioco repository root. Inspect the current scripts and their help before using a saved command:

```sh
git status --short
git rev-parse HEAD
python3 wordpress/scripts/release-wordpress-production.py --help
wordpress/scripts/release-wordpress-staging.sh --help
```

After resolving the candidate, accepted baseline and private review evidence, the existing production preview is:

```sh
python3 wordpress/scripts/release-wordpress-production.py \
  --commit="$BIOCO_CANDIDATE_SHA" \
  --expected-baseline="$BIOCO_ACCEPTED_BASELINE_SHA" \
  --review-evidence="$BIOCO_PRIVATE_REVIEW_EVIDENCE"
```

These variables are operator inputs, not new script features. Read the source for required credentials and evidence schema. The preview reruns preflight and reads the real staging and production installations; absent access means the preview remains unverified. It does not apply code. The existing rollback preview uses the same command with `--rollback`. Adding `--apply` writes remotely and belongs only to an authorized, reviewed action.

At the initial skill revision, `release-wordpress-production.py` compares a broad editorial fingerprint before and after code copy. Normal covered admin or signup writes can therefore fail a release after files have changed. It does not yet implement immutable-package promotion, reusable validation receipts or complete write attribution. Inspect current source and acceptance evidence before treating those planned features as available. Never bypass a failed fingerprint to make a release appear successful.

The release policy comes from Bioco's repository instructions and the user's explicit approval decision. The small preparation/apply boundary adapts the scoped-work approach in [Theo's implementation issue](https://github.com/pingdotgg/t3code/issues/9661) and [poteto's planning skill](https://raw.githubusercontent.com/poteto/noodle/main/.agents/skills/plan/SKILL.md); their release systems are not assumed to exist here.
