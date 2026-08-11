import { beforeEach, describe, expect, it } from 'vitest'
import { checkRateLimit, resetRateLimits } from '@/lib/rateLimit'

describe('checkRateLimit (pure core)', () => {
  beforeEach(() => {
    resetRateLimits()
  })

  it('allows up to the limit and blocks the N+1th request', () => {
    const opts = { limit: 3, windowMs: 60_000, now: 1_000 }

    expect(checkRateLimit('k', opts)).toMatchObject({ allowed: true, remaining: 2 })
    expect(checkRateLimit('k', opts)).toMatchObject({ allowed: true, remaining: 1 })
    expect(checkRateLimit('k', opts)).toMatchObject({ allowed: true, remaining: 0 })

    const fourth = checkRateLimit('k', opts)
    expect(fourth.allowed).toBe(false)
    expect(fourth.remaining).toBe(0)
  })

  it('reports a positive retryAfterSeconds when blocked', () => {
    const opts = { limit: 1, windowMs: 30_000 }

    expect(checkRateLimit('retry-key', { ...opts, now: 0 }).allowed).toBe(true)
    const blocked = checkRateLimit('retry-key', { ...opts, now: 5_000 })

    expect(blocked.allowed).toBe(false)
    // oldest timestamp (0) expires at 30_000; now is 5_000 -> 25s remain
    expect(blocked.retryAfterSeconds).toBe(25)
  })

  it('rounds retryAfterSeconds up so callers never under-wait', () => {
    const opts = { limit: 1, windowMs: 30_000 }

    expect(checkRateLimit('round-key', { ...opts, now: 0 }).allowed).toBe(true)
    const blocked = checkRateLimit('round-key', { ...opts, now: 29_100 })

    expect(blocked.allowed).toBe(false)
    // 900ms remain -> must round up to 1s, never 0
    expect(blocked.retryAfterSeconds).toBe(1)
  })

  it('expires the window: blocked immediately after the limit, then allowed once the window has fully elapsed', () => {
    const opts = { limit: 2, windowMs: 10_000 }

    expect(checkRateLimit('window-key', { ...opts, now: 0 }).allowed).toBe(true)
    expect(checkRateLimit('window-key', { ...opts, now: 1_000 }).allowed).toBe(true)

    const blocked = checkRateLimit('window-key', { ...opts, now: 5_000 })
    expect(blocked.allowed).toBe(false)

    // still inside the window opened by the first request (expires at 10_000)
    const stillBlocked = checkRateLimit('window-key', { ...opts, now: 9_999 })
    expect(stillBlocked.allowed).toBe(false)

    // now both original timestamps (0, 1_000) have aged out of the 10s window
    const allowedAgain = checkRateLimit('window-key', { ...opts, now: 10_001 })
    expect(allowedAgain.allowed).toBe(true)
  })

  it('isolates counters per key', () => {
    const opts = { limit: 1, windowMs: 60_000, now: 1_000 }

    expect(checkRateLimit('key-a', opts).allowed).toBe(true)
    // key-a is now exhausted, but key-b must be unaffected
    expect(checkRateLimit('key-a', opts).allowed).toBe(false)
    expect(checkRateLimit('key-b', opts).allowed).toBe(true)
  })

  it('prunes timestamps outside the window on each call (bounded memory per key)', () => {
    const opts = { limit: 5, windowMs: 1_000 }

    checkRateLimit('prune-key', { ...opts, now: 0 })
    checkRateLimit('prune-key', { ...opts, now: 100 })
    // by now=2_000 both earlier timestamps are outside the 1s window and should be pruned,
    // so this call must be treated as the first request in a fresh window
    const result = checkRateLimit('prune-key', { ...opts, now: 2_000 })
    expect(result.allowed).toBe(true)
    expect(result.remaining).toBe(4)
  })

  it('evicts the oldest tracked key once the key cap is exceeded', () => {
    const opts = { limit: 1, windowMs: 60_000, now: 0 }

    // fill well past any reasonable cap; the oldest keys must fall out of tracking
    for (let i = 0; i < 6000; i++) {
      checkRateLimit(`bulk-key-${i}`, { ...opts, now: i })
    }

    // the very first key should have been evicted, so it is allowed again
    // even though it already "used" its single allowance above
    const firstKeyAgain = checkRateLimit('bulk-key-0', { limit: 1, windowMs: 60_000, now: 6000 })
    expect(firstKeyAgain.allowed).toBe(true)

    // a recently-added key should still be tracked and correctly rate-limited
    const recentKeyRepeat = checkRateLimit('bulk-key-5999', { limit: 1, windowMs: 60_000, now: 6001 })
    expect(recentKeyRepeat.allowed).toBe(false)
  })

  it('resetRateLimits clears all tracked state', () => {
    const opts = { limit: 1, windowMs: 60_000, now: 0 }

    expect(checkRateLimit('reset-key', opts).allowed).toBe(true)
    expect(checkRateLimit('reset-key', opts).allowed).toBe(false)

    resetRateLimits()

    expect(checkRateLimit('reset-key', opts).allowed).toBe(true)
  })
})
