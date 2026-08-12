import { describe, expect, it } from 'vitest'
import fs from 'fs'
import path from 'path'

/**
 * Schema-Validierung der Content-Freeze-Seeds (cms/content-seed/*.json).
 *
 * Die Seeds sind die einzige Quelle für die ProcessWire-Migration
 * (site/templates/migrate-content-freeze.php). Dieser Test stellt sicher,
 * dass jede Seed-Datei das Schema aus cms/content-seed/README.md erfüllt und
 * mit den Grenzen kompatibel ist, die api.php beim Speichern anwendet
 * (sanitizeSectionConfigValue: Keys [a-z0-9_-], Strings max. 400 Zeichen,
 * keine Kontrollzeichen, Tiefe max. 6) — sonst wäre der gespeicherte Inhalt
 * nicht byte-identisch mit dem Seed.
 */

const SEED_DIR = path.resolve(__dirname, '../../cms/content-seed')
const REGISTRY_PATH = path.resolve(__dirname, '../../site/templates/component-registry.json')

const EXPECTED_SEED_COUNT = 19

// Muss dem PHP-Regex in migrate-content-freeze.php::validateSeed entsprechen.
const SECTION_ID_PATTERN = /^[a-z0-9][a-z0-9_-]*$/

// section_layout-Werte laut cms/content-seed/README.md (+ 'component' für
// registrierte Komponenten-Blöcke, vgl. optionLabelPlan der Migration).
const ALLOWED_LAYOUTS = new Set([
  'rich_text',
  'split_media_text',
  'split_text_media',
  'full_width_banner',
  'media_grid',
  'video_embed',
  'component',
])

const ALLOWED_THEMES = new Set(['default', 'muted', 'accent', 'dark'])
const ALLOWED_BUTTON_VARIANTS = new Set(['primary', 'secondary'])

interface SeedButton {
  text?: unknown
  href?: unknown
  variant?: unknown
}

interface SeedSection {
  section_id?: unknown
  section_title?: unknown
  section_eyebrow?: unknown
  section_text?: unknown
  section_layout?: unknown
  section_theme?: unknown
  section_component?: unknown
  section_config?: unknown
  image_url?: unknown
  image_alt?: unknown
  buttons?: unknown
}

interface Seed {
  path?: unknown
  slug?: unknown
  template?: unknown
  title?: unknown
  seo?: unknown
  hero?: unknown
  sections?: unknown
  conversion_notes?: unknown
}

function loadSeeds(): Array<{ file: string; seed: Seed }> {
  const files = fs
    .readdirSync(SEED_DIR)
    .filter((f) => f.endsWith('.json'))
    .sort()
  return files.map((file) => ({
    file,
    seed: JSON.parse(fs.readFileSync(path.join(SEED_DIR, file), 'utf8')) as Seed,
  }))
}

function loadRegistryKeys(): Set<string> {
  const registry = JSON.parse(fs.readFileSync(REGISTRY_PATH, 'utf8')) as Array<{
    key?: string
    aliases?: string[]
  }>
  const keys = new Set<string>()
  for (const entry of registry) {
    if (entry.key) keys.add(entry.key)
    for (const alias of entry.aliases ?? []) keys.add(alias)
  }
  return keys
}

/** Prüft section_config gegen die api.php-Sanitisierungsgrenzen (rekursiv). */
function collectConfigViolations(value: unknown, trail: string, depth = 0): string[] {
  const violations: string[] = []
  if (depth > 6) {
    violations.push(`${trail}: Tiefe > 6 (api.php sanitizeSectionConfigValue kappt darunter)`)
    return violations
  }
  if (typeof value === 'string') {
    if (value.length > 400) {
      violations.push(`${trail}: String länger als 400 Zeichen (${value.length}) — api.php würde kürzen`)
    }
    // eslint-disable-next-line no-control-regex
    if (/[\u0000-\u001F\u007F]/.test(value)) {
      violations.push(`${trail}: enthält Kontrollzeichen — api.php würde sie entfernen`)
    }
    return violations
  }
  if (Array.isArray(value)) {
    value.forEach((child, i) => {
      violations.push(...collectConfigViolations(child, `${trail}[${i}]`, depth + 1))
    })
    return violations
  }
  if (value !== null && typeof value === 'object') {
    for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
      if (!/^[a-z0-9_-]+$/i.test(key)) {
        violations.push(`${trail}.${key}: Key enthält Zeichen ausserhalb [a-z0-9_-] — api.php würde ihn umbenennen/verwerfen`)
      }
      violations.push(...collectConfigViolations(child, `${trail}.${key}`, depth + 1))
    }
    return violations
  }
  if (
    typeof value !== 'boolean' &&
    typeof value !== 'number' &&
    value !== null &&
    value !== undefined
  ) {
    violations.push(`${trail}: nicht JSON-serialisierbarer Wert (${typeof value})`)
  }
  return violations
}

const seeds = loadSeeds()
const registryKeys = loadRegistryKeys()

