import { describe, it, expect, vi, beforeEach } from 'vitest'

let draftEnabled = false

vi.mock('next/headers', () => ({
  draftMode: () => ({ isEnabled: draftEnabled }),
}))

describe('CMS client draft mode', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    vi.unstubAllGlobals()
    draftEnabled = false
  })

  it('appends preview_token when draft mode enabled', async () => {
    draftEnabled = true
    process.env.DRAFT_SECRET = 'my-secret'

    const fetchSpy = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ success: true }),
    })
    vi.stubGlobal('fetch', fetchSpy)

    const { fetchCmsJson } = await import('@/lib/cmsClient')
    await fetchCmsJson('/content/homepage', { revalidate: 0 })

    const calledUrl = fetchSpy.mock.calls[0][0] as string
    expect(calledUrl).toContain('preview_token=my-secret')
  })

  it('does not append preview_token when draft mode disabled', async () => {
    draftEnabled = false

    const fetchSpy = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ success: true }),
    })
    vi.stubGlobal('fetch', fetchSpy)

    const { fetchCmsJson } = await import('@/lib/cmsClient')
    await fetchCmsJson('/content/homepage', { revalidate: 60 })

    const calledUrl = fetchSpy.mock.calls[0][0] as string
    expect(calledUrl).not.toContain('preview_token')
  })
})
