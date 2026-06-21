import { test, expect } from '@playwright/test'

// E.1 — every interactive element shows a visible focus indicator.
// Needs a live server; runs in CI / manual verify (no axe dep required).
// Run: BASE_URL=https://www.bioco.ch npx playwright test visual-polish-a11y.spec.ts
test('primary CTA has a visible focus indicator', async ({ page }) => {
  await page.goto('/')
  const cta = page.locator('a.btn, button.btn').first()
  await cta.focus()
  const styles = await cta.evaluate((el) => {
    const s = getComputedStyle(el)
    return { outline: s.outlineStyle, outlineWidth: s.outlineWidth, shadow: s.boxShadow }
  })
  const visible = (styles.outline !== 'none' && styles.outlineWidth !== '0px') || styles.shadow !== 'none'
  expect(visible, JSON.stringify(styles)).toBeTruthy()
})
