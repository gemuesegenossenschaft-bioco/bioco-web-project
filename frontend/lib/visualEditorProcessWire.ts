import focusFieldConfig from '../../site/templates/visual-editor-focus-fields.json'
import { resolveComponentRegistryEntry } from '@/lib/componentRegistry'
import type { ContentSection } from '@/lib/processwire-types'

export interface VisualEditorFocusSelection {
  field?: string | null
  buttonIndex?: number
  targetField?: string | null
}

interface FocusFieldConfig {
  heroBaseFields: string[]
  sectionBaseFields: string[]
  fieldMappings: Record<string, string[]>
  heroFieldMappings: Record<string, string[]>
  buttonFieldMappings: Record<string, string[]>
}

const config = focusFieldConfig as FocusFieldConfig

function uniqueFields(fields: Array<string | null | undefined>): string[] {
  const seen = new Set<string>()
  const next: string[] = []
  for (const field of fields) {
    const value = String(field || '').trim()
    if (!value || seen.has(value)) continue
    seen.add(value)
    next.push(value)
  }
  return next
}

function isHeroSection(section?: ContentSection | null): boolean {
  return !!section && (section.id === '__hero__' || section.layout === 'hero')
}

function getComponentCmsFields(section?: ContentSection | null): string[] {
  const resolved = resolveComponentRegistryEntry(section?.component || '')
  return resolved?.entry.cmsFields || []
}

export function getProcessWireFocusFields(
  section?: ContentSection | null,
  selection?: VisualEditorFocusSelection | null,
): string[] {
  if (!section) return []

  if (isHeroSection(section)) {
    if (!selection?.field) return uniqueFields(config.heroBaseFields)
    if (selection.field === 'media') return uniqueFields(config.heroFieldMappings.media)
    return uniqueFields(config.heroFieldMappings[selection.field] || config.heroBaseFields)
  }

  if (!selection?.field) {
    const componentFields = getComponentCmsFields(section)
    return uniqueFields(componentFields.length ? componentFields : config.sectionBaseFields)
  }

  if (selection.field === 'button') {
    return uniqueFields(config.buttonFieldMappings[String(selection.buttonIndex ?? 0)] || config.buttonFieldMappings['0'])
  }

  if (selection.field === 'media') {
    return uniqueFields([selection.targetField || 'section_image', 'image_alt'])
  }

  return uniqueFields(config.fieldMappings[selection.field] || config.sectionBaseFields)
}

export function serializeProcessWireFocusFields(fields: string[]): string {
  return uniqueFields(fields).join(',')
}