describe('content-seed schema (cms/content-seed/*.json)', () => {
  it(`contains exactly ${EXPECTED_SEED_COUNT} seed files`, () => {
    expect(seeds.map((s) => s.file)).toHaveLength(EXPECTED_SEED_COUNT)
  })

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s has all required top-level keys',
    (_file, seed) => {
      expect(typeof seed.path, 'path fehlt').toBe('string')
      expect(seed.path as string).toMatch(/^\/([a-z0-9-]+(\/[a-z0-9-]+)*\/)?$/)
      expect(typeof seed.slug, 'slug fehlt').toBe('string')
      expect(seed.slug as string).toMatch(/^[a-z0-9]+(-[a-z0-9]+)*$/)
      expect(typeof seed.template, 'template fehlt').toBe('string')
      expect((seed.template as string).length).toBeGreaterThan(0)
      expect(typeof seed.title, 'title fehlt').toBe('string')
      expect((seed.title as string).length).toBeGreaterThan(0)
      expect(Array.isArray(seed.sections), 'sections fehlt/kein Array').toBe(true)
      expect((seed.sections as SeedSection[]).length).toBeGreaterThan(0)
    },
  )

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s file name matches its slug',
    (file, seed) => {
      expect(file).toBe(`${seed.slug as string}.json`)
    },
  )

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s page name (last path segment) equals its slug so api.php sections/{slug} resolves',
    (_file, seed) => {
      // api.php resolves sections/{slug} via /content/{slug}/ or name={slug};
      // the migration names the page after the last path segment, so the two
      // must agree or getPageSections(slug) 404s in production. Homepage ('/')
      // resolves via the dedicated homepage endpoint instead.
      const path = seed.path as string
      if (path === '/') return
      const lastSegment = path.replace(/\/$/, '').split('/').pop()
      expect(lastSegment).toBe(seed.slug as string)
    },
  )

  it('has a unique slug and a unique path across all seeds', () => {
    const slugs = seeds.map(({ seed }) => seed.slug as string)
    const paths = seeds.map(({ seed }) => seed.path as string)
    expect(new Set(slugs).size).toBe(seeds.length)
    expect(new Set(paths).size).toBe(seeds.length)
  })

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s has unique, valid section_ids',
    (_file, seed) => {
      const ids = (seed.sections as SeedSection[]).map((s) => s.section_id)
      for (const id of ids) {
        expect(typeof id).toBe('string')
        expect(id as string).toMatch(SECTION_ID_PATTERN)
      }
      expect(new Set(ids).size).toBe(ids.length)
    },
  )

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s sections use only known layouts, themes and string fields',
    (_file, seed) => {
      for (const section of seed.sections as SeedSection[]) {
        const sid = String(section.section_id)
        for (const key of [
          'section_title',
          'section_eyebrow',
          'section_text',
          'section_component',
          'image_url',
          'image_alt',
        ] as const) {
          if (section[key] !== undefined) {
            expect(typeof section[key], `${sid}.${key} muss String sein`).toBe('string')
          }
        }
        if (section.section_layout !== undefined) {
          expect(
            ALLOWED_LAYOUTS.has(section.section_layout as string),
            `${sid}: unbekanntes section_layout '${String(section.section_layout)}'`,
          ).toBe(true)
        }
        if (section.section_theme !== undefined) {
          expect(
            ALLOWED_THEMES.has(section.section_theme as string),
            `${sid}: unbekanntes section_theme '${String(section.section_theme)}'`,
          ).toBe(true)
        }
      }
    },
  )

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s section_config is a valid, api.php-safe JSON object',
    (_file, seed) => {
      for (const section of seed.sections as SeedSection[]) {
        if (section.section_config === undefined) continue
        const sid = String(section.section_id)
        expect(
          section.section_config !== null &&
            typeof section.section_config === 'object' &&
            !Array.isArray(section.section_config),
          `${sid}: section_config muss ein JSON-Objekt sein`,
        ).toBe(true)
        // Muss verlustfrei re-serialisierbar sein (wird als JSON-String gespeichert)
        expect(() => JSON.stringify(section.section_config)).not.toThrow()
        const violations = collectConfigViolations(section.section_config, `${sid}.section_config`)
        expect(violations, violations.join('\n')).toEqual([])
      }
    },
  )

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s references only registered section_components',
    (_file, seed) => {
      for (const section of seed.sections as SeedSection[]) {
        const component = section.section_component
        if (component === undefined || component === '') continue
        expect(
          registryKeys.has(component as string),
          `${String(section.section_id)}: Komponente '${String(component)}' fehlt in site/templates/component-registry.json`,
        ).toBe(true)
      }
    },
  )

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s buttons have the api.php shape (max 2, text/href/variant)',
    (_file, seed) => {
      for (const section of seed.sections as SeedSection[]) {
        if (section.buttons === undefined) continue
        const sid = String(section.section_id)
        expect(Array.isArray(section.buttons), `${sid}: buttons muss ein Array sein`).toBe(true)
        const buttons = section.buttons as SeedButton[]
        expect(buttons.length, `${sid}: api.php kennt nur button_* und button2_*`).toBeLessThanOrEqual(2)
        for (const button of buttons) {
          expect(typeof button.text, `${sid}: button.text fehlt`).toBe('string')
          expect((button.text as string).length).toBeGreaterThan(0)
          expect(typeof button.href, `${sid}: button.href fehlt`).toBe('string')
          expect((button.href as string).length).toBeGreaterThan(0)
          expect(
            ALLOWED_BUTTON_VARIANTS.has(button.variant as string),
            `${sid}: button.variant muss primary|secondary sein`,
          ).toBe(true)
        }
      }
    },
  )

  it.each(seeds.map(({ file, seed }) => [file, seed] as const))(
    '%s seo/hero blocks, when present, are well-formed',
    (_file, seed) => {
      if (seed.seo !== undefined) {
        const seo = seed.seo as Record<string, unknown>
        expect(typeof seo.title).toBe('string')
        expect((seo.title as string).length).toBeGreaterThan(0)
        expect(typeof seo.description).toBe('string')
        expect((seo.description as string).length).toBeGreaterThan(0)
      }
      if (seed.hero !== undefined) {
        const hero = seed.hero as Record<string, unknown>
        for (const key of Object.keys(hero)) {
          expect(['hero_title', 'hero_subtitle', 'image_alt']).toContain(key)
          expect(typeof hero[key]).toBe('string')
        }
      }
    },
  )
})
