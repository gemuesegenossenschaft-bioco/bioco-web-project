# Proposed ticket cleanup, 9 October 2026

Ticket-body and label proposals only. PR #198 contains the independent implementation slices; issue bodies and labels remain unchanged. Based on the GLM 5.3 Flash first pass in `open-issues-audit-2026-10-09.md`, issue bodies and comments, implementation baseline `origin/wordpress` at b0c65f9, and saved public HTML.

The initial audit covered 27 open issues. A final refresh found #195 closed by its separate membership work, leaving 26 open issues. None of the remaining issues has enough evidence here for unconditional closure. Missing evidence is a verification task, not proof that implementation is absent.

## Ask Matt route

Use the existing GitHub tracker and labels configured in `docs/agents/`. These tickets were authored for execution; Ask Matt says not to put agent-ready tickets back through incoming-report triage. Update their remaining scope and use the to-tickets approach where a ticket spans multiple independently verifiable outcomes. Reuse issue numbers where possible.

Every executable ticket needs a user-visible outcome, current behavior, measurable acceptance criteria, genuine blockers, test boundary, safe deployment scope, and required evidence. Record completed criteria with their evidence instead of deleting history. Proposed labels below are readiness recommendations, not applied labels.

## Per-ticket changes

| Issue | Proposed action | Remaining outcome and completion evidence |
| --- | --- | --- |
| #132 | Keep as coordination epic | Link only remaining children. Record WordPress production status, the approved home parity waiver, and unresolved acceptance gaps. Close after child outcomes or explicit scope decisions are recorded. |
| #135 | Narrow; closure candidate after verification | Attach #191/#192 evidence and correct the obsolete home 0.95 criterion to the documented waiver. Verify native shell preset ownership, keyboard navigation, focus and mobile menu on the deployed release. Keep other-route parity owned by #142. |
| #136 | Narrow to editorial editability and acceptance | Enumerate editorial routes. For each, change and revert representative title/text/image through supported Divi editing; prove shared preset behavior and preserved editorial values on repeat import. Route-specific visual evidence belongs to #142, referenced here. |
| #137 | Narrow to interactive acceptance | Enumerate dynamic components and their routes. Cover loaded, empty and failure states, map interaction, event detail, calculator and mobile keyboard behavior. Link existing module registration work; remaining defects become small child tickets. |
| #138 | Retain focused refactoring requirement | Extract one cohesive contact submission responsibility from the shared monolith while preserving public request/response and six-form behavior. Demonstrate validation, spam rejection, recovery and accessibility at the REST/browser boundary. Line count alone is not acceptance. |
| #140 | Narrow to remaining form evidence | Create a matrix of all six flows, accepted/rejected requests, confirmation messages, DOI expiration/replay and mail effects. Retain the prior email-only decision. Reference shared lifecycle work; avoid rebuilding it. Membership-specific delivery belongs to #195. |
| #141 | Keep as SEO/security acceptance parent | Deduplicate implementation scope with #155/#163/#154. Retain independent canonical/social/structured-data, accessibility and performance checks. Define route coverage and explicit severity/budget thresholds before execution. Keep production submissions outside this ticket. |
| #142 | Rename to remaining migration acceptance | Preserve frozen legacy references and document home waiver. Produce a current 21-route, two-viewport comparison with per-route results and approved differences. Add deployment SHA and staging CI evidence. Historical failures cannot certify current failure or success. |
| #144 | Narrow to Turnstile enforcement and delivery proof | Record the real public site key as completed. Privately verify the secret and hostname configuration. Prove valid, invalid, expired and replayed token handling across all six forms; capture rejected-request side-effect counts. Retain a separately authorized delivery check, since rendering the widget does not prove backend enforcement or inbox receipt. |
| #147 | Split into small outcomes | Vegetable calendar management, depot management, group editing/import preservation, and shared pricing are separate verifiable slices. Existing populated groups are completed work, not a new population task. Move RankMath dependency drift to #155. |
| #150 | Narrow to unresolved editorial decisions | Keep the existing comparison table; identify remaining rows and client owner. Record keep/discard/source for each disputed section, then verify chosen copy in WordPress and repeat-import preservation. Archived sources do not constitute client approval. |
| #153 | Rename to production backups and off-server recovery | Preserve the staging restore result. Identify whether the new production tables/uploads are covered. Configure approved remote storage and restore to an isolated target. Evidence must include schedule, retention, remote retrieval, restored content and cleanup. |
| #154 | Clarify privacy behavior before implementation | Inventory actual cookies, storage and third-party requests. Decide which resources require consent, including maps and cookieless analytics, with the responsible reviewer. Implement the approved rule; test initial state, accept, reject, persistence and withdrawal. Cookieless tracking alone does not settle the decision. |
| #155 | Narrow to SEO metadata coverage | Fix the empty home-title suffix and missing descriptions on sampled routes. Define approved title/description for every canonical route and verify actual rendered tags, canonical URLs and social previews. Verify deployed RankMath inventory rather than inferring absence from Composer. |
| #156 | Split caching from security | Retain this issue for caching and move security plugin selection to #163. Compare cold/warm performance, prove cache invalidation after edits, and exclude POST, DOI, signup receipts and private admin responses. Choose the cache after inspecting actual hosting capabilities. |
| #157 | Split newsletter operations | First deliver confirmed-subscriber administration/export and safe unsubscribe. Sending/provider integration follows the owner decision. Test with a dedicated list and captured transport; record inbox evidence separately. Prevent unconfirmed/unsubscribed recipients from being mailed. |
| #159 | Separate implemented fixes from client changes | Recheck each punch item; do not redo contrast work already closed under #178. Record accept/decline for every proposed presentation change. Capture a new design baseline for future changes while retaining the legacy migration reference for #142. |
| #160 | Narrow to missing recheck decisions | Retain the September matrix and fill missing human checks. Link regressions #174–#177. This audit ticket can close once every result is recorded and each failure has an owner/ticket; children need not all be fixed first unless explicitly required. |
| #163 | Replace inferred absence with configuration audit | Verify actual production controls and update path, then repair evidenced gaps. Cover login protection, uploads execution, HTTPS administration, DB privileges, protected REST scope and dependency updates. Validate importer compatibility in an isolated environment before privilege changes. |
| #164 | Rename to Matomo counting verification | Record cookieless implementation and actual tracker URL matomo.bioco.ch. Verify tracker availability and a controlled pageview in the analytics report. Coordinate the approved privacy rule with #154. Preserve legacy /matomo routing only if still required. |
| #165 | Remove completed and superseded criteria | Preserve the explicit seed-ID decision from comments; do not restore legacy anchors accidentally. Record mailto and distinct CTA copy evidence. Verify /anmeldung visually and narrow to the minimal signup template if header/footer are actually visible. Test affected routes at desktop and mobile. |
| #174 | Keep; strengthen for direct execution | Render approved depot websites as real links in map popups and any equivalent list. Test all four destinations, safe escaping, and absence of removed personal names/phone numbers. Chrättli's approved URL is a content input, not something to invent. |
| #175 | Mark needs-info until copy supplied | Obtain approved arrival/parking copy, then publish it on the Schnuppertag page with the turn-around/yard instruction. Verify readable placement at both widths and editable source. Identify the content owner and source. |
| #176 | Separate inputs from implementation | Obtain approved PDFs and the 15/16 Uhr decision. Then publish labelled links and verify PDF responses and chosen pickup wording. Current 16:00 text is not evidence of approval. |
| #177 | Mark needs-info until contacts supplied | Obtain approved BG, ELKI and Kräutergruppe contact methods and permission to publish. Make them clickable in the existing group cards and editable in admin; verify each target. Preserve the rule excluding depot personal contacts. |
| #195 | Keep ready-for-agent; clarify two adapter paths | Fake staging acceptance intentionally sends no backup mail. Test that path separately from production-local behavior in an isolated harness with captured mail/storage and no live HTTP. Prove receipt reuse, complete summary, rejected-input silence and recoverability after mail failure. Document manual intranet handoff and draft a separate transport ticket only if requested. |
| #100 | Rename to post-cutover assurance and retention | Record the live WordPress switch as completed. Retain rollback rehearsal evidence, production backup coverage, authorized mail/inbox check, Matomo count and retention/decommission plan. Automated production deployment was not demonstrated. Rehearse rollback in an isolated setup; a live traffic switch needs separate operational authorization. |

