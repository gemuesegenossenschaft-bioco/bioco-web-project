/**
 * Draft model of the shell. Field-change semantics are delegated to the
 * shared frontend/lib/visualEditorContract.ts (the same module the iframe
 * runtime uses), killing the old PHP-side duplicated switch. On top of the
 * contract this module owns:
 *
 * - draft-media bookkeeping (MediaLibrary picks pending until publish)
 * - dirty tracking (per-section + order)
 * - THE single draft persistence layer.
 *
 * Persistence note (G.2): the old shell had three draft layers —
 *   1. sessionStorage autosave ('bioco-ve-draft:v1:*'),
 *   2. a server-side draft (GET/POST/DELETE /api/content/draft) re-synced on
 *      every autosave and page switch,
 *   3. localStorage "last page" memory ('bioco-ve:last-page:v1').
 * They are replaced by layer 1 alone: sessionStorage, fingerprint-gated.
 * The server draft added cross-device recovery nobody used and doubled every
 * autosave into two network writes; the last-page memory is superseded by
 * iframe-driven navigation plus the ?path= boot parameter (veReturn links).
 */

import {
  applyVisualEditorFieldChange,
  normalizeVisualEditorSection,
  type VisualEditorFieldChange,
} from '../lib/visualEditorContract'
import type { ContentMedia, ContentSection } from '../lib/processwire-types'
import { STRINGS } from './strings'

export const HERO_SECTION_ID = '__hero__'
const DRAFT_STORAGE_PREFIX = 'bioco-ve-draft:v1:'

export interface DraftMediaRef {
  assetId: number
  fileField: string
  fileName: string
  targetField: string
  url: string
  assetTitle: string
}

export type ShellSection = ContentSection & {
  mediaItems?: ContentMedia[]
  draftMedia?: DraftMediaRef
  draftMediaItems?: DraftMediaRef[]
}

export type ShellFieldChange = VisualEditorFieldChange

export interface DraftMediaFile {
  assetId: number
  assetTitle?: string
  fileField: string
  fileName: string
  url: string
}

type UnknownRecord = Record<string, unknown>

function clone<T>(value: T): T {
  if (value == null) return value
  return JSON.parse(JSON.stringify(value)) as T
}

export function isHeroSection(sectionOrId: ShellSection | ContentSection | string | null | undefined): boolean {
  if (!sectionOrId) return false
  if (typeof sectionOrId === 'string') return sectionOrId === HERO_SECTION_ID
  return sectionOrId.id === HERO_SECTION_ID || sectionOrId.layout === 'hero'
}

function normalizeDraftMediaRef(raw: unknown, fallbackTargetField: string): DraftMediaRef | null {
  const ref = raw as Partial<DraftMediaRef> | null | undefined
  if (!ref || !ref.assetId || !ref.fileField || !ref.fileName || !ref.url) return null
  return {
    assetId: Number(ref.assetId),
    fileField: String(ref.fileField),
    fileName: String(ref.fileName),
    targetField: String(ref.targetField || fallbackTargetField),
    url: String(ref.url),
    assetTitle: ref.assetTitle ? String(ref.assetTitle) : '',
  }
}

/**
 * Contract normalization + draft-media validation. Returns null for
 * sections without an id (matches the old normalizeDraftSection).
 */
export function normalizeShellSection(raw: unknown): ShellSection | null {
  const record = raw as (Partial<ContentSection> & UnknownRecord) | null | undefined
  if (!record || !record.id) return null
  const normalized = normalizeVisualEditorSection(record) as ShellSection

  const fallbackTarget = isHeroSection(normalized) ? 'hero_image' : 'section_image'
  const draftMedia = normalizeDraftMediaRef(record.draftMedia, fallbackTarget)
  if (draftMedia) {
    normalized.draftMedia = draftMedia
  } else {
    delete normalized.draftMedia
  }

  if (Array.isArray(record.draftMediaItems)) {
    const items = record.draftMediaItems
      .map((item) => normalizeDraftMediaRef(item, 'section_images'))
      .filter((item): item is DraftMediaRef => item !== null)
    if (items.length) {
      normalized.draftMediaItems = items
    } else {
      delete normalized.draftMediaItems
    }
  } else {
    delete normalized.draftMediaItems
  }

  return normalized
}

export function cloneShellSections(list: readonly unknown[]): ShellSection[] {
  return (list || []).map(normalizeShellSection).filter((section): section is ShellSection => section !== null)
}

export function buildHomepageHeroSection(hero: UnknownRecord | null | undefined, pageId: number | null): ShellSection {
  const data = hero || {}
  return normalizeShellSection({
    id: HERO_SECTION_ID,
    pwId: pageId || undefined,
    title: String(data.headline || 'Hero'),
    eyebrow: String(data.subtitle || ''),
    image: String(data.image || ''),
    imageAlt: String(data.imageAlt || ''),
    layout: 'hero',
    theme: 'default',
  }) as ShellSection
}

