import { describe, it, expect, beforeEach, vi } from 'vitest'

describe('ISR caching exports', () => {
  beforeEach(() => {
    vi.resetModules()
  })

  it('homepage does not export force-dynamic', async () => {
    const mod = await import('@/app/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('homepage exports revalidate = 60', async () => {
    const mod = await import('@/app/page')
    expect((mod as Record<string, unknown>).revalidate).toBe(60)
  })

  it('doi/confirm keeps force-dynamic for real-time token validation', async () => {
    const mod = await import('@/app/api/doi/confirm/route')
    expect((mod as Record<string, unknown>).dynamic).toBe('force-dynamic')
  })

  it('(cms)/[...slug] does not export force-dynamic', async () => {
    const mod = await import('@/app/(cms)/[...slug]/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('aktuelles does not export force-dynamic', async () => {
    const mod = await import('@/app/aktuelles/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('abos does not export force-dynamic', async () => {
    const mod = await import('@/app/abos/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('mitmachen does not export force-dynamic', async () => {
    const mod = await import('@/app/mitmachen/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('wir does not export force-dynamic', async () => {
    const mod = await import('@/app/wir/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('solawi does not export force-dynamic', async () => {
    const mod = await import('@/app/solawi/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('gemuese does not export force-dynamic', async () => {
    const mod = await import('@/app/gemuese/page')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })

  it('api/events does not export force-dynamic', async () => {
    const mod = await import('@/app/api/events/route')
    expect((mod as Record<string, unknown>).dynamic).not.toBe('force-dynamic')
  })
})
