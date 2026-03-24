import { beforeEach, describe, expect, it, vi } from 'vitest'
import { verifyTurnstileToken } from '@/lib/turnstile'

describe('verifyTurnstileToken', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    delete process.env.TURNSTILE_SECRET_KEY
  })

  it('returns unavailable when secret is missing', async () => {
    const result = await verifyTurnstileToken('token')
    expect(result).toEqual({ ok: false, errorCode: 'captcha_unavailable' })
  })

  it('returns missing when token is missing', async () => {
    process.env.TURNSTILE_SECRET_KEY = 'secret'
    const result = await verifyTurnstileToken('')
    expect(result).toEqual({ ok: false, errorCode: 'captcha_missing' })
  })

  it('returns ok=true for valid verification response', async () => {
    process.env.TURNSTILE_SECRET_KEY = 'secret'
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true }), { status: 200 }))
    )

    const result = await verifyTurnstileToken('valid-token', '203.0.113.5')
    expect(result).toEqual({ ok: true })
  })

  it('returns first turnstile error code for invalid token', async () => {
    process.env.TURNSTILE_SECRET_KEY = 'secret'
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValue(
          new Response(JSON.stringify({ success: false, 'error-codes': ['invalid-input-response'] }), { status: 200 })
        )
    )

    const result = await verifyTurnstileToken('invalid-token')
    expect(result).toEqual({ ok: false, errorCode: 'invalid-input-response' })
  })

  it('returns service error when verify endpoint returns non-200', async () => {
    process.env.TURNSTILE_SECRET_KEY = 'secret'
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('error', { status: 500 })))

    const result = await verifyTurnstileToken('token')
    expect(result).toEqual({ ok: false, errorCode: 'captcha_service_error' })
  })

  it('returns service unreachable when fetch throws', async () => {
    process.env.TURNSTILE_SECRET_KEY = 'secret'
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network fail')))

    const result = await verifyTurnstileToken('token')
    expect(result).toEqual({ ok: false, errorCode: 'captcha_service_unreachable' })
  })
})
