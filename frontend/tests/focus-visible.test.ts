import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import path from 'node:path'

// E.1 — keyboard focus must be visible. Deterministic CSS-contract; the
// in-browser axe/focus check lives in tests/visual-polish-a11y.spec.ts.
const css = readFileSync(path.resolve(__dirname, '..', 'app/globals.css'), 'utf8')

// collect the body of every :focus-visible rule
function focusVisibleRules(): string {
  const rules: string[] = []
  const re = /:focus-visible[^{]*\{([^}]*)\}/g
  let m: RegExpExecArray | null
  while ((m = re.exec(css))) rules.push(m[0])
  return rules.join('\n')
}

describe('focus-visible a11y (E.1)', () => {
  const rules = focusVisibleRules()

  it('defines at least one :focus-visible rule', () => {
    expect(rules.length).toBeGreaterThan(0)
  })

  it('provides a visible indicator (outline or box-shadow), not outline:none', () => {
    expect(rules).toMatch(/outline:\s*(?!none)[^;]+|box-shadow:\s*[^;]+/)
    expect(rules).not.toMatch(/outline:\s*none/)
  })

  it('covers core interactive elements (links, buttons, .btn)', () => {
    // the union of all :focus-visible selectors must mention these
    const selectors = (css.match(/[^{};]*:focus-visible/g) || []).join(' ')
    expect(selectors).toMatch(/\ba\b|\.btn|button/)
  })
})