## Proposed executable slices

Reuse the issue where possible; create children only for the splits below. A label says the acceptance brief is executable, not that credentials or content are already available.

1. **#195: Prove membership acceptance and backup notification.** Blocked by none for the isolated harness. Deliver fake staging trace, local-mode captured mail, replay/rejection/failure evidence, and explicit manual intranet processing. Run the actual handler with mocked external boundaries; fail on unexpected outbound HTTP or mail. Targeted tests and full regression results are required. No live registration.
2. **#174: Make approved depot websites clickable.** All four URLs, including Chrättli, were found in existing source data. Deliver the four approved anchors in rendered popups/list with personal-contact regression coverage. Other three links can be implemented independently.
3. **#165: Give signup its minimal template.** Blocked by a current visual check establishing the gap, not the entire #136. Deliver minimal signup header/footer behavior, accessible wizard navigation and unchanged thank-you mailto. Verify desktop/mobile rendered behavior.
4. **#155: Publish complete route metadata.** Blocked by approved wording for undecided entries. Deliver a route matrix with title, description, canonical and social tags; verify live HTML after the approved release. Fix the homepage title as the first complete slice.
5. **#142 child: Restore staging release CI.** Latest failure is missing Playwright, not credentials. Pinned browser dependencies and Chromium installation are implemented in this worktree. Deliver a successful run of the sole staging release entry point for a reviewed full SHA, with marker and 22-route smoke evidence. Avoid a separate deployment path.
6. **#153: Prove remote production recovery.** Blocked by remote storage choice/access. Deliver current production DB and files restored privately to an isolated target, with schedule/retention evidence. Never overwrite production during the test.
7. **#163: Prove and repair login/upload protection.** Blocked by access and the documented plugin update path. First record actual configuration. Deliver denied unauthorized operations and intact admin/media/import behavior; private evidence excludes credentials.
8. **#147 child: Restore the complete editable season calendar.** Existing public source supplies all 54 names and their month mappings. Deliver admin CRUD reflected on the calendar, complete legacy-list reconciliation, and repeat import preserving edits.
9. **#147 child: Manage depots in admin.** Blocked by none for model/import work; approved data needed for release. Deliver depot CRUD reflected in map/list, preserve website links and personal-data exclusions, prove repeat import does not clobber edits.
10. **#147 child: Preserve editable groups.** Blocked by none for existing groups. Deliver admin edit/create/delete reflected in cards and repeat-import preservation. Contact completion remains #177, so the whole slice is not blocked on missing contacts.
11. **#147 child: Keep calculator and table prices consistent.** Blocked by approved tariff values. Deliver one editable pricing source used by both views; behavior tests show a tariff change reflected in both and correct calculations.
12. **#157 child: Manage and export confirmed subscribers.** Blocked by none. Deliver useful admin columns and restricted CSV export; test authorization, confirmation status and spreadsheet formula escaping.
13. **#157 child: Unsubscribe from the test newsletter.** Blocked by a provider/send decision and #12's subscriber contract. Deliver a dedicated test-list send with functional unsubscribe, subsequent-send exclusion and clear confirmation. Test transport acceptance separately from inbox delivery.

