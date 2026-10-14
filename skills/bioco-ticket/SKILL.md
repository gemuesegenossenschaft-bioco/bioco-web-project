---
name: bioco-ticket
description: Write or implement a self-contained Bioco engineering ticket with real blockers, one owner and one focused PR. Use for turning an agreed change into tickets or picking up an existing ticket; page content editing does not need this flow.
---

# Work one Bioco ticket

1. Read the checkout's `AGENTS.md` and relevant `CLAUDE.md` sections, tracker configuration, domain terms and ADRs. Read the issue, comments and linked PRs. Check current project threads for owners and holds. Report inaccessible ownership evidence as unknown; an unassigned issue does not prove nobody is working on it.
2. Reuse an existing issue that owns the behavior. Compare its acceptance criteria with source and current verification: an open ticket may already have working code. Separate observed failures from suspected causes. Carry forward the user's scope and authorization; ticket publication does not authorize extra work or a release.
3. When writing tickets, split by complete behavior that a fresh session can demonstrate. Give each real prerequisite its issue link and the reason it blocks this slice. Use native blocker relationships when available. An umbrella ticket or nearby change is not automatically a blocker. Keep design questions that need evidence explicit instead of disguising them as implementation tasks.

Each ticket must stand alone:

```markdown
## Problem
Observed behavior, its effect on editors or release owners, and source evidence.

## What changes
One complete behavior. Name the boundary that keeps adjacent work separate.

## Done when
- Observable success case.
- Relevant failure or conflict case, including what must remain intact.
- Verification command or concrete manual proof, with required environment.

## Blocked by
Issue links and what each must provide, or none.

## Starting context
Relevant decision, current implementation evidence, active owner and known limits.
```

4. For implementation, select one ticket whose blockers are satisfied. Establish its isolated checkout, branch and owner before editing. Keep one concern per PR. Hand unrelated findings back to their existing ticket; split newly discovered work when it is not needed for this ticket's acceptance. For a bug, first produce a check that fails on the reported behavior, then make it pass. Choose validation from the affected behavior and repository gates.
5. Before declaring the change ready, record the exact tested commit and results, inspect CodeRabbit's actual findings, resolve blockers and obtain agreement for the resulting change. A green status from an earlier commit is stale evidence. After further edits, run the affected checks and required gates for the new head. Report skipped or unavailable checks separately. Merge and release follow the repository's current rules; production approval is a separate decision.
6. At a session boundary, leave only what the next agent needs: issue and PR, owner, branch/worktree and full SHA, decisions and source links, commands/results tied to that SHA, unresolved review or environment blockers, and the next bounded action. Leave parent issues open while their acceptance criteria remain unresolved. State any shared-environment hold and existing authorization. The next session rechecks live ownership and refs rather than inheriting them as facts.

These are Bioco adaptations of [Matt Pocock's ticket slices and blocker edges](https://raw.githubusercontent.com/mattpocock/skills/main/skills/engineering/to-tickets/SKILL.md), [Theo's focused implementation PRs](https://github.com/pingdotgg/t3code/issues/9661), and [poteto's small phases and explicit alternatives](https://raw.githubusercontent.com/poteto/noodle/main/.agents/skills/plan/SKILL.md). Their tools and repository conventions are not dependencies.
