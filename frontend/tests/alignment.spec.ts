import { test, expect } from '@playwright/test'

// C.2 — logo, lead heading, and intro paragraph must share one left edge.
// Needs a live server WITH the ProcessWire backend (hero is CMS-fed), so this
// runs in CI / manual verify against a deployed build, not in the unit loop.
// Run: BASE_URL=https://www.bioco.ch npx playwright test alignment.spec.ts
const PAGES = ['/wir', '/abos']
const TOL = 2 // px

for (const route of PAGES) {
  test(`left edges align on ${route}`, async ({ page }) => {
    await page.goto(route)
    const logo = await page.locator('.primary-nav-logo, .secondary-nav-logo').first().boundingBox()
    const heading = await page.locator('h1, .hero-title, .cms-section-title h2').first().boundingBox()
    expect(logo, 'logo box').toBeTruthy()
    expect(heading, 'heading box').toBeTruthy()
    expect(Math.abs((logo!.x) - (heading!.x))).toBeLessThanOrEqual(TOL)
  })
}
