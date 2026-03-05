import { describe, it, expect, vi, beforeEach } from 'vitest'

vi.mock('next/cache', () => ({
  revalidatePath: vi.fn(),
  revalidateTag: vi.fn(),
}))

const SECRET = 'test-revalidate-secret'

describe('POST /api/revalidate', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    process.env.REVALIDATE_SECRET = SECRET
  })

  it('returns 401 when secret is missing', async () => {
    const { POST } = await import('@/app/api/revalidate/route')
    const req = new Request('https://bioco.ch/api/revalidate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ path: '/' }),
    })
    const res = await POST(req)
    expect(res.status).toBe(401)
  })

  it('returns 401 when secret is wrong', async () => {
    const { POST } = await import('@/app/api/revalidate/route')
    const req = new Request('https://bioco.ch/api/revalidate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ secret: 'wrong', path: '/' }),
    })
    const res = await POST(req)
    expect(res.status).toBe(401)
  })

  it('returns 200 and calls revalidatePath for valid secret + path', async () => {
    const { revalidatePath } = await import('next/cache')
    const { POST } = await import('@/app/api/revalidate/route')
    const req = new Request('https://bioco.ch/api/revalidate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ secret: SECRET, path: '/mitmachen' }),
    })
    const res = await POST(req)
    expect(res.status).toBe(200)
    expect(revalidatePath).toHaveBeenCalledWith('/mitmachen')
    const body = await res.json()
    expect(body.revalidated).toBe(true)
    expect(body.paths).toEqual(['/mitmachen'])
  })

  it('revalidates root layout when no path given', async () => {
    const { revalidatePath } = await import('next/cache')
    const { POST } = await import('@/app/api/revalidate/route')
    const req = new Request('https://bioco.ch/api/revalidate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ secret: SECRET }),
    })
    const res = await POST(req)
    expect(res.status).toBe(200)
    expect(revalidatePath).toHaveBeenCalledWith('/', 'layout')
  })

  it('revalidates tags and multiple paths', async () => {
    const { revalidatePath, revalidateTag } = await import('next/cache')
    const { POST } = await import('@/app/api/revalidate/route')
    const req = new Request('https://bioco.ch/api/revalidate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        secret: SECRET,
        path: 'mitmachen',
        paths: ['/aktuelles', '/mitmachen'],
        tag: 'cms',
        tags: ['cms:nav', 'cms'],
      }),
    })
    const res = await POST(req)
    expect(res.status).toBe(200)
    expect(revalidatePath).toHaveBeenCalledWith('/mitmachen')
    expect(revalidatePath).toHaveBeenCalledWith('/aktuelles')
    expect(revalidateTag).toHaveBeenCalledWith('cms')
    expect(revalidateTag).toHaveBeenCalledWith('cms:nav')
  })

  it('returns 401 for malformed JSON body', async () => {
    const { POST } = await import('@/app/api/revalidate/route')
    const req = new Request('https://bioco.ch/api/revalidate', {
      method: 'POST',
      body: 'not json',
    })
    const res = await POST(req)
    expect(res.status).toBe(401)
  })
})
