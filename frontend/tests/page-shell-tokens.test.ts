import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import path from 'node:path'

// C.1 — page-shell tokens must be the single source of truth for the shell width.
const read = (p: string) => readFileSync(path.resolve(__dirname, '..', p), 'utf8')
const globals = read('app/globals.css')
const tokens = read('app/tokens.css')
const css = globals + '\n' + tokens

const countDefs = (name: string) =>
  (css.match(new RegExp(`--${name}\\s*:`, 'g')) || []).length

describe('page-shell tokens (C.1)', () => {
  it('defines --page-max-width exactly once', () => {
    expect(countDefs('page-max-width')).toBe(1)
  })

  it('defines --page-inline-padding exactly once', () => {
    expect(countDefs('page-inline-padding')).toBe(1)
  })

  it('uses the shell width literal 1400px only in the token definition', () => {
    // Allowed: the single `--page-max-width: 1400px;` definition.
    // Disallowed: every other `max-width: 1400px` magic number.
    const stray = (css.match(/1400px/g) || []).length
    const inTokenDef = (css.match(/--page-max-width\s*:\s*1400px/g) || []).length
    expect(stray - inTokenDef).toBe(0)
  })
})
