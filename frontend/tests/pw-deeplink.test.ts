import { describe, expect, it } from 'vitest'
import { pwEditUrl, regionAffordance } from '@/lib/pwDeeplink'
import type { AuditEntry } from '@/lib/editabilityAudit'

// B.2 — deep-link into ProcessWire + map an audit entry to its change affordance.
describe('pwEditUrl (B.2)', () => {
  it('builds the PW admin edit URL for a page id', () => {
    expect(pwEditUrl(1740)).toMatch(/\/processwire\/page\/edit\/\?id=1740$/)
  })
  it('throws on a missing/invalid id', () => {
    // @ts-expect-error invalid input
    expect(() => pwEditUrl(undefined)).toThrow()
    expect(() => pwEditUrl(0)).toThrow()
  })
})

describe('regionAffordance (B.2)', () => {
  const mk = (changePath: AuditEntry['changePath']): AuditEntry =>
    ({ status: 'hardcoded', changePath })

  it('maps change paths to affordances', () => {
    expect(regionAffordance({ status: 'cms', changePath: 'inline' })).toBe('inline')
    expect(regionAffordance(mk('pw'))).toBe('pw-link')
    expect(regionAffordance(mk('ticket'))).toBe('ticket')
  })

  it('never returns "none" (every region has a change path)', () => {
    for (const cp of ['inline', 'pw', 'ticket'] as const) {
      expect(regionAffordance(mk(cp))).not.toBe('none')
    }
  })
})
