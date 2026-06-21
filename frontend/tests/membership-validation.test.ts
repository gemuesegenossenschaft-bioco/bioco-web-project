import { describe, expect, it } from 'vitest'
import { validateMembership } from '@/lib/membership'

// D.1 — faithful extraction of the current /api/forms/membership validation.
// No behavior change: same required fields, notify-only backend unchanged.
const valid = {
  firstName: 'Anna',
  lastName: 'Muster',
  email: 'anna@example.ch',
  address: 'Dorfstrasse 1',
  zip: '5236',
  city: 'Gebenstorf',
  privacyAccept: true,
}

describe('validateMembership (D.1)', () => {
  it('accepts a complete, valid submission', () => {
    const r = validateMembership(valid)
    expect(r.ok).toBe(true)
    expect(r.errors).toEqual({})
  })

  it.each(['firstName', 'lastName', 'email', 'address', 'zip', 'city'] as const)(
    'rejects missing %s',
    (field) => {
      const r = validateMembership({ ...valid, [field]: '' })
      expect(r.ok).toBe(false)
      expect(r.errors[field]).toBeTruthy()
    }
  )

  it('requires privacy acceptance', () => {
    const r = validateMembership({ ...valid, privacyAccept: false })
    expect(r.ok).toBe(false)
    expect(r.errors.privacyAccept).toBeTruthy()
  })

  it('rejects a malformed email', () => {
    const r = validateMembership({ ...valid, email: 'not-an-email' })
    expect(r.ok).toBe(false)
    expect(r.errors.email).toBeTruthy()
  })
})
