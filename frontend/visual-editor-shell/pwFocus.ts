/**
 * ProcessWire deep-link building for the "→ In PW öffnen" affordances.
 * Focus-field resolution comes from the shared
 * site/templates/visual-editor-focus-fields.json map (passed through the
 * shell config); the produced URL matches what admin.js's focused edit mode
 * expects (veFocus/veFields/veReturn query contract).
 */

import type { ShellFocusFieldsConfig } from './config'

export interface PwFocusSectionLike {
  id: string
  pwId?: number
  layout?: string
  component?: string
}

export interface PwFocusRequest {
  field?: string
  kind?: string
  buttonIndex?: number
  targetField?: string
}

export interface PwFocusComponentMeta {
  key: string
  cmsFields?: string[]
}

export type ResolveComponentMeta = (rawKey?: string | null) => PwFocusComponentMeta | null

export function isHeroLike(section: PwFocusSectionLike): boolean {
  return section.id === '__hero__' || section.layout === 'hero'
}

function unique(items: readonly (string | undefined | null)[]): string[] {
  const out: string[] = []
  for (const item of items) {
    const value = String(item || '').trim()
    if (value && !out.includes(value)) out.push(value)
  }
  return out
}

export function getProcessWireFocusFields(
  section: PwFocusSectionLike | null,
  request: PwFocusRequest,
  focusFields: ShellFocusFieldsConfig,
  resolveComponent: ResolveComponentMeta
): string[] {
  if (!section) return []
  const field = String(request.field || '').trim()

  if (isHeroLike(section)) {
    if (!field) return unique(focusFields.heroBaseFields)
    if (field === 'media') return unique(focusFields.heroFieldMappings.media || focusFields.heroBaseFields)
    return unique(focusFields.heroFieldMappings[field] || focusFields.heroBaseFields)
  }

  if (!field) {
    const meta = resolveComponent(section.component)
    if (meta?.cmsFields?.length) return unique(meta.cmsFields)
    return unique(focusFields.sectionBaseFields)
  }

  if (field === 'button') {
    const index = String(request.buttonIndex != null ? request.buttonIndex : 0)
    return unique(focusFields.buttonFieldMappings[index] || focusFields.buttonFieldMappings['0'] || [])
  }

  if (field === 'media') {
    return unique([request.targetField || 'section_image', 'image_alt'])
  }

  return unique(focusFields.fieldMappings[field] || focusFields.sectionBaseFields)
}

export function buildVisualEditorReturnUrl(visualEditorUrl: string, pageId: number, path: string): string {
  const params: string[] = []
  if (pageId) params.push(`pageId=${encodeURIComponent(String(pageId))}`)
  if (path) params.push(`path=${encodeURIComponent(path)}`)
  return visualEditorUrl + (params.length ? `?${params.join('&')}` : '')
}

export type PwFocusError = 'missing_target' | 'publish_first' | 'missing_fields'

export interface BuildPwFocusUrlOptions {
  pageEditUrl: string
  visualEditorUrl: string
  pageId: number
  path: string
  section: PwFocusSectionLike | null
  request: PwFocusRequest
  focusFields: ShellFocusFieldsConfig
  resolveComponent: ResolveComponentMeta
}

export function buildProcessWireFocusUrl(
  options: BuildPwFocusUrlOptions
): { url: string } | { error: PwFocusError } {
  const { section, request } = options
  if (!options.pageId || !section) return { error: 'missing_target' }
  if (!isHeroLike(section) && !section.pwId) return { error: 'publish_first' }

  const fields = getProcessWireFocusFields(section, request, options.focusFields, options.resolveComponent)
  if (!fields.length) return { error: 'missing_fields' }

  const params = [
    `id=${encodeURIComponent(String(options.pageId))}`,
    'veFocus=1',
    `vePageId=${encodeURIComponent(String(options.pageId))}`,
    `vePath=${encodeURIComponent(options.path || '')}`,
    `veSectionId=${encodeURIComponent(section.id || '')}`,
    `veFields=${encodeURIComponent(fields.join(','))}`,
    `veReturn=${encodeURIComponent(buildVisualEditorReturnUrl(options.visualEditorUrl, options.pageId, options.path || ''))}`,
  ]

  if (!isHeroLike(section) && section.pwId) {
    params.push(`veSectionPwId=${encodeURIComponent(String(section.pwId))}`)
  }
  if (request.field) params.push(`veField=${encodeURIComponent(request.field)}`)
  if (request.kind) params.push(`veKind=${encodeURIComponent(request.kind)}`)
  if (request.buttonIndex != null) params.push(`veButtonIndex=${encodeURIComponent(String(request.buttonIndex))}`)
  if (request.targetField) params.push(`veTargetField=${encodeURIComponent(request.targetField)}`)
  if (section.component) params.push(`veComponent=${encodeURIComponent(section.component)}`)

  return { url: `${options.pageEditUrl}?${params.join('&')}` }
}
