import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import path from 'node:path'

// C.4 — /abos must share the exact same page shell as every other CMS page:
// SectionRenderer's `.cms-sections` container IS the shell (token width +
// token inline padding). A page that wraps SectionRenderer in its own
// width/padding container double-insets its content and breaks the shared
// left edge with the logo.
const read = (p: string) => readFileSync(path.resolve(__dirname, '..', p), 'utf8')

describe('/abos shares the page shell (C.4)', () => {
  it('cms-sections consumes the inline-padding token', () => {
    const css = read('app/globals.css')
    const i = css.indexOf('.cms-sections {')
    expect(i, '.cms-sections rule not found').toBeGreaterThan(-1)
    const block = css.slice(css.indexOf('{', i) + 1, css.indexOf('}', i))
    expect(block).toMatch(/max-width:\s*var\(--page-max-width\)/)
    expect(block).toMatch(/var\(--page-inline-padding\)/)
  })

  it('abos page adds no second width/padding shell around SectionRenderer', () => {
    const page = read('app/abos/page.tsx')
    expect(page).not.toMatch(/maxWidth/)
    expect(page).not.toMatch(/1400px/)
    expect(page).not.toMatch(/padding.*clamp/)
  })
})
