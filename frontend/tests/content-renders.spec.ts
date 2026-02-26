import { test, expect } from '@playwright/test'

/**
 * Content rendering tests
 * Verifies CMS content is served (no fallback images/text in DOM).
 * Run after CMS is populated: npx playwright test
 */

const FALLBACK_IMAGES = [
  '/images/FrontseiteStartseite.jpg',
  '/images/mitmachen/zusammen-arbeiten.JPG',
  '/images/gemeinsamSolidarischFrisch.JPG',
  '/images/ernte/bioco_ernte-kuerbis-hoch.JPG',
  '/images/team/alle-mitglieder-bioco.jpeg',
  '/images/team/betriebsgruppe.JPG',
  '/images/team/hofteam_matthias.JPG',
  '/images/team/bioco_hofteam_christian.JPG',
  '/images/DerHof1.jpg',
  '/images/DerHof2.JPG',
]

const PAGES = [
  { path: '/', name: 'Homepage' },
  { path: '/wir', name: 'Wir' },
  { path: '/mitmachen', name: 'Mitmachen' },
  { path: '/solawi', name: 'Solawi' },
  { path: '/gemuese', name: 'Gemuese' },
  { path: '/aktuelles', name: 'Aktuelles' },
]

for (const page of PAGES) {
  test(`${page.name}: no fallback image paths in DOM`, async ({ page: p }) => {
    await p.goto(page.path, { waitUntil: 'networkidle' })

    const html = await p.content()
    for (const fallbackImg of FALLBACK_IMAGES) {
      expect(html).not.toContain(fallbackImg)
    }
  })

  test(`${page.name}: has at least one CMS image`, async ({ page: p }) => {
    await p.goto(page.path, { waitUntil: 'networkidle' })

    // Look for images from CMS (site/assets/files/) or processed by Next.js (_next/image)
    const cmsImages = await p.locator('img').evaluateAll((imgs) =>
      imgs.filter((img) => {
        const src = img.getAttribute('src') || ''
        return src.includes('site/assets/files') || src.includes('_next/image') || src.includes('cms.bioco.ch')
      }).length
    )
    expect(cmsImages).toBeGreaterThan(0)
  })
}

test('Homepage: hero section has headline', async ({ page }) => {
  await page.goto('/', { waitUntil: 'networkidle' })
  const h1 = page.locator('h1').first()
  await expect(h1).toBeVisible()
  const text = await h1.textContent()
  expect(text?.length).toBeGreaterThan(5)
})

test('Aktuelles: three sections visible (news, schnuppertage, events)', async ({ page }) => {
  await page.goto('/aktuelles', { waitUntil: 'networkidle' })

  await expect(page.locator('#G-01')).toBeVisible()
  await expect(page.locator('#G-02')).toBeVisible()
  await expect(page.locator('#G-02b')).toBeVisible()
})
