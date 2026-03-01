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