/* ------------------------------------------------------------------ */
/* Dirty tracking                                                      */
/* ------------------------------------------------------------------ */

function comparableSection(section: ShellSection): UnknownRecord {
  return {
    id: section.id,
    pwId: section.pwId || null,
    title: section.title || '',
    text: section.text || '',
    layout: section.layout || 'rich_text',
    theme: section.theme || 'default',
    eyebrow: section.eyebrow || '',
    component: section.component || '',
    config: clone(section.config || {}),
    bgColor: section.bgColor || '',
    imageOverlay: section.imageOverlay || '',
    image: section.image || '',
    imageAlt: section.imageAlt || '',
    imageBrightness: section.imageBrightness == null ? null : section.imageBrightness,
    imageContrast: section.imageContrast == null ? null : section.imageContrast,
    imageSaturate: section.imageSaturate == null ? null : section.imageSaturate,
    video: clone(section.video || null),
    media: clone(section.media || []),
    mediaItems: clone(section.mediaItems || []),
    buttons: clone(section.buttons || []),
    draftMedia: clone(section.draftMedia || null),
    draftMediaItems: clone(section.draftMediaItems || []),
  }
}

function comparableJson(section: ShellSection): string {
  return JSON.stringify(comparableSection(section))
}

export function hasDraftChanges(current: readonly ShellSection[], canonical: readonly ShellSection[]): boolean {
  return (
    JSON.stringify(current.map(comparableSection)) !== JSON.stringify(canonical.map(comparableSection))
  )
}

export function computeDirtyIds(
  current: readonly ShellSection[],
  canonical: readonly ShellSection[]
): Record<string, true> {
  const dirty: Record<string, true> = {}
  const canonicalById: Record<string, string> = {}
  for (const section of canonical) canonicalById[section.id] = comparableJson(section)

  for (const section of current) {
    if (canonicalById[section.id] !== comparableJson(section)) dirty[section.id] = true
  }

  const canonicalOrder = canonical.map((section) => section.id).join('|')
  const currentOrder = current.map((section) => section.id).join('|')
  if (canonicalOrder !== currentOrder) {
    for (const section of current) dirty[section.id] = true
  }

  return dirty
}

/* ------------------------------------------------------------------ */
/* Field changes (shared contract) + iframe echo notifications         */
/* ------------------------------------------------------------------ */

export function applyShellFieldChange(section: ShellSection, change: ShellFieldChange): ShellSection {
  const next = applyVisualEditorFieldChange(section, change) as ShellSection

  if (change.field === 'mediaItems') {
    // Keep pending MediaLibrary refs only for URLs still present (multiset
    // semantics: duplicated URLs consume one ref each).
    const previousRefs = Array.isArray(section.draftMediaItems) ? section.draftMediaItems : []
    const refsByUrl: Record<string, DraftMediaRef[]> = {}
    for (const ref of previousRefs) {
      if (!ref || !ref.url) continue
      ;(refsByUrl[ref.url] = refsByUrl[ref.url] || []).push(ref)
    }
    next.draftMediaItems = (next.media || [])
      .map((item) => (refsByUrl[item.url]?.length ? refsByUrl[item.url].shift()! : null))
      .filter((ref): ref is DraftMediaRef => ref !== null)
  }

  return next
}

export interface SectionUpdateNotification {
  field: string
  value: unknown
}

/**
 * The parent->iframe `section-update` echoes the old shell emitted after a
 * field change; values are read from the post-change section.
 */
export function sectionUpdateNotifications(field: string, section: ShellSection): SectionUpdateNotification[] {
  switch (field) {
    case 'component':
      return [
        { field: 'component', value: section.component || '' },
        { field: 'config', value: section.config || {} },
      ]
    case 'config':
      return [{ field: 'config', value: section.config || {} }]
    case 'videoUrl':
      return [{ field: 'video', value: section.video || null }]
    case 'videoTitle':
      return [
        { field: 'video', value: section.video || null },
        { field: 'videoTitle', value: section.video?.title || '' },
      ]
    case 'mediaItems':
      return [
        { field: 'media', value: section.media || [] },
        { field: 'images', value: section.images || [] },
        { field: 'image', value: section.image || '' },
      ]
    case 'button_text':
    case 'button_href':
    case 'button_variant':
      return [{ field: 'buttons', value: section.buttons || [] }]
    default:
      return [{ field, value: (section as unknown as UnknownRecord)[field] }]
  }
}

/* ------------------------------------------------------------------ */
/* Draft media selection (Mediathek modal)                             */
/* ------------------------------------------------------------------ */