## Order and owner decisions

- Start independent membership verification, depot links, signup-template check, metadata inventory and latest CI-failure diagnosis.
- Per user instruction, skip content/service work when approved inputs cannot be found. Existing repository sources supplied the Chrättli URL and arrival copy. Contacts, PDFs, pickup decision, newsletter provider and remote-backup destination remain pending. No client message was sent.
- Prioritize production backup coverage and evidenced hardening gaps. Keep cache changes separate from security controls.
- Resolve the privacy resource policy before consent implementation. Resolve newsletter provider and remote-backup destination before those integrations.
- Verify #135 for closure after correcting its home waiver. Narrow #153 instead of closing it. Close #160 only after its missing matrix results are recorded.
- Preserve old parity artifacts. A new WordPress baseline serves future design regression checks; it cannot retroactively prove migration parity.

## Execution contract

Use a fresh checkout of the selected reviewed WordPress SHA; the current local branch is older. For implementation follow the repository runner ladder unless the user explicitly selects another runner. GLM 5.3 Flash was selected for this verification pass only. Each result includes commit, environment, command, outcome, artifacts and remaining limitations. Deploy staging through the canonical release script. Production writes, bulk mail, live intranet submissions and decommissioning remain separately scoped operations.

Proposals are complete for review. Publishing revised bodies, labels, dependencies or new child issues is a later step.

