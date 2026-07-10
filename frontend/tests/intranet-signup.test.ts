import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { INTRANET_FIELD_NAMES, buildIntranetSignupPayload } from '@/lib/membership'
import { forwardToIntranet } from '@/lib/intranetSignup'

// D.2a — full MembershipForm fixture, as submitted by
// components/forms/MembershipForm.tsx.
const fullMembershipFixture = {
  firstName: 'Anna',
  lastName: 'Muster',
  address: 'Dorfstrasse 1',
  zip: '5236',
  city: 'Gebenstorf',
  phone: '079 123 45 67',
  email: 'anna@example.ch',
  membershipType: 'abo' as const,
  aboType: 'standard' as const,
  additionalShares: 1,
  sharesOnly: 1,
  depot: 'Depot Chrättli',
  paymentType: 'yearly' as const,
  preferredDays: ['Montag', 'Mittwoch'],
  preferredTimes: ['morgens'],
  activityAreas: ['Feld/Anbau', 'Andere'],
  otherActivity: 'Vereinsfeste organisieren',
  zusatzabos: ['Milch', 'Käse'],
  weitereProdukte: 'Eier bitte',
  commitmentAccepted: [true, true, true, true],
  privacyAccept: true,
}

describe('buildIntranetSignupPayload (D.2a contract test)', () => {
  it('maps every INTRANET_FIELD_NAMES key onto the payload', () => {
    const payload = buildIntranetSignupPayload(fullMembershipFixture)

    for (const intranetFieldName of Object.values(INTRANET_FIELD_NAMES)) {
      expect(payload).toHaveProperty(intranetFieldName)
    }
  })

  it('maps personal + address + contact fields', () => {
    const payload = buildIntranetSignupPayload(fullMembershipFixture)

    expect(payload[INTRANET_FIELD_NAMES.firstName]).toBe('Anna')
    expect(payload[INTRANET_FIELD_NAMES.lastName]).toBe('Muster')
    expect(payload[INTRANET_FIELD_NAMES.email]).toBe('anna@example.ch')
    expect(payload[INTRANET_FIELD_NAMES.phone]).toBe('079 123 45 67')
    expect(payload[INTRANET_FIELD_NAMES.street]).toBe('Dorfstrasse 1')
    expect(payload[INTRANET_FIELD_NAMES.postalCode]).toBe('5236')
    expect(payload[INTRANET_FIELD_NAMES.city]).toBe('Gebenstorf')
  })

  it('maps membership/abo/shares/depot/payment fields', () => {
    const payload = buildIntranetSignupPayload(fullMembershipFixture)

    expect(payload[INTRANET_FIELD_NAMES.membershipType]).toBe('abo')
    expect(payload[INTRANET_FIELD_NAMES.abo]).toBe('standard')
    // standard abo requires 2 shares + 1 additional = 3
    expect(payload[INTRANET_FIELD_NAMES.shares]).toBe('3')
    expect(payload[INTRANET_FIELD_NAMES.depot]).toBe('Depot Chrättli')
    expect(payload[INTRANET_FIELD_NAMES.paymentInterval]).toBe('yearly')
  })

  it('uses sharesOnly when membershipType is shares-only', () => {
    const payload = buildIntranetSignupPayload({
      ...fullMembershipFixture,
      membershipType: 'shares-only',
      sharesOnly: 4,
    })

    expect(payload[INTRANET_FIELD_NAMES.shares]).toBe('4')
  })

  it('maps commitment + privacy acknowledgements onto terms', () => {
    const accepted = buildIntranetSignupPayload(fullMembershipFixture)
    expect(accepted[INTRANET_FIELD_NAMES.terms]).toBe('on')

    const missingCommitment = buildIntranetSignupPayload({
      ...fullMembershipFixture,
      commitmentAccepted: [true, true, false, true],
    })
    expect(missingCommitment[INTRANET_FIELD_NAMES.terms]).toBe('')

    const missingPrivacy = buildIntranetSignupPayload({
      ...fullMembershipFixture,
      privacyAccept: false,
    })
    expect(missingPrivacy[INTRANET_FIELD_NAMES.terms]).toBe('')
  })

  it('serializes extras (preferredDays/-Times, activityAreas, zusatzabos, free text) into notes', () => {
    const payload = buildIntranetSignupPayload(fullMembershipFixture)
    const notes = payload[INTRANET_FIELD_NAMES.notes]

    expect(notes).toContain('Montag')
    expect(notes).toContain('Mittwoch')
    expect(notes).toContain('morgens')
    expect(notes).toContain('Feld/Anbau')
    expect(notes).toContain('Vereinsfeste organisieren')
    expect(notes).toContain('Milch')
    expect(notes).toContain('Käse')
    expect(notes).toContain('Eier bitte')
  })

  it('produces an empty notes string when no extras are given', () => {
    const payload = buildIntranetSignupPayload({
      firstName: 'Anna',
      lastName: 'Muster',
      email: 'anna@example.ch',
      address: 'Dorfstrasse 1',
      zip: '5236',
      city: 'Gebenstorf',
      privacyAccept: true,
    })

    expect(payload[INTRANET_FIELD_NAMES.notes]).toBe('')
  })
})

