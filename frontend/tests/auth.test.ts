import { describe, it, expect, vi, beforeEach } from 'vitest'

describe('checkPwSession', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    vi.unstubAllGlobals()
  })

  it('returns user info when PW session is valid', async () => {
    const fetchSpy = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ loggedIn: true, username: 'admin' }),
    })
    vi.stubGlobal('fetch', fetchSpy)

    const { checkPwSession } = await import('@/lib/auth')
    const result = await checkPwSession('wire_session=abc123')

    expect(result).toEqual({ loggedIn: true, username: 'admin' })
    expect(fetchSpy).toHaveBeenCalledWith(
      expect.stringContaining('/api/auth-check'),
      expect.objectContaining({ credentials: 'include' })
    )
  })

  it('returns null when PW session is invalid', async () => {
    const fetchSpy = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
    })
    vi.stubGlobal('fetch', fetchSpy)

    const { checkPwSession } = await import('@/lib/auth')
    const result = await checkPwSession(undefined)
    expect(result).toBeNull()
  })

  it('returns null when fetch fails (network error)', async () => {
    const fetchSpy = vi.fn().mockRejectedValue(new Error('Network error'))
    vi.stubGlobal('fetch', fetchSpy)

    const { checkPwSession } = await import('@/lib/auth')
    const result = await checkPwSession('wire_session=abc123')
    expect(result).toBeNull()
  })
})
