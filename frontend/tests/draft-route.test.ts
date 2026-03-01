import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockEnable = vi.fn()
const mockDisable = vi.fn()

vi.mock('next/headers', () => ({
  draftMode: () => ({ enable: mockEnable, disable: mockDisable, isEnabled: false }),
}))

const SECRET = 'test-draft-secret'

describe('GET /api/draft', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    process.env.DRAFT_SECRET = SECRET
  })

  it('returns 401 without secret', async () => {
    const { GET } = await import('@/app/api/draft/route')
    const req = new Request('https://bioco.ch/api/draft')
    const res = await GET(req)
    expect(res.status).toBe(401)
  })

  it('enables draft mode and redirects to path', async () => {
    const { GET } = await import('@/app/api/draft/route')
    const req = new Request(`https://bioco.ch/api/draft?secret=${SECRET}&path=/mitmachen`)
    const res = await GET(req)
    expect(res.status).toBe(307)
    expect(res.headers.get('location')).toContain('/mitmachen')
    expect(mockEnable).toHaveBeenCalled()
  })

  it('disables draft mode when disable=1', async () => {
    const { GET } = await import('@/app/api/draft/route')
    const req = new Request(`https://bioco.ch/api/draft?secret=${SECRET}&disable=1`)
    const res = await GET(req)
    expect(mockDisable).toHaveBeenCalled()
  })

  it('redirects to / when no path given', async () => {
    const { GET } = await import('@/app/api/draft/route')
    const req = new Request(`https://bioco.ch/api/draft?secret=${SECRET}`)
    const res = await GET(req)
    expect(res.status).toBe(307)
    expect(res.headers.get('location')).toContain('/')
  })
})
