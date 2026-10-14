---
name: bioco-release
description: Prepare, apply or recover a Bioco WordPress code release using exact version evidence and explicit production approval. Use for staging promotion, production release packets or code rollback, not publishing CMS content or deploying documentation.
---

# Prepare a Bioco release

Read the checkout's `AGENTS.md`, `CLAUDE.md` release/editorial-ownership sections, current release ADRs and canonical entry-point source. Check active project threads, release owner and holds before writing a shared environment. Establish the accepted baseline from its marker and actual owned-code manifest. A marker alone cannot prove which files remain after a failed apply.

## Prepare the approval packet

Do the available preparation before asking for production approval. Use [current preparation commands](references/preparation.md) to discover the supported interface. Keep private evidence outside committed files and redact public summaries.

The packet contains:

- Environment and action: forward code release or code rollback.
- Full candidate and accepted-baseline SHAs, owned-code manifest digest, and package digest when the implementation supports packages.
- Changes and known risks, linked PR/review evidence, applicable tests for the exact commit, resolved CodeRabbit blockers, and the exact staging receipt and runtime results.
- Release owner, current holds/lease status, dry-run result and evidence locations.
- Recovery target and its verified code-only procedure, including the evidence that later edits and signups survive.

Mark each missing check as missing. Fixture results do not establish live Divi or host behavior. Reuse a test receipt only when the implemented verifier accepts its exact code, dependencies, toolchain and policy inputs; otherwise run the required checks. Once the packet is ready, ask for approval of that environment, version and action.

## Apply the approved action

Production apply and production rollback each require explicit user approval for the concrete packet. A prior forward-release approval does not approve rollback. An approval for another SHA, baseline, environment or action does not transfer. If approval already covers the unchanged packet, continue without asking again.

Immediately before apply, recheck holds, owner, target identity, candidate, accepted baseline and evidence. Acquire the canonical server lease; CI concurrency alone does not cover local writers. A lease grants exclusive execution, not user approval. If another release changed the baseline, prepare a new packet. Use only the inspected canonical entry point. Keep code promotion separate from seed imports, saved-template changes and database recovery.

## Preserve edits through failure

If apply fails, retain logs and inspect the failed step, actual manifest and last successful marker. Report partial code state accurately; do not label a copied candidate as released. Prepare rollback from the actual candidate/baseline files through the canonical code-only path. Preserve later registrations, signups and editorial edits. A database backup is recovery evidence, not permission to restore it.

Content/template migrations require their own preview, private before-state, conditional write and restore evidence. Refuse a write or restore when an editor has changed its target since preview. For the continuous-editing pipeline, require proof of an admin save and a visitor signup surviving both release and rollback before claiming that behavior. Direct SQL writes require coverage too; WordPress hooks alone do not establish complete attribution.

## Report the result

Report actual deployed SHA/manifest, verification results and receipt locations, or the failed step and remaining recovery action. Separate deployed, verified and approved states. Preserve the existing guards when a planned capability is absent.
