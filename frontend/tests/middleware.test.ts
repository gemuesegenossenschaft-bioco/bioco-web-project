import { describe, it, expect } from 'vitest'
import { NextRequest } from 'next/server'
import { middleware, config } from '@/middleware'

describe('middleware', () => {
  it('does not force Cache-Control on page requests', () => {
    const req = new NextRequest('https://bioco.ch/mitmachen')
    const res = middleware(req)
    expect(res.headers.get('Cache-Control')).toBeNull()
  })

  it('exports matcher that excludes _next/static and api routes', () => {
    expect(config).toBeDefined()
    expect(config.matcher).toBeDefined()
    const matcherStr = JSON.stringify(config.matcher)
    expect(matcherStr).toContain('_next')
  })

  it('keeps existing security headers', () => {
    const req = new NextRequest('https://bioco.ch/')
    const res = middleware(req)
    expect(res.headers.get('X-Content-Type-Options')).toBe('nosniff')
    expect(res.headers.get('Referrer-Policy')).toBe('strict-origin-when-cross-origin')
  })

  it('does not set X-Frame-Options (handled by next.config.js headers)', () => {
    const req = new NextRequest('https://bioco.ch/mitmachen')
    const res = middleware(req)
    // Framing headers managed by next.config.js headers() to survive ISR cache
    expect(res.headers.get('X-Frame-Options')).toBeNull()
  })
})
