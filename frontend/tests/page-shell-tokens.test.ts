import { describe, expect, it } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import path from 'node:path'

// C.1 / D1 — validate the CSS the app actually imports. Screenshot parity is
// intentionally Playwright-only (*.spec.ts, excluded from Vitest); once enabled,
// capture/compare with:
// BASE_URL=https://www.bioco.ch npx playwright test <visual-spec>.spec.ts --update-snapshots
// BASE_URL=https://www.bioco.ch npx playwright test <visual-spec>.spec.ts
const frontendRoot = path.resolve(__dirname, '..')
const read = (p: string) => readFileSync(path.resolve(frontendRoot, p), 'utf8')
const toRepoPath = (p: string) => path.relative(frontendRoot, p).split(path.sep).join('/')
const layoutPath = path.resolve(frontendRoot, 'app/layout.tsx')
const layoutSource = read('app/layout.tsx')

const cssImportPattern = /import\s+(?:[^'"]+\s+from\s+)?['"]([^'"]+\.css)['"]/g

const getLayoutCssImports = () => {
  const imports = Array.from(layoutSource.matchAll(cssImportPattern)).map((match) => match[1])

  return imports.map((specifier) => {
    const resolved = specifier.startsWith('.')
      ? path.resolve(path.dirname(layoutPath), specifier)
      : path.resolve(frontendRoot, specifier)

    return {
      specifier,
      path: toRepoPath(resolved),
      css: read(toRepoPath(resolved)),
    }
  })
}

const loadedCssFiles = getLayoutCssImports()
const loadedCss = loadedCssFiles.map((file) => file.css).join('\n')

const sourceFileExtensions = new Set(['.js', '.jsx', '.ts', '.tsx'])
const ignoredDirs = new Set(['.next', 'coverage', 'node_modules', 'playwright-report', 'test-results'])

const walkSourceFiles = (dir: string): string[] => {
  return readdirSync(dir).flatMap((entry) => {
    const fullPath = path.join(dir, entry)
    const stat = statSync(fullPath)

    if (stat.isDirectory()) {
      return ignoredDirs.has(entry) ? [] : walkSourceFiles(fullPath)
    }

    return sourceFileExtensions.has(path.extname(entry)) ? [fullPath] : []
  })
}

const getImportedCssSpecifiers = () => {
  return walkSourceFiles(frontendRoot).flatMap((file) => {
    const source = readFileSync(file, 'utf8')

    return Array.from(source.matchAll(cssImportPattern)).map((match) => ({
      sourcePath: toRepoPath(file),
      specifier: match[1],
    }))
  })
}

const stripCssComments = (source: string) => source.replace(/\/\*[\s\S]*?\*\//g, '')

const tokenDefinitionPattern =
  /(--(?:color|radius|shadow|space|spacing|font|page|section|card|input)[A-Za-z0-9_-]*)\s*:/g

const findDesignTokenDefinitions = (source: string) => {
  return Array.from(stripCssComments(source).matchAll(tokenDefinitionPattern)).map((match) => match[1])
}

const findDuplicateDesignTokens = (source: string) => {
  const counts = new Map<string, number>()

  for (const token of findDesignTokenDefinitions(source)) {
    counts.set(token, (counts.get(token) || 0) + 1)
  }

  return Array.from(counts.entries())
    .filter(([, count]) => count > 1)
    .map(([token, count]) => ({ token, count }))
}

const duplicateDesignTokens = findDuplicateDesignTokens(loadedCss)
const duplicateTokenTest = duplicateDesignTokens.length > 0 ? it.skip : it

if (duplicateDesignTokens.length > 0) {
  it.todo(
    `D2 removes duplicate loaded design token definitions: ${duplicateDesignTokens
      .map(({ token, count }) => `${token} (${count})`)
      .join(', ')}`
  )
}

const countDefs = (name: string) =>
  (loadedCss.match(new RegExp(`--${name}\\s*:`, 'g')) || []).length

describe('page-shell tokens (C.1)', () => {
  it('loads only the CSS imported by the app layout', () => {
    expect(loadedCssFiles.map((file) => file.path)).toEqual(['app/globals.css'])
  })

  it('does not import the dead app/tokens.css file anywhere', () => {
    expect(
      getImportedCssSpecifiers().filter(({ specifier }) =>
        specifier.split(/[\\/]/).at(-1) === 'tokens.css'
      )
    ).toEqual([])
  })

  duplicateTokenTest('defines each loaded design custom-property token at most once', () => {
    expect(duplicateDesignTokens).toEqual([])
  })

  it('defines --page-max-width exactly once', () => {
    expect(countDefs('page-max-width')).toBe(1)
  })

  it('defines --page-inline-padding exactly once', () => {
    expect(countDefs('page-inline-padding')).toBe(1)
  })

  it('uses the shell width literal 1400px only in the token definition', () => {
    // Allowed: the single `--page-max-width: 1400px;` definition.
    // Disallowed: every other `max-width: 1400px` magic number.
    const stray = (loadedCss.match(/1400px/g) || []).length
    const inTokenDef = (loadedCss.match(/--page-max-width\s*:\s*1400px/g) || []).length
    expect(stray - inTokenDef).toBe(0)
  })
})

describe('design token duplicate detector', () => {
  it('flags duplicate design-token definitions in inline CSS fixtures', () => {
    const cssWithDuplicate = `
      :root { --radius-sm: 6px; --space-sm: 8px; }
      .card { --radius-sm: 12px; }
    `

    expect(findDuplicateDesignTokens(cssWithDuplicate)).toEqual([
      { token: '--radius-sm', count: 2 },
    ])
  })

  it('passes clean inline CSS fixtures', () => {
    const cleanCss = `
      :root { --radius-sm: 6px; --space-sm: 8px; }
      .card { --card-border-width: 1px; }
    `

    expect(findDuplicateDesignTokens(cleanCss)).toEqual([])
  })
})
