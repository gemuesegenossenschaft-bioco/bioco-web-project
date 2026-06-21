import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import path from 'node:path'

// C.2 — hero and nav must share the SAME shell tokens so the hero text left
// edge lines up with the logo. Deterministic CSS-contract test (the pixel-level
// check lives in tests/alignment.spec.ts, which needs a live server + CMS).
const css = readFileSync(path.resolve(__dirname, '..', 'app/globals.css'), 'utf8')

// grab the declaration block for a selector group (first match)
function block(selector: string): string {
  const i = css.indexOf(selector)
  expect(i, `selector ${selector} not found`).toBeGreaterThan(-1)
  const open = css.indexOf('{', i)
  const close = css.indexOf('}', open)
  return css.slice(open + 1, close)
}

const nav = block('.primary-nav-container')
const hero = block('.hero-container')

describe('hero shares the page shell (C.2)', () => {
  it('nav container consumes both shell tokens', () => {
    expect(nav).toMatch(/max-width:\s*var\(--page-max-width\)/)
    expect(nav).toMatch(/var\(--page-inline-padding\)/)
  })

  it('hero container consumes both shell tokens', () => {
    expect(hero).toMatch(/max-width:\s*var\(--page-max-width\)/)
    expect(hero).toMatch(/var\(--page-inline-padding\)/)
  })

  it('inline-padding token equals the canonical nav padding', () => {
    expect(css).toMatch(/--page-inline-padding:\s*clamp\(16px,\s*6vw,\s*96px\)/)
  })
})
