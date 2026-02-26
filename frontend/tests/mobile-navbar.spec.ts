import { test, expect } from '@playwright/test'

const MOBILE_VIEWPORT = { width: 390, height: 844 }

async function assertLogoHamburgerAligned(page: import('@playwright/test').Page) {
  const logo = page.locator('.mobile-nav-shell .primary-nav-logo').first()
  const hamburger = page.locator('.mobile-nav-shell .mobile-menu-toggle').first()

  const [logoBox, hamburgerBox] = await Promise.all([logo.boundingBox(), hamburger.boundingBox()])
  expect(logoBox).not.toBeNull()
  expect(hamburgerBox).not.toBeNull()

  const logoCenterY = logoBox!.y + logoBox!.height / 2
  const hamburgerCenterY = hamburgerBox!.y + hamburgerBox!.height / 2
  expect(Math.abs(logoCenterY - hamburgerCenterY)).toBeLessThanOrEqual(2)
}

test('mobile /wir navbar sticky with divider and aligned controls', async ({ page }) => {
  await page.setViewportSize(MOBILE_VIEWPORT)
  await page.goto('/wir', { waitUntil: 'networkidle' })

  const shell = page.locator('.mobile-nav-shell').first()
  const nav = page.locator('.mobile-nav-shell .primary-nav').first()
  await expect(shell).toBeVisible()
  await expect(nav).toBeVisible()

  const shellPosition = await shell.evaluate((el) => getComputedStyle(el).position)
  expect(['sticky', 'fixed']).toContain(shellPosition)

  const navBorderBottom = await nav.evaluate((el) => getComputedStyle(el).borderBottomWidth)
  expect(navBorderBottom).not.toBe('0px')

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
  await page.waitForTimeout(150)
  await expect(shell).toBeVisible()
  await assertLogoHamburgerAligned(page)
})

test('mobile homepage navbar is white on load and remains visible on scroll', async ({ page }) => {
  await page.setViewportSize(MOBILE_VIEWPORT)
  await page.goto('/', { waitUntil: 'networkidle' })

  const shell = page.locator('.mobile-nav-shell').first()
  const nav = page.locator('.mobile-nav-shell .primary-nav').first()
  await expect(shell).toBeVisible()
  await expect(nav).toBeVisible()

  const navBackground = await nav.evaluate((el) => getComputedStyle(el).backgroundColor)
  expect(navBackground).not.toBe('rgba(0, 0, 0, 0)')

  const navBorderBottom = await nav.evaluate((el) => getComputedStyle(el).borderBottomWidth)
  expect(navBorderBottom).not.toBe('0px')

  await page.evaluate(() => window.scrollTo(0, 1200))
  await page.waitForTimeout(150)
  await expect(shell).toBeVisible()
  await assertLogoHamburgerAligned(page)
})