// D.2b — forwardToIntranet against a mock intranet endpoint.
const INTRANET_URL = 'https://intranet.bioco.ch/my/signup/'

function primeHtml(token: string) {
  return `<html><body><form><input type="hidden" name="csrfmiddlewaretoken" value="${token}"></form></body></html>`
}

function makeMockFetch(handler: (url: string, init?: RequestInit) => Promise<Response> | Response) {
  return vi.fn(async (url: string, init?: RequestInit) => handler(url, init))
}

// happy-dom's Headers filters `set-cookie` (browser forbidden-header rules),
// so a real `new Response(..., {headers: {'set-cookie': ...}})` loses the
// cookie. Duck-type the prime response instead; the adapter reads
// getSetCookie() first (undici behavior), then falls back to get().
function mockPrimeResponse(html: string, setCookie: string): Response {
  return {
    status: 200,
    headers: {
      get: (name: string) => (name.toLowerCase() === 'set-cookie' ? setCookie : null),
      getSetCookie: () => [setCookie],
    },
    text: async () => html,
  } as unknown as Response
}

describe('forwardToIntranet (D.2b adapter)', () => {
  const originalUrl = process.env.INTRANET_SIGNUP_URL

  beforeEach(() => {
    process.env.INTRANET_SIGNUP_URL = INTRANET_URL
  })

  afterEach(() => {
    process.env.INTRANET_SIGNUP_URL = originalUrl
    vi.restoreAllMocks()
  })

  it('primes CSRF via GET, then POSTs with cookie + csrfmiddlewaretoken + Referer; 302 => ok:true', async () => {
    const calls: Array<{ url: string; init?: RequestInit }> = []

    const fetchImpl = makeMockFetch((url, init) => {
      calls.push({ url, init })

      if (!init || init.method === 'GET') {
        return mockPrimeResponse(primeHtml('abc123token'), 'csrftoken=cookie-value-xyz; Path=/; HttpOnly')
      }

      return new Response(null, { status: 302, headers: { Location: '/my/signup/done/' } })
    })

    const result = await forwardToIntranet({ first_name: 'Anna' }, { fetchImpl: fetchImpl as unknown as typeof fetch })

    expect(result.ok).toBe(true)
    expect(calls).toHaveLength(2)

    const [getCall, postCall] = calls
    expect(getCall.init?.method ?? 'GET').toBe('GET')

    expect(postCall.init?.method).toBe('POST')
    const headers = postCall.init?.headers as Record<string, string>
    expect(headers.Cookie).toContain('csrftoken=cookie-value-xyz')
    expect(headers.Referer).toBe(INTRANET_URL)
    expect(String(postCall.init?.body)).toContain('csrfmiddlewaretoken=abc123token')
    expect(String(postCall.init?.body)).toContain('first_name=Anna')
  })

  it('treats a 200 re-render with Django field errors as ok:false and parses the errors', async () => {
    const fetchImpl = makeMockFetch((url, init) => {
      if (!init || init.method === 'GET') {
        return mockPrimeResponse(primeHtml('tok'), 'csrftoken=cookie-abc; Path=/')
      }

      return new Response(
        `<html><body><form>
          <ul class="errorlist"><li>Diese E-Mail-Adresse ist bereits registriert.</li></ul>
          <input type="email" name="email" value="anna@example.ch">
        </form></body></html>`,
        { status: 200 }
      )
    })

    const result = await forwardToIntranet({ email: 'anna@example.ch' }, { fetchImpl: fetchImpl as unknown as typeof fetch })

    expect(result.ok).toBe(false)
    expect(result.errors).toBeDefined()
    expect(result.errors?.email).toContain('bereits registriert')
  })

  it('does not throw on network failure and returns ok:false', async () => {
    const fetchImpl = makeMockFetch(() => {
      throw new Error('network down')
    })

    await expect(
      forwardToIntranet({ first_name: 'Anna' }, { fetchImpl: fetchImpl as unknown as typeof fetch })
    ).resolves.toMatchObject({ ok: false })
  })

  it('returns ok:false without a fetch attempt when INTRANET_SIGNUP_URL is unset', async () => {
    delete process.env.INTRANET_SIGNUP_URL
    const fetchImpl = makeMockFetch(() => new Response(null, { status: 200 }))

    const result = await forwardToIntranet({ first_name: 'Anna' }, { fetchImpl: fetchImpl as unknown as typeof fetch })

    expect(result.ok).toBe(false)
    expect(fetchImpl).not.toHaveBeenCalled()
  })
})

