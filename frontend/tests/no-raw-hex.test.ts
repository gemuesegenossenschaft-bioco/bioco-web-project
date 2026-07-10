import { describe, expect, it } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import path from 'node:path'

// D13 (GH #87) — door-lock guard: no NEW raw hex color literals outside the
// :root design-token block in app/globals.css, and none in components/**/*.tsx.
// Route a color through an existing --color-*/--bg-*/etc. token instead; if
// none matches the exact value, add one next to the D13 block in globals.css
// (respecting the D1 no-duplicate gate: reuse a token if one already equals
// the literal).

const frontendRoot = path.resolve(__dirname, '..')
const read = (p: string) => readFileSync(path.resolve(frontendRoot, p), 'utf8')

const HEX_PATTERN = /#[0-9a-fA-F]{3,8}\b/g

// `var(--token, #hex)` fallback values (used for the rare case the custom
// property is unset, e.g. app/error.tsx, app/not-found.tsx, DepotMap.tsx)
// are documented fallbacks, not undocumented raw literals — strip them
// before scanning.
const VAR_FALLBACK_PATTERN = /var\(--[A-Za-z0-9_-]+,\s*#[0-9a-fA-F]{3,8}\s*\)/g
const CSS_COMMENT_PATTERN = /\/\*[\s\S]*?\*\//g

function findHexLiterals(source: string): string[] {
  const stripped = source.replace(VAR_FALLBACK_PATTERN, '').replace(CSS_COMMENT_PATTERN, '')
  return Array.from(stripped.matchAll(HEX_PATTERN)).map((m) => m[0])
}

// SVG glyph / illustration components legitimately hardcode swatch colors —
// they render fixed artwork, not themed UI surfaces, so they sit outside the
// design-token system entirely.
const EXCLUDED_BASENAMES = new Set([
  'Icon.tsx',
  'BiocoIcons.tsx',
  'BiocoIllustrations.tsx',
  'PersonIcons.tsx',
  'PlantIllustration.tsx',
  'PeaBullet.tsx',
])

// Pre-existing raw-hex literals outside D13's scope (GH #87 only tokenizes
// RegisteredSectionComponents.tsx, its factored-out SectionHeading.tsx
// block-heading styles, and InlineVisualEditorRuntime.tsx's dark panel).
// Catalogued here rather than silently ignored — same spirit as the D6
// deferred-magic-number catalog in globals.css — so a future slice can close
// these out. Keyed by exact literal(s) per file: any *new* or *additional*
// raw hex in these files still fails the guard.
const DEFERRED: Record<string, string[]> = {
  // Leaflet popup HTML built as a raw string (not a CSS-in-JS style object).
  'components/DepotMap.tsx': ['#2e7d32', '#2e7d32', '#ffffff'],
  // Single background swatch on the modal media frame.
  'components/ItemDetailModal.tsx': ['#000'],
  // Visual-editor "selected section" highlight styles, duplicated verbatim
  // in HomeClient.tsx and VisualEditorWrapper.tsx.
  'components/HomeClient.tsx': ['#fff', '#4a7c59'],
  'components/sections/VisualEditorWrapper.tsx': ['#fff', '#4a7c59'],
}

const sourceFileExtensions = new Set(['.tsx'])
const ignoredDirs = new Set(['.next', 'coverage', 'node_modules', 'playwright-report', 'test-results'])

function walkComponents(dir: string): string[] {
  return readdirSync(dir).flatMap((entry) => {
    const fullPath = path.join(dir, entry)
    const stat = statSync(fullPath)
    if (stat.isDirectory()) {
      return ignoredDirs.has(entry) ? [] : walkComponents(fullPath)
    }
    return sourceFileExtensions.has(path.extname(entry)) ? [fullPath] : []
  })
}

const componentFiles = walkComponents(path.resolve(frontendRoot, 'components'))

// Multiset-subtracts the DEFERRED literals for this file from what was
// actually found, so only *new* or *additional* hex literals are reported.
function unexpectedOffenders(relativePath: string, source: string): string[] {
  if (EXCLUDED_BASENAMES.has(path.basename(relativePath))) return []
  const remaining = [...(DEFERRED[relativePath] || [])]
  const unexpected: string[] = []
  for (const hex of findHexLiterals(source)) {
    const idx = remaining.indexOf(hex)
    if (idx >= 0) remaining.splice(idx, 1)
    else unexpected.push(hex)
  }
  return unexpected
}

// Finds the `:root { ... }` design-token block via depth-aware brace
// matching (robust to the block growing — no reliance on a pinned line
// number).
function splitOutRootBlock(css: string): { before: string; after: string } {
  const rootStart = css.indexOf(':root {')
  if (rootStart < 0) throw new Error('expected a :root { ... } token block in globals.css')
  let depth = 0
  let rootEnd = -1
  for (let i = rootStart; i < css.length; i++) {
    if (css[i] === '{') depth++
    else if (css[i] === '}') {
      depth--
      if (depth === 0) {
        rootEnd = i
        break
      }
    }
  }
  if (rootEnd < 0) throw new Error('expected to find the closing brace of :root { ... }')
  return { before: css.slice(0, rootStart), after: css.slice(rootEnd + 1) }
}

describe('no raw hex color literals outside design tokens (D13, GH #87)', () => {
  it('components/**/*.tsx have no new raw hex literals', () => {
    const offenders: string[] = []
    for (const file of componentFiles) {
      const relativePath = path.relative(frontendRoot, file).split(path.sep).join('/')
      const hits = unexpectedOffenders(relativePath, readFileSync(file, 'utf8'))
      if (hits.length > 0) offenders.push(`${relativePath}: ${hits.join(', ')}`)
    }
    expect(
      offenders,
      [
        'Raw hex color literals found outside the design-token system.',
        'Route each through an existing --color-*/--bg-*/etc. token in app/globals.css',
        '(add one next to the D13 block at the exact value if none matches — see the',
        'D1 no-duplicate gate), or, for a genuine pre-existing exception, add a',
        'value-keyed entry to DEFERRED in this test with a one-line reason:',
        ...offenders,
      ].join('\n')
    ).toEqual([])
  })

  it('app/globals.css has no raw hex literals outside the :root token block', () => {
    const { before, after } = splitOutRootBlock(read('app/globals.css'))
    const hits = [...findHexLiterals(before), ...findHexLiterals(after)]
    expect(hits, `Raw hex outside :root in app/globals.css: ${hits.join(', ')}`).toEqual([])
  })
})

describe('no-raw-hex detector fixture self-test', () => {
  it('catches a planted raw hex literal', () => {
    const fixture = `
      export function Widget() {
        return <div style={{ color: '#ff00aa' }} />
      }
    `
    expect(findHexLiterals(fixture)).toEqual(['#ff00aa'])
  })

  it('catches multiple planted hex literals in one line', () => {
    const fixture = `style="background: #123456; color: #abc;"`
    expect(findHexLiterals(fixture)).toEqual(['#123456', '#abc'])
  })

  it('does not flag a var(--token, #hex) fallback', () => {
    const fixture = `background: var(--color-carrot-logo, #F29200);`
    expect(findHexLiterals(fixture)).toEqual([])
  })

  it('does not flag hex inside a CSS comment', () => {
    const fixture = `/* legacy value was #123456 */\n.foo { color: red; }`
    expect(findHexLiterals(fixture)).toEqual([])
  })

  it('DEFERRED subtraction only clears exactly-catalogued literals, not extras', () => {
    const relativePath = 'components/ItemDetailModal.tsx'
    // A pretend rewrite that keeps the deferred '#000' but adds a brand-new
    // raw hex — the new one must still be reported.
    const fixtureWithExtra = `background: '#000'; border: '#ff00aa';`
    expect(unexpectedOffenders(relativePath, fixtureWithExtra)).toEqual(['#ff00aa'])
    // The exact deferred literal alone stays clean.
    expect(unexpectedOffenders(relativePath, `background: '#000';`)).toEqual([])
  })
})
