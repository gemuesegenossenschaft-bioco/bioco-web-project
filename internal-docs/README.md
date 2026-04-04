# Internal documentation mirror (Git)

This tree is a **machine-writable mirror** of ProcessWire pages under `/internal-docs/` (template `internal-doc`). Editors work in the CMS; the nightly GitHub Action exports here, runs automated fixes, commits, and syncs back.

Do not hand-edit these files unless you intend the next sync to push changes into ProcessWire (the sync step sends file contents to `POST /api/internal-docs-sync`).

See [HANDOFF.md](../HANDOFF.md) for secrets and operations.
