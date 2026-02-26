import { test, expect } from '@playwright/test'

// Slice 1: Favicon / icon
test('icon.png returns 200', async ({ request }) => {
  const res = await request.get('/icon.png')
  expect(res.status()).toBe(200)
  expect(res.headers()['content-type']).toContain('image')
})

test('favicon.ico returns 200', async ({ request }) => {
  const res = await request.get('/favicon.ico')
  expect(res.status()).toBe(200)
})

// Slice 2: Custom error page
test('invalid route shows custom error page with home link', async ({ page }) => {
  await page.goto('/this-page-does-not-exist-12345', { waitUntil: 'networkidle' })
  // Should show custom 404 (not-found.tsx), not default Next.js error
  await expect(page.locator('text=404')).toBeVisible()
  // Should have a link back to homepage
  const homeLink = page.locator('a[href="/"]')
  await expect(homeLink).toBeVisible()
})

// Slice 3: CMS API rejects unknown origins
test('CMS API does not allow arbitrary origins', async ({ request }) => {
  const res = await request.fetch('/cms/api/pages', {
    headers: { Origin: 'https://evil.com' },
  })
  const corsHeader = res.headers()['access-control-allow-origin']
  // Should NOT be wildcard or the evil origin
  expect(corsHeader).not.toBe('*')
  expect(corsHeader).not.toBe('https://evil.com')
})

// Slice 4: Debug mode off (no PHP traces in error responses)
test('API error does not expose PHP file paths', async ({ request }) => {
  const res = await request.get('/cms/api/nonexistent-endpoint-xyz')
  const body = await res.text()
  // Should not contain PHP stack traces or file paths
  expect(body).not.toContain('/home/bioco/')
  expect(body).not.toContain('Stack trace')
  expect(body).not.toContain('.php on line')
})

// Slice 6: Migration scripts deleted
const migrationPaths = [
  '/cms/api-setup.php',
  '/cms/migrate.php',
]

for (const path of migrationPaths) {
  test(`migration script ${path} returns 404`, async ({ request }) => {
    const res = await request.get(path)
    expect(res.status()).toBe(404)
  })
}
