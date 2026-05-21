import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/lib/email', () => ({
  sendFormEmail: vi.fn(),
}))

vi.mock('@/lib/turnstile', () => ({
  verifyTurnstileToken: vi.fn(),
}))

import { sendFormEmail } from '@/lib/email'
import { verifyTurnstileToken } from '@/lib/turnstile'

const captchaProtectedRouteSpecs = [
  {
    name: 'contact',
    load: () => import('@/app/api/forms/contact/route'),
    validPayload: { name: 'Jane', email: 'jane@example.com', subject: 'Hi', message: 'Hello', captchaToken: 'tok' },
  },
  {
    name: 'visit',
    load: () => import('@/app/api/forms/visit/route'),
    validPayload: {
      name: 'Jane',
      email: 'jane@example.com',
      phone: '123',
      visit_date: '2026-04-01',
      participants: 2,
      privacy_accept: true,
      captchaToken: 'tok',
    },
  },
  {
    name: 'waiting-list',
    load: () => import('@/app/api/forms/waiting-list/route'),
    validPayload: {
      name: 'Jane',
      email: 'jane@example.com',
      phone: '123',
      interest: 'program1',
      privacy_accept: true,
      captchaToken: 'tok',
    },
  },
  {
    name: 'event-signup',
    load: () => import('@/app/api/forms/event-signup/route'),
    validPayload: { name: 'Jane', email: 'jane@example.com', eventTitle: 'Event', captchaToken: 'tok' },
  },
] as const

const captchaFreeRouteSpecs = [
  {
    name: 'subscribe',
    load: () => import('@/app/api/forms/subscribe/route'),
    validPayload: { email: 'jane@example.com', privacy_accept: true },
  },
  {
    name: 'membership',
    load: () => import('@/app/api/forms/membership/route'),
    validPayload: {
      firstName: 'Jane',
      lastName: 'Doe',
      email: 'jane@example.com',
      address: 'Street 1',
      zip: '8000',
      city: 'Zurich',
      privacyAccept: true,
    },
  },
] as const

describe('form routes captcha enforcement', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns 400 when captcha missing', async () => {
    vi.mocked(verifyTurnstileToken).mockResolvedValue({ ok: false, errorCode: 'captcha_missing' })

    for (const route of captchaProtectedRouteSpecs) {
      const { POST } = await route.load()
      const payload = { ...route.validPayload }
      delete (payload as { captchaToken?: string }).captchaToken

      const response = await POST(
        new Request(`https://bioco.ch/api/forms/${route.name}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        }) as never
      )

      expect(response.status).toBe(400)
      await expect(response.json()).resolves.toMatchObject({ success: false })
    }

    expect(sendFormEmail).not.toHaveBeenCalled()
  })

  it('returns 400 when captcha invalid', async () => {
    vi.mocked(verifyTurnstileToken).mockResolvedValue({ ok: false, errorCode: 'invalid-input-response' })

    for (const route of captchaProtectedRouteSpecs) {
      const { POST } = await route.load()
      const response = await POST(
        new Request(`https://bioco.ch/api/forms/${route.name}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(route.validPayload),
        }) as never
      )

      expect(response.status).toBe(400)
      await expect(response.json()).resolves.toMatchObject({ success: false })
    }

    expect(sendFormEmail).not.toHaveBeenCalled()
  })

  it('returns 200 and sends email when captcha valid', async () => {
    vi.mocked(verifyTurnstileToken).mockResolvedValue({ ok: true })
    vi.mocked(sendFormEmail).mockResolvedValue({ success: true, id: 'test-message-id' })

    for (const route of captchaProtectedRouteSpecs) {
      const { POST } = await route.load()
      const response = await POST(
        new Request(`https://bioco.ch/api/forms/${route.name}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'x-forwarded-for': '198.51.100.10' },
          body: JSON.stringify(route.validPayload),
        }) as never
      )

      expect(response.status).toBe(200)
      await expect(response.json()).resolves.toEqual({ success: true })
    }

    expect(sendFormEmail).toHaveBeenCalledTimes(captchaProtectedRouteSpecs.length)
    expect(verifyTurnstileToken).toHaveBeenCalledTimes(captchaProtectedRouteSpecs.length)
  })

  it('allows subscribe and membership submissions without captcha verification', async () => {
    vi.mocked(sendFormEmail).mockResolvedValue({ success: true, id: 'test-message-id' })

    for (const route of captchaFreeRouteSpecs) {
      const { POST } = await route.load()
      const response = await POST(
        new Request(`https://bioco.ch/api/forms/${route.name}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(route.validPayload),
        }) as never
      )

      expect(response.status).toBe(200)
      await expect(response.json()).resolves.toEqual({ success: true })
    }

    expect(sendFormEmail).toHaveBeenCalledTimes(captchaFreeRouteSpecs.length)
    expect(verifyTurnstileToken).not.toHaveBeenCalled()
  })
})