## Worktree results, PR #198

Implemented independent slices of #138, #142, #147, #165, #174 and #175 in
`fix/open-issues-completion`, based on b0c65f9. GLM 5.3 Flash performed the initial
verification. Kimi returned HTTP 403 `access_terminated_error`; the user explicitly
authorized Codex instead. No quota exhaustion was claimed.

- Staging release 8531b51 passed the canonical entry point, 662 tests, PHP lint,
  seed checks, runtime verification and all 22 public route smoke checks.
  55 environment-gated tests were skipped. Production was not changed.
- Staging catalog initialization created 54 vegetables and nine depots. Mobile
  browser inspection found 54 calendar rows, 12 month columns and four website
  links. Signup retained its form and home logo, with no footer content or
  horizontal overflow at 390px.
- All 24 original form functions were preserved byte-for-byte during extraction.
- The arrival seed reuses existing Standorte & Depots wording. A guarded staging-only
  insertion placed it before the visit form, preserved every existing content byte
  and retained a revision. Mobile browser inspection confirmed the heading,
  parking/wendeplatz copy and form. Do not force-import the other page content.
- A subsequent commit removes trailing blank lines only. The staging marker still
  identifies the behavior-equivalent 8531b51 release.
- CodeRabbit refused review because all three included reviews were consumed;
  no CodeRabbit approval is claimed. Direct diff review and runtime checks ran.
- Production read-only checks found Rank Math installed but registration invalid,
  and remote backup services unset. Do not bypass registration or invent a destination.

These results do not close the parent tickets. Remaining editor acceptance, route
parity, shared pricing, live delivery and deployment evidence remain explicit work.
The missing content/service inputs remain excluded under the user's instruction.

## Execution pass, 10 October 2026

The current instruction authorizes execution of open issues, excludes #199-#215,
and asks for status checks on #216 and #217. All changes from this pass belong to
one PR based on `wordpress` at `29fc0ed`. The original dirty checkout is preserved.
The client agreed to defer missing inputs but supplied no destination, PDFs,
contacts, pickup-time decision or approved content/SEO decisions.

