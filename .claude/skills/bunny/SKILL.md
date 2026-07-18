---
name: bunny
description: Guide-driven porting loop for migration projects — serialize the porting rules before porting anything, gate every unit on a source-captured conformance suite, and fix the process instead of the output. Use when porting a codebase to another language, migrating between CMSes, swapping a site theme, or moving between frameworks.
---

# bunny — the porting loop

Quality lives in the guides, not the porter. The loop is: **generate a guide-conformant port → adversarial review → conformance gate → commit**. You monitor outputs and edit the loop; the loop writes the port. Extracted from Bun's Zig→Rust rewrite (~535K lines, ~50–64 concurrent Claude workflows, 11 days).

## Steps

### 1. Serialize the porting guide

Discuss, then write `PORTING.md`: every recurring SOURCE pattern, type, or idiom mapped to its TARGET equivalent, with a code/markup example per mapping. Bun spent ~3 hours on this document before porting a single file — match that investment relative to project size.

**Done when:** `PORTING.md` exists and a sample of 10 random source files contains zero recurring patterns missing from the guide.

### 2. Catalog the hard cases

Inventory the genuinely hard mappings in a separate file (Bun: `LIFETIMES.tsv` for struct fields with complex lifetimes). One row per case: source item → proposed target mapping. Send each row through 2 adversarial reviewers, apply their feedback, then serialize the final mapping. Typical hard cases per migration type: interactive widgets, data-model mismatches, escaping/sanitization differences, ownership/lifetime semantics.

**Done when:** every known hard case has a mapping that survived 2 adversarial reviews.

### 3. Review the guides together

Run an adversarial review round on `PORTING.md` + the hard-case catalog as one document set. Conflicting rules between the two files are the target — mass execution amplifies every contradiction into dozens of wrong files.

**Done when:** a second agent read both documents and found zero conflicting rules.

### 4. Capture the conformance suite

Build a test suite that is indifferent to which implementation runs, captured from the LIVE source system before porting starts (Bun: a TypeScript suite with ~1M assertions ran unchanged against the Rust build). See Reference for what conformance means per migration type.

**Done when:** the suite runs green against the SOURCE system and contains no implementation-internal assertions.

### 5. Tracer trio

Port the first ~3 units (files, pages, templates) with 1 implementer + 2 adversarial reviewers each. Reviewers check two things independently: behavior parity against the source, and conformance to the guides. Serialize every guide gap the reviewers expose back into `PORTING.md` before scaling.

**Done when:** 3 units pass both reviews and the conformance suite, and all review findings are folded into the guides.

### 6. Transpile first, idiomatic later

The initial port deliberately mirrors the source structure 1:1 — same file boundaries, same function shapes, target syntax. Idiomatic refactoring is a separate phase that starts only after the shipping gates pass; structure-matching ports are diffable against their source, idiomatic ones are opinions.

**Done when:** each ported unit maps line-region-to-line-region onto its source.

### 7. Scale to the monitored fleet

Run the loop from the core concept across parallel workers, sized to the remaining inventory. Give each worker an explicit unit assignment and a commit rule that names its files (Bun's fleet was forbidden any git command that doesn't commit a specific file, after workers stepped on each other with stash/reset). Your job is monitoring outputs and editing the loop.

**Done when:** every unit in the inventory is ported, reviewed, and green on the conformance suite.

### 8. Fix the process, not the output

A wrong ported unit is a bug in `PORTING.md` or the workflow loop: edit the guide or the loop, then regenerate the unit. Hand-patching one instance of a systematic error leaves the same error in every sibling unit and in every future run.

**Done when:** every error class found during the fleet phase has a corresponding guide/loop edit, and the affected units were regenerated, not patched.

### 9. Cut over all at once

Port everything, gate on the conformance suite, cut over once. Incremental migration adds temporary interop code "you hope gets deleted eventually" and is painful for the whole short/medium term — Bun's position, learned from porting esbuild's transpiler Go→Zig.

**Done when:** the conformance suite is green against the TARGET, the single cutover is executed, and zero interop shims exist in the result.

## Reference: conformance per migration type

- **Code port (language→language):** the existing test suite, run unchanged against the target build. Missing coverage? Write behavior tests against the source first.
- **CMS migration:** content inventory, redirect/URL inventory, rendered-output snapshots, and form-behavior contracts (fields, validation, destination, emails) — all captured from the live system.
- **Theme swap:** content model and forms live OUTSIDE the theme; the theme is a replaceable rendering adapter. Conformance = reference screenshots + DOM snapshots per template type + form contracts, with content and data untouched by the swap.
- **Framework migration:** route inventory, per-route DOM snapshots, API response contracts, redirect map, headers/caching behavior.

## Source

Methodology extracted from bun.com/blog/bun-in-rust (Bun's Zig→Rust rewrite).
