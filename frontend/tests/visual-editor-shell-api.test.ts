import { describe, expect, it, vi } from 'vitest'
import { ApiError, combineCollectionEntries, createShellApi, publishPill } from '../visual-editor-shell/api'

// G.2 — one typed fetch wrapper for all admin endpoints the shell talks to.
// content-save stays sectionPwId-first; content-publish carries the
// baseFingerprint (concurrency guard) and returns the revalidate outcome
// that drives the "Publiziert, aber Build nicht aktualisiert" pill.

function jsonResponse(body: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(body),
  } as Response
}

function apiWithMock(body: unknown, status = 200) {
  const fetchMock = vi.fn(() => Promise.resolve(jsonResponse(body, status)))
  const api = createShellApi({ apiRoot: '/api/' }, fetchMock as unknown as typeof fetch)
  return { api, fetchMock }
}

function requestOf(fetchMock: ReturnType<typeof vi.fn>, call = 0): { url: string; init: RequestInit } {
  const [url, init] = fetchMock.mock.calls[call] as [string, RequestInit]
  return { url, init }
}

describe('createShellApi', () => {
  it('fetches homepage sections for "/" and slug sections otherwise, with credentials', async () => {
    const { api, fetchMock } = apiWithMock({ sections: [], hero: { headline: 'Hi' }, fingerprint: 'fp-1' })
    const home = await api.fetchSections('/')
    expect(requestOf(fetchMock).url).toBe('/api/content/homepage')
    expect(requestOf(fetchMock).init.credentials).toBe('include')
    expect(home.fingerprint).toBe('fp-1')

    await api.fetchSections('/abos')
    expect(requestOf(fetchMock, 1).url).toBe('/api/content/sections/abos')
  })

  it('publishes via content-publish with pageId, path, baseFingerprint and sections', async () => {
    const { api, fetchMock } = apiWithMock({
      success: true,
      fingerprint: 'fp-2',
      hero: null,
      sections: [{ id: 's1', title: 'T', text: '' }],
      revalidated: true,
      revalidateStatus: 200,
      revalidateError: '',
    })
    const result = await api.publish({
      pageId: 1042,
      path: '/abos',
      baseFingerprint: 'fp-1',
      sections: [{ id: 's1', title: 'T', text: '' }],
    })
    const { url, init } = requestOf(fetchMock)
    expect(url).toBe('/api/content-publish')
    expect(init.method).toBe('POST')
    const body = JSON.parse(String(init.body))
    expect(body).toMatchObject({ pageId: 1042, path: '/abos', baseFingerprint: 'fp-1' })
    expect(result.revalidated).toBe(true)
    expect(result.fingerprint).toBe('fp-2')
  })

  it('throws ApiError carrying server data on publish conflict (409)', async () => {
    const { api } = apiWithMock({
      success: false,
      error: 'Die Seite wurde zwischenzeitlich geändert. Entwurf neu laden oder verwerfen.',
      fingerprint: 'fp-server',
      hero: null,
      sections: [{ id: 's1', title: 'Server', text: '' }],
    }, 409)

    const error = await api.publish({ pageId: 1, path: '/abos', baseFingerprint: 'fp-old', sections: [] })
      .then(() => null, (e: unknown) => e as ApiError)
    expect(error).toBeInstanceOf(ApiError)
    expect(error?.status).toBe(409)
    expect(error?.message).toContain('zwischenzeitlich geändert')
    expect(error?.data).toMatchObject({ fingerprint: 'fp-server' })
  })

  it('uses the German fallback error when the server sends no message', async () => {
    const { api } = apiWithMock({}, 500)
    await expect(api.publish({ pageId: 1, path: '/', baseFingerprint: 'x', sections: [] }))
      .rejects.toThrow('Publizieren fehlgeschlagen')
  })

  it('content-save is sectionPwId-first', async () => {
    const { api, fetchMock } = apiWithMock({ success: true, saved: true, sectionId: 77 })
    await api.saveSectionFields({ sectionPwId: 77, fields: { section_title: 'Neu' } })
    const body = JSON.parse(String(requestOf(fetchMock).init.body))
    expect(body.sectionPwId).toBe(77)
    expect(requestOf(fetchMock).url).toBe('/api/content-save')

    await api.saveSectionFields({ pageId: 1, fields: { hero_headline: 'Neu' } })
    const pageBody = JSON.parse(String(requestOf(fetchMock, 1).init.body))
    expect(pageBody.pageId).toBe(1)
    expect(pageBody.sectionPwId).toBeUndefined()
  })

  it('covers section CRUD + collection-create endpoints', async () => {
    const { api, fetchMock } = apiWithMock({ success: true, section: { id: 's' }, editUrl: '/edit', pwId: 9 })
    await api.addSection(5, 'rich_text')
    await api.deleteSection(5, 123)
    await api.reorderSections(5, [3, 1, 2])
    await api.createCollectionEntry('event', '2026-07-04')
    expect((fetchMock.mock.calls as unknown as [string, RequestInit][]).map((c) => c[0])).toEqual([
      '/api/sections-add',
      '/api/sections-delete',
      '/api/sections-reorder',
      '/api/collection-create',
    ])
    expect(JSON.parse(String(requestOf(fetchMock, 2).init.body))).toEqual({ pageId: 5, order: [3, 1, 2] })
    expect(JSON.parse(String(requestOf(fetchMock, 3).init.body))).toEqual({ type: 'event', date: '2026-07-04' })
  })

  it('fetches media files and presets with German fallback errors', async () => {
    const okFiles = apiWithMock({ success: true, files: [{ url: '/a.jpg' }] })
    await expect(okFiles.api.fetchMediaFiles()).resolves.toEqual([{ url: '/a.jpg' }])
    expect(requestOf(okFiles.fetchMock).url).toBe('/api/media-files')

    const failFiles = apiWithMock({ success: false }, 500)
    await expect(failFiles.api.fetchMediaFiles()).rejects.toThrow('Medien konnten nicht geladen werden')

    const okPresets = apiWithMock({ success: true, presets: [{ name: 'P' }] })
    await expect(okPresets.api.fetchPresets()).resolves.toEqual([{ name: 'P' }])
    expect(requestOf(okPresets.fetchMock).url).toBe('/api/content/presets')

    const failPresets = apiWithMock({}, 500)
    await expect(failPresets.api.fetchPresets()).rejects.toThrow('Vorlagen konnten nicht geladen werden')
  })

  it('fetches collection entries from the configured list endpoint', async () => {
    const { api, fetchMock } = apiWithMock({ upcoming: [{ id: 1, title: 'A' }], past: [] })
    const data = await api.fetchCollectionEntries('content/events')
    expect(requestOf(fetchMock).url).toBe('/api/content/events')
    expect(data).toMatchObject({ upcoming: [{ id: 1, title: 'A' }] })
  })

  it('treats success:false with HTTP 200 as an error for admin POSTs', async () => {
    const { api } = apiWithMock({ success: false, error: 'Nur Events werden derzeit unterstützt.' })
    await expect(api.createCollectionEntry('blog', '2026-01-01'))
      .rejects.toThrow('Nur Events werden derzeit unterstützt.')
  })
})

