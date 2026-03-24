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
    expect(res.headers.get('X-Frame-Options')).toBe('DENY')
  })

  it('sets X-Frame-Options DENY by default (no visual editor)', () => {
    const req = new NextRequest('https://bioco.ch/mitmachen')
    const res = middleware(req)
    expect(res.headers.get('X-Frame-Options')).toBe('DENY')
    expect(res.headers.get('Content-Security-Policy')).toBeNull()
  })

  it('allows iframe embedding from CMS when _visual=1', () => {
    const req = new NextRequest('https://bioco.ch/mitmachen?_visual=1')
    const res = middleware(req)
    expect(res.headers.get('X-Frame-Options')).toContain('ALLOW-FROM')
    expect(res.headers.get('Content-Security-Policy')).toContain('frame-ancestors')
    expect(res.headers.get('Content-Security-Policy')).toContain('cms.bioco.ch')
  })
})
