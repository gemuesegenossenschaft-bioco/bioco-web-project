import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

export function middleware(request: NextRequest) {
  const response = NextResponse.next()

  response.headers.set('X-Content-Type-Options', 'nosniff')
  response.headers.set('X-XSS-Protection', '1; mode=block')
  response.headers.set('Referrer-Policy', 'strict-origin-when-cross-origin')
  response.headers.set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')

  // Framing protection via next.config.js headers() (works with ISR cache)
  // Middleware headers don't survive ISR cache hits in Next.js 14

  return response
}

export const config = {
  matcher: ['/((?!api|_next/static|_next/image|favicon.ico|images/|icons/).*)'],
}
