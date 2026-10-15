# Preserve document containment through code release and rollback

Scope: #232. The reviewed operation removed 17 public copies of three member
documents, including three files in the owned importer seed. Their originals and
verified backups remain private. The accepted Git baseline therefore contains
three files which must never be republished to satisfy a release fingerprint.

## Decision

Compare two approaches:

- A receipt-bound projection verifies the exact private operation, recognizes only
  its three owned deletions, and sanitizes the private rollback extraction.
- A new recovery checkpoint would adopt a different Git baseline through a separate
  reconciliation operation. It preserves Git-only identity but introduces another
  baseline transition, ancestry requirement and approval operation.

Choose the first for this bounded incident. `wordpress_containment.py` owns the
fixed operation digest, protected content identities, private proof, projection
and rollback sanitization. It cannot approve unrelated drift or arbitrary files.
The cost is explicit: the projected recovery state is not byte-identical to its
Git source commit. Receipts record `baseline_projection`, its source commit,
operation/evidence digests and actual resulting manifest. Never describe it as
an unmodified checkout of the old commit.

## Operator interface

The canonical production command optionally accepts
`--containment-evidence=<private-0600-json>`. The production workflow accepts the
same JSON through its optional `containment_evidence` input. The normal private
review JSON must include `containment_evidence_sha256`, the SHA-256 of the exact
containment file bytes. Both documents remain private; neither contains user data
in public evidence.

The containment JSON contains exactly `schema: 1`, the current `commit`,
`accepted_baseline`, production `host` and `content`, the pinned
`operation_manifest_sha256`, fixed private `quarantine` location and `owned_removed`
(mapping the three baseline-relative owned paths to their pinned content hashes).
Obtain paths and hashes from the reviewed private operation and baseline. This is
not a caller-supplied general exclusion list.

The helper requires those same three protected baseline blobs, candidate absence
of their paths and content identities, and a current remote proof of all 17 source
paths absent plus 34 private files intact, with exact intent/completion journal
and private permissions. Forward promotion requires the actual baseline manifest
to equal the original manifest minus only these files. Existing baseline,
review, staging, lease, editorial and runtime guards remain required.

During rollback, remove the three pinned blobs only from the private Git archive
before deployment; compare that extraction and the deployed bytes to the exact
projected manifest. Recheck private proof after acquiring the lease and after
verification. Missing evidence forbids raw rollback to a protected baseline.
Failure never restores the public files, bypasses another manifest mismatch or
publishes a successful marker. Database restore and public Git history remain
outside this operation.
