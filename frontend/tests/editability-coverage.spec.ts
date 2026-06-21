import { test, expect } from '@playwright/test'
import { AUDIT } from '@/lib/editabilityAudit'

// B.3 — every page exposes a change path in the UI: inline editor markers,
// a "In PW öffnen" deep-link, or (documented) ticket-only. Needs a live
// server WITH the affordance UI rendered + ?_visual=1; runs in CI / manual.
// Run: BASE_URL=https://www.bioco.ch npx playwright test editability-coverage.spec.ts
for (const [route, entry] of Object.entries(AUDIT)) {
  if (route.includes('[')) continue // skip catch-all pattern
  test(`change path present on ${route} (${entry.changePath})`, async ({ page }) => {
    await page.goto(route + '?_visual=1')
    if (entry.changePath === 'inline') {
      await expect(page.locator('[data-ve-field]').first()).toBeVisible()
    } else if (entry.changePath === 'pw') {
      await expect(page.getByRole('link', { name: /In PW öffnen/i }).first()).toBeVisible()
    } else {
      // ticket-only pages are documented in the audit; nothing to assert in-page
      expect(entry.changePath).toBe('ticket')
    }
  })
}