| Issue | Current result and remaining work |
| --- | --- |
| #100 | Cutover already live. Added retention/decommission checklist. Rollback rehearsal, inbox delivery, counting and production backup proof remain. No traffic switch or deletion performed. |
| #132 | Keep epic open. Remaining native-editor and responsive acceptance depends on excluded #199-#215 and the children below. |
| #135 | Existing shell/home implementation retained. Shared-preset ownership and deployed keyboard/mobile acceptance remain; home parity waiver is preserved. |
| #136 | Editorial migration already implemented. Native editing/save/reopen proof and frozen-reference parity remain; excluded editor work blocks acceptance. |
| #137 | Dynamic modules already registered. Interactive/failure-state and responsive acceptance remain; excluded module work is not reimplemented here. |
| #138 | PR #198 extracted the shared form handlers. Regression suite passes. Contact staging browser/parity acceptance remains. |
| #140 | Six shared forms and DOI retained. Newsletter re-opt-in now invalidates earlier unsubscribe links. Per-form staging behavior/parity evidence remains. |
| #141 | Added restricted anonymous users endpoint, login limiting, security headers and upload routing denial. Metadata, privacy, a11y and performance acceptance remain owned by the related tickets. |
| #142 | Current staging release CI passes at `29fc0ed`, run 37960562820. Frozen reference comparisons, client content decisions and editor acceptance remain. |
| #144 | Real public key already present. Private secret/hostname and all-six-form production inbox tests remain. No production form submitted. |
| #147 | Existing vegetable/depot catalogs and groups retained. Shared pricing, editorial preservation acceptance and client tariff approval remain. |
| #150 | Client source decisions remain missing. Recovered sources are retained; no disputed public text overwritten. |
| #153 | Off-server destination/access and production restore proof remain missing. No backup destination invented. |
| #154 | Implemented editable consent copy, independent map/analytics choices, persistence and withdrawal. Explicit text setup and staging/production acceptance remain required. No consent plugin is added. |
| #155 | Added Rank Math to Composer and locked dependencies. Client-approved route metadata and actual plugin installation/rendered output remain. |
| #156 | Cache/security plugin installation remains. Production SSH timed out, so hosting capability, cold/warm measurements, cache exclusions and invalidation cannot yet be verified. No cache enabled speculatively. |
| #157 | Implemented confirmed-only CSV/admin columns, queued plain-text sending, per-recipient delivery state, signed unsubscribe and re-opt-in. Captured transport tests pass. Dedicated staging test-list inbox proof and provider signing remain. No live list sent. |
| #159 | Client punch-list decisions/signoff and a new design baseline remain. Existing contrast fixes retained; legacy migration references are not replaced. |
| #160 | Prior recheck matrix retained. #174/#175 and signup behavior rechecked on current staging. Remaining human-check outcomes require their owners. |
| #163 | Added login/REST/upload controls, Dependabot and a locked update path. Private production flags, database grants, upload behavior and update-path acceptance remain. |
| #164 | Tracking now waits for analytics consent and stops after withdrawal. Controlled production counting still requires Matomo report access. |
| #165 | Verified for closure. Seed-anchor decision retained. Current staging signup has one home-logo link, a form, no footer text and no horizontal overflow at 390px and 1280px. Thank-you page links to `mailto:info@bioco.ch`; all five page-specific CTA texts match the seed. |
| #174 | Current staging HTML includes all four clickable website destinations, including Chrättli. Existing escaping and personal-contact regression tests pass. |
| #175 | Current staging includes Velo/Bus, not driving onto the farm, street parking and keeping the turning area clear. Copy was recovered from the existing approved location guidance in PR #198. |
| #176 | Approved PDFs and the 15:00/16:00 decision remain missing. No substitute documents or pickup-time changes invented. |
| #177 | Approved BG, ELKI and Kräutergruppe contact methods remain missing. No personal contacts invented. |

PR #216 is open and unmerged at `960a491`. CodeRabbit is successful and the PR is
mergeable. Production React still returned HTTP 404 at verification, so the
production acceptance described in its body is incomplete. This pass does not
merge or extend it because it implements excluded editor work.

Issue #217 is open and incomplete. On production the back button was blue
(`rgb(46, 163, 242)`) with a 3px radius at 1280px and 390px. The desktop button
bottom and footer top were both 983.3125px, leaving zero gap. Editable event
template work depends on excluded #202. No event-template change is claimed.

Local evidence is recorded in the PR: release preflight, captured newsletter
transport/storage tests, real-browser consent tests, routing tests and CodeRabbit
review. Final local preflight passed 756 tests (55 skipped), PHP lint, the
hardcoded-content gate and the 22-page/111-block seed plan. Composer audit found
no vulnerability advisories; the existing abandoned `roots/wp-password-bcrypt`
package still requires a separate compatibility decision.
The first staging release passed at `10f1b9d` (run 38044168023). All nine
consent texts were saved and reopened through WordPress admin. On the depot
page, rejection persisted, map-only opt-in loaded tiles without analytics, and
withdrawal removed the tiles while preserving addresses. At 390px the banner
had no horizontal overflow and buttons exceeded 44px. Subsequent requests
showed stale PHP behavior; the canonical release now invalidates owned staging
web OPcache before runtime verification. Review fixes also synchronize tabs,
cancel pending Matomo commands and strengthen the login boundary.
Staging code release and explicit consent-text setup must be recorded
before #154 can close. Do not close the broader tickets from source changes alone.
