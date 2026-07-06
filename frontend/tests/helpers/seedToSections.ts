import { readFileSync } from 'node:fs'
import path from 'node:path'
import type { ContentButton, ContentSection, SectionConfigObject } from '@/lib/processwire-types'

// Seed section shape as stored in cms/content-seed/<slug>.json (see cms/content-seed/README.md).
export interface SeedSection {
  section_id?: string
  section_title?: string
  section_text?: string
  section_layout?: string
  section_theme?: string
  section_eyebrow?: string
  section_component?: string
  section_config?: SectionConfigObject | string
  image_url?: string
  image_alt?: string
  buttons?: ContentButton[]
}

export interface SeedFile {
  path: string
  slug: string
  template: string
  title: string
  seo?: { title?: string; description?: string }
  sections: SeedSection[]
  conversion_notes?: string[]
}

export function loadSeed(name: string): SeedFile {
  const seedPath = path.resolve(__dirname, '..', '..', '..', 'cms', 'content-seed', `${name}.json`)
  return JSON.parse(readFileSync(seedPath, 'utf8')) as SeedFile
}

/**
 * Mirrors buildSectionData() in site/templates/api.php (~line 526):
 *   section_id → id, section_title → title, section_text → text,
 *   section_layout → layout (default 'split_media_text'),
 *   section_theme → theme (default 'default'),
 *   section_eyebrow → eyebrow (only when set),
 *   section_component → component (only when set),
 *   section_config → config (JSON-parsed, only when non-empty),
 *   buttons → buttons (passthrough, only when non-empty).
 */
export function seedToSections(seed: SeedFile): ContentSection[] {
  return seed.sections.map((s, index) => {
    const section: ContentSection = {
      id: s.section_id || `section-${index}`,
      title: s.section_title || '',
      text: s.section_text || '',
      layout: s.section_layout || 'split_media_text',
      theme: s.section_theme || 'default',
    }
    if (s.section_eyebrow) {
      section.eyebrow = s.section_eyebrow
    }
    if (s.section_component) {
      section.component = s.section_component
    }
    const config: SectionConfigObject | null =
      typeof s.section_config === 'string'
        ? (s.section_config.trim() ? (JSON.parse(s.section_config) as SectionConfigObject) : null)
        : s.section_config || null
    if (config && Object.keys(config).length > 0) {
      section.config = config
    }
    if (s.buttons && s.buttons.length > 0) {
      section.buttons = s.buttons
    }
    return section
  })
}