describe('combineCollectionEntries', () => {
  it('flattens upcoming + past entries and tags their status', () => {
    const entries = combineCollectionEntries({
      upcoming: [{ id: 1, title: 'Bald' }],
      past: [{ id: 2, title: 'Vorbei' }],
    })
    expect(entries).toEqual([
      { id: 1, title: 'Bald', _status: 'upcoming' },
      { id: 2, title: 'Vorbei', _status: 'past' },
    ])
  })

  it('handles missing arrays gracefully', () => {
    expect(combineCollectionEntries({})).toEqual([])
    expect(combineCollectionEntries({ upcoming: 'junk' })).toEqual([])
  })
})

describe('publishPill', () => {
  it('is green when the build was revalidated', () => {
    expect(publishPill({ revalidated: true })).toEqual({ text: 'Publiziert & live', cls: 'is-ready' })
  })

  it('turns red with the exact German warning when revalidation failed', () => {
    expect(publishPill({ revalidated: false })).toEqual({
      text: 'Publiziert, aber Build nicht aktualisiert',
      cls: 'is-error',
    })
    expect(publishPill({ revalidated: false, revalidateError: 'timeout' })).toEqual({
      text: 'Publiziert, aber Build nicht aktualisiert (timeout)',
      cls: 'is-error',
    })
  })
})
