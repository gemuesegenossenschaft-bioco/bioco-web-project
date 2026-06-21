import { describe, expect, it } from 'vitest'
import { readdirSync } from 'node:fs'
import path from 'node:path'
import { AUDIT, classifyRoute } from '@/lib/editabilityAudit'

// B.1 — every route is classified cms | hardcoded, and the audit map stays
// complete: adding a new page.tsx without classifying it fails this test.
function routeFiles(dir: string, base = 'app'): string[] {
  const out: string[] = []
  for (const e of readdirSync(path.resolve(__dirname, '..', dir), { withFileTypes: true })) {
    if (e.isDirectory()) out.push(...routeFiles(path.join(dir, e.name), base))
    else if (e.name === 'page.tsx') {
      const route = '/' + path.relative(base, dir).split(path.sep).join('/')
      out.push(route === '/' ? '/' : route.replace(/\/$/, ''))
    }
  }
  return out
}
const routes = routeFiles('app')

describe('editability audit (B.1)', () => {
  it('classifies known routes', () => {
    expect(classifyRoute('/abos')).toBe('cms')
    expect(classifyRoute('/(cms)/[...slug]')).toBe('cms')
    expect(classifyRoute('/wir')).toBe('hardcoded')
    expect(classifyRoute('/impressum')).toBe('hardcoded')
  })

  it('throws on an unknown route', () => {
    expect(() => classifyRoute('/does-not-exist')).toThrow()
  })

  it('audit map covers every page.tsx route (completeness)', () => {
    const missing = routes.filter((r) => !(r in AUDIT))
    expect(missing, `unclassified routes: ${missing.join(', ')}`).toEqual([])
  })

  it('every entry has a change path (inline | pw | ticket)', () => {
    for (const [route, entry] of Object.entries(AUDIT)) {
      expect(['cms', 'hardcoded'], route).toContain(entry.status)
      expect(entry.changePath, route).toMatch(/inline|pw|ticket/)
    }
  })
})