export function applyDraftMedia(section: ShellSection, file: DraftMediaFile, targetField: string): ShellSection {
  const ref: DraftMediaRef = {
    assetId: file.assetId,
    assetTitle: file.assetTitle || '',
    fileField: file.fileField,
    fileName: file.fileName,
    targetField,
    url: file.url,
  }
  const imageAlt = section.imageAlt || file.assetTitle || section.title || ''
  return {
    ...section,
    draftMedia: ref,
    draftMediaItems: [clone(ref)],
    image: file.url,
    imageAlt,
    imageData: { url: file.url, description: imageAlt },
    images: [{ url: file.url, alt: imageAlt }],
    media: [{ url: file.url, alt: imageAlt, type: 'image' }],
  }
}

export function appendDraftMedia(section: ShellSection, file: DraftMediaFile, targetField?: string): ShellSection {
  const ref: DraftMediaRef = {
    assetId: file.assetId,
    assetTitle: file.assetTitle || '',
    fileField: file.fileField,
    fileName: file.fileName,
    targetField: targetField || 'section_images',
    url: file.url,
  }
  const alt = file.assetTitle || section.imageAlt || section.title || ''
  const media = [...(section.media || []), { url: file.url, alt, type: 'image' as const }]
  const images = media.map((item) => ({ url: item.url, alt: item.alt || '' }))
  return {
    ...section,
    media,
    images,
    image: section.image || images[0]?.url || '',
    imageAlt: section.image ? section.imageAlt : section.imageAlt || images[0]?.alt || '',
    draftMediaItems: [...(section.draftMediaItems || []), ref],
  }
}

/* ------------------------------------------------------------------ */
/* THE single persistence layer (sessionStorage, fingerprint-gated)    */
/* ------------------------------------------------------------------ */

export interface DraftStorage {
  getItem(key: string): string | null
  setItem(key: string, value: string): void
  removeItem(key: string): void
}

export interface PersistedDraft {
  pageId: number
  path: string
  baseFingerprint: string
  sections: readonly ShellSection[]
  activeSectionId?: string | null
  activeField?: unknown
}

export function draftStorageKey(pageId: number, path: string): string {
  if (!pageId || !path) return ''
  return `${DRAFT_STORAGE_PREFIX}${pageId}:${path}`
}

export function persistDraft(storage: DraftStorage, draft: PersistedDraft): void {
  const key = draftStorageKey(draft.pageId, draft.path)
  if (!key) return
  try {
    storage.setItem(
      key,
      JSON.stringify({
        pageId: draft.pageId,
        path: draft.path,
        baseFingerprint: draft.baseFingerprint || '',
        savedAt: Date.now(),
        sections: cloneShellSections(draft.sections as unknown[]),
        activeSectionId: draft.activeSectionId ?? null,
        activeField: draft.activeField ?? null,
      })
    )
  } catch {
    // storage full/unavailable: the draft still lives in memory
  }
}

export function clearDraft(storage: DraftStorage, pageId: number, path: string): void {
  const key = draftStorageKey(pageId, path)
  if (!key) return
  try {
    storage.removeItem(key)
  } catch {
    // ignore storage errors
  }
}

export interface RestoreDraftResult {
  sections: ShellSection[]
  restored: boolean
  message: string
  activeSectionId: string | null
  activeField: unknown
}

export function restoreDraft(
  storage: DraftStorage,
  options: { pageId: number; path: string; sections: readonly ShellSection[]; fingerprint: string }
): RestoreDraftResult {
  const fallback: RestoreDraftResult = {
    sections: cloneShellSections(options.sections as unknown[]),
    restored: false,
    message: '',
    activeSectionId: null,
    activeField: null,
  }
  const key = draftStorageKey(options.pageId, options.path)
  if (!key) return fallback

  let stored: UnknownRecord | null = null
  try {
    const raw = storage.getItem(key)
    stored = raw ? (JSON.parse(raw) as UnknownRecord) : null
  } catch {
    stored = null
  }
  if (!stored || !Array.isArray(stored.sections)) return fallback

  if (String(stored.baseFingerprint || '') !== String(options.fingerprint || '')) {
    clearDraft(storage, options.pageId, options.path)
    return { ...fallback, message: STRINGS.statusStaleDraftDiscarded }
  }

  const restoredSections = cloneShellSections(stored.sections)
  if (!restoredSections.length) {
    clearDraft(storage, options.pageId, options.path)
    return fallback
  }

  return {
    sections: restoredSections,
    restored: true,
    message: STRINGS.statusDraftRestored,
    activeSectionId: typeof stored.activeSectionId === 'string' ? stored.activeSectionId : null,
    activeField: stored.activeField ?? null,
  }
}
