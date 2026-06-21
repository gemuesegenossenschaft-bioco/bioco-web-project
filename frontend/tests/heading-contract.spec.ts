import { test, expect } from '@playwright/test'

// C.3 — every page has exactly one <h1>; in-page section titles are <h2>.
// Needs a live server (CMS-fed); runs in CI / manual verify.
// Run: BASE_URL=https://www.bioco.ch npx playwright test heading-contract.spec.ts
for (const route of ['/wir', '/abos']) {
  test(`exactly one h1 on ${route}`, async ({ page }) => {
    await page.goto(route)
    await expect(page.locator('h1')).toHaveCount(1)
  })
}