// D.2c-lite (route wiring) — the forward only runs when INTRANET_SIGNUP_URL is
// set, and must never fail the user's submission. Existing membership route
// tests (tests/forms-captcha-routes.test.ts) keep INTRANET_SIGNUP_URL unset
// and assert the response body stays exactly { success: true }.
vi.mock('@/lib/email', () => ({
  sendFormEmail: vi.fn(),
}))

describe('membership route + intranet forward wiring', () => {
  const originalUrl = process.env.INTRANET_SIGNUP_URL
  const validPayload = {
    firstName: 'Anna',
    lastName: 'Muster',
    email: 'anna@example.ch',
    address: 'Dorfstrasse 1',
    zip: '5236',
    city: 'Gebenstorf',
    privacyAccept: true,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    process.env.INTRANET_SIGNUP_URL = originalUrl
    vi.unstubAllGlobals()
  })

  it('includes forwarded:true in the response when the forward succeeds', async () => {
    process.env.INTRANET_SIGNUP_URL = INTRANET_URL
    const { sendFormEmail } = await import('@/lib/email')
    vi.mocked(sendFormEmail).mockResolvedValue({ success: true, id: 'msg-1' })

    const fetchImpl = makeMockFetch((url, init) => {
      if (!init || init.method === 'GET') {
        return mockPrimeResponse(primeHtml('tok'), 'csrftoken=cookie-abc; Path=/')
      }
      return new Response(null, { status: 302, headers: { Location: '/done/' } })
    })
    vi.stubGlobal('fetch', fetchImpl)

    const { POST } = await import('@/app/api/forms/membership/route')
    const response = await POST(
      new Request('https://bioco.ch/api/forms/membership', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(validPayload),
      }) as never
    )

    expect(response.status).toBe(200)
    await expect(response.json()).resolves.toEqual({ success: true, forwarded: true })
  })

  it('includes forwarded:false and still returns success when the forward fails', async () => {
    process.env.INTRANET_SIGNUP_URL = INTRANET_URL
    const { sendFormEmail } = await import('@/lib/email')
    vi.mocked(sendFormEmail).mockResolvedValue({ success: true, id: 'msg-2' })

    const fetchImpl = makeMockFetch(() => {
      throw new Error('network down')
    })
    vi.stubGlobal('fetch', fetchImpl)

    const { POST } = await import('@/app/api/forms/membership/route')
    const response = await POST(
      new Request('https://bioco.ch/api/forms/membership', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(validPayload),
      }) as never
    )

    expect(response.status).toBe(200)
    await expect(response.json()).resolves.toEqual({ success: true, forwarded: false })
  })

  it('omits forwarded entirely when INTRANET_SIGNUP_URL is unset', async () => {
    delete process.env.INTRANET_SIGNUP_URL
    const { sendFormEmail } = await import('@/lib/email')
    vi.mocked(sendFormEmail).mockResolvedValue({ success: true, id: 'msg-3' })

    const fetchImpl = makeMockFetch(() => new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetchImpl)

    const { POST } = await import('@/app/api/forms/membership/route')
    const response = await POST(
      new Request('https://bioco.ch/api/forms/membership', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(validPayload),
      }) as never
    )

    expect(response.status).toBe(200)
    await expect(response.json()).resolves.toEqual({ success: true })
    expect(fetchImpl).not.toHaveBeenCalled()
  })
})
