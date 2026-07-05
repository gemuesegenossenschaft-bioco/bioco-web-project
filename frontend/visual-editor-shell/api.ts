/**
 * Typed API layer: one fetch wrapper for every endpoint the visual-editor
 * shell talks to (site/templates/api.php). Replaces the old IIFE's scattered
 * fetch/postJson calls.
 *
 * Contract notes:
 * - content-save is sectionPwId-first (pageId only for hero/page fields).
 * - content-publish carries baseFingerprint; a 409 means the page changed
 *   under the draft — the thrown ApiError carries the server's canonical
 *   state (fingerprint/hero/sections) for conflict resolution.
 * - `revalidated: false` on publish drives the red
 *   "Publiziert, aber Build nicht aktualisiert" pill (see publishPill).
 */

import { STRINGS } from './strings'

export class ApiError extends Error {
  readonly status: number
  readonly data: Record<string, unknown>

  constructor(message: string, status: number, data: Record<string, unknown> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.data = data
  }
}

export interface SectionsResponse {
  sections?: unknown[]
  hero?: Record<string, unknown> | null
  fingerprint?: string
  [extra: string]: unknown
}

export interface PublishPayload {
  pageId: number
  path: string
  baseFingerprint: string
  sections: unknown[]
}

export interface PublishResult {
  success?: boolean
  fingerprint?: string
  hero?: Record<string, unknown> | null
  sections?: unknown[]
  revalidated?: boolean
  revalidateStatus?: number
  revalidateError?: string
  [extra: string]: unknown
}

export interface SaveSectionFieldsPayload {
  sectionPwId?: number
  pageId?: number
  fields: Record<string, unknown>
}

export interface ShellApi {
  fetchSections(path: string): Promise<SectionsResponse>
  publish(payload: PublishPayload): Promise<PublishResult>
  saveSectionFields(payload: SaveSectionFieldsPayload): Promise<Record<string, unknown>>
  addSection(pageId: number, layout: string): Promise<Record<string, unknown>>
  deleteSection(pageId: number, sectionPwId: number): Promise<Record<string, unknown>>
  reorderSections(pageId: number, order: number[]): Promise<Record<string, unknown>>
  createCollectionEntry(type: string, date: string): Promise<Record<string, unknown>>
  fetchMediaFiles(): Promise<Array<Record<string, unknown>>>
  fetchPresets(): Promise<Array<Record<string, unknown>>>
  fetchCollectionEntries(listEndpoint: string): Promise<Record<string, unknown>>
}

type FetchLike = typeof fetch

function joinUrl(apiRoot: string, endpoint: string): string {
  return apiRoot.replace(/\/+$/, '') + '/' + endpoint.replace(/^\/+/, '')
}

async function parseBody(response: Response): Promise<Record<string, unknown>> {
  try {
    const data: unknown = await response.json()
    return typeof data === 'object' && data !== null ? (data as Record<string, unknown>) : {}
  } catch {
    return {}
  }
}

function errorFrom(response: Response, data: Record<string, unknown>, fallback: string): ApiError {
  const message = typeof data.error === 'string' && data.error ? data.error : fallback
  return new ApiError(message, response.status, data)
}

export function createShellApi(config: { apiRoot: string }, fetchImpl: FetchLike = fetch): ShellApi {
  async function getJson(
    endpoint: string,
    fallbackError: string,
    options: { requireSuccess?: boolean } = {}
  ): Promise<Record<string, unknown>> {
    const response = await fetchImpl(joinUrl(config.apiRoot, endpoint), { credentials: 'include' })
    const data = await parseBody(response)
    if (!response.ok || (options.requireSuccess && !data.success)) {
      throw errorFrom(response, data, fallbackError)
    }
    return data
  }

  async function postJson(
    endpoint: string,
    body: Record<string, unknown>,
    fallbackError: string
  ): Promise<Record<string, unknown>> {
    const response = await fetchImpl(joinUrl(config.apiRoot, endpoint), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(body),
    })
    const data = await parseBody(response)
    // Old shell semantics preserved: an admin POST without success:true is a failure,
    // even on HTTP 200.
    if (!response.ok || !data.success) {
      throw errorFrom(response, data, fallbackError)
    }
    return data
  }

  return {
    fetchSections(path) {
      const endpoint =
        path === '/'
          ? 'content/homepage'
          : 'content/sections/' + encodeURIComponent(path.replace(/^\/|\/$/g, ''))
      return getJson(endpoint, STRINGS.errorLoadFailed)
    },

    publish(payload) {
      return postJson('content-publish', { ...payload }, STRINGS.errorPublishFailed)
    },

    saveSectionFields({ sectionPwId, pageId, fields }) {
      const body: Record<string, unknown> = sectionPwId ? { sectionPwId, fields } : { pageId, fields }
      return postJson('content-save', body, STRINGS.errorPublishFailed)
    },

    addSection(pageId, layout) {
      return postJson('sections-add', { pageId, layout }, STRINGS.errorLoadFailed)
    },

    deleteSection(pageId, sectionPwId) {
      return postJson('sections-delete', { pageId, sectionPwId }, STRINGS.errorLoadFailed)
    },

    reorderSections(pageId, order) {
      return postJson('sections-reorder', { pageId, order }, STRINGS.errorLoadFailed)
    },

    createCollectionEntry(type, date) {
      return postJson('collection-create', { type, date }, STRINGS.errorEntryCreateFailed)
    },

    async fetchMediaFiles() {
      const data = await getJson('media-files', STRINGS.mediaLoadFailed, { requireSuccess: true })
      return Array.isArray(data.files) ? (data.files as Array<Record<string, unknown>>) : []
    },

    async fetchPresets() {
      const data = await getJson('content/presets', STRINGS.presetLoadFailed, { requireSuccess: true })
      return Array.isArray(data.presets) ? (data.presets as Array<Record<string, unknown>>) : []
    },

    fetchCollectionEntries(listEndpoint) {
      return getJson(listEndpoint, STRINGS.collectionLoadFailed)
    },
  }
}

export interface CollectionEntry {
  _status: 'upcoming' | 'past'
  [extra: string]: unknown
}

/** Flatten an events-style list response ({upcoming,past}) tagging each entry. */
export function combineCollectionEntries(data: Record<string, unknown>): CollectionEntry[] {
  const entries: CollectionEntry[] = []
  for (const status of ['upcoming', 'past'] as const) {
    const list = data[status]
    if (!Array.isArray(list)) continue
    for (const entry of list) {
      if (typeof entry !== 'object' || entry === null) continue
      entries.push({ ...(entry as Record<string, unknown>), _status: status })
    }
  }
  return entries
}

export interface PublishPillState {
  text: string
  cls: 'is-ready' | 'is-error'
}

/** Status pill after publish: red when the Next.js build was not revalidated. */
export function publishPill(result: { revalidated?: boolean; revalidateError?: string }): PublishPillState {
  if (result.revalidated === false) {
    const why = result.revalidateError ? ` (${result.revalidateError})` : ''
    return { text: `${STRINGS.statusPublishedStaleBuild}${why}`, cls: 'is-error' }
  }
  return { text: STRINGS.statusPublishedLive, cls: 'is-ready' }
}
