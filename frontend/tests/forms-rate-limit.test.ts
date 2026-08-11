import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/lib/email', () => ({
  sendFormEmail: vi.fn(),
}))

vi.mock('@/lib/membership', () => ({
  validateMembership: vi.fn(() => ({ ok: true })),
  buildIntranetSignupPayload: vi.fn((body: unknown) => body),
}))

vi.mock('@/lib/intranetSignup', () => ({
  forwardToIntranet: vi.fn(async () => ({ ok: true })),
}))

import { sendFormEmail } from '@/lib/email'
import { RATE_LIMITS, resetRateLimits } from '@/lib/rateLimit'

const validPayload = {
  firstName: 'Jane',
  lastName: 'Doe',
  email: 'jane@example.com',
  address: 'Street 1',
  zip: '8000',
  city: 'Zurich',
  commitmentAccepted: [true, true, true, true],
  privacyAccept: true,
}

function postMembership(ip: string) {
  return import('@/app/api/forms/membership/route').then(({ POST }) =>
    POST(
      new Request('https://bioco.ch/api/forms/membership', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'x-forwarded-for': ip },
        body: JSON.stringify(validPayload),
      }) as never
    )
  )
}

describe('membership route rate limiting', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetRateLimits()
    vi.mocked(sendFormEmail).mockResolvedValue({ success: true, id: 'test-message-id' })
  })

  it('allows submissions up to the membership limit, then returns 429 with Retry-After', async () => {
    const ip = '203.0.113.5'
    const { limit } = RATE_LIMITS.membership

    for (let i = 0; i < limit; i++) {
      const response = await postMembership(ip)
      expect(response.status).toBe(200)
    }

    const blocked = await postMembership(ip)
    expect(blocked.status).toBe(429)
    expect(blocked.headers.get('Retry-After')).toBeTruthy()
    expect(Number(blocked.headers.get('Retry-After'))).toBeGreaterThan(0)

    const body = await blocked.json()
    expect(body).toMatchObject({ success: false })
    expect(body.error).toMatch(/Zu viele Anfragen/)

    // the request never even reached email sending
    expect(sendFormEmail).toHaveBeenCalledTimes(limit)
  })

  it('does not rate-limit a different client IP once one IP is exhausted', async () => {
    const exhaustedIp = '203.0.113.9'
    const otherIp = '203.0.113.10'
    const { limit } = RATE_LIMITS.membership

    for (let i = 0; i < limit; i++) {
      await postMembership(exhaustedIp)
    }
    const blocked = await postMembership(exhaustedIp)
    expect(blocked.status).toBe(429)

    const otherResponse = await postMembership(otherIp)
    expect(otherResponse.status).toBe(200)
    await expect(otherResponse.json()).resolves.toMatchObject({ success: true })
  })
})
