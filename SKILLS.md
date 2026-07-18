# SKILLS.md

## /deploy

Full deploy: build frontend, rsync all, restore sharp, upload CMS templates, restart Node.js, verify.

**Usage:** `/deploy` or `/deploy main`

**Skill file:** `.claude/skills/deploy.md`

## /deploy-cms

CMS-only deploy: rsync admin.js, api.php, api-events.php to server. No frontend build needed.

**Usage:** `/deploy-cms`

**Skill file:** `.claude/skills/deploy-cms.md`

## /server-status

Check server health: Node.js process, port, logs, external HTTP status.

**Usage:** `/server-status`

**Skill file:** `.claude/skills/server-status.md`

## /bunny

Porting/migration methodology (codebase, CMS, theme, framework): porting guide first, adversarial reviews, conformance suite gate, all-at-once cutover. Extracted from Bun's Zig-to-Rust rewrite.

**Usage:** `/bunny`

**Skill file:** `.claude/skills/bunny/SKILL.md`
