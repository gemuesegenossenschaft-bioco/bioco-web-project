import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

export function middleware(request: NextRequest) {
  const response = NextResponse.next()
  response.headers.set('X-Content-Type-Options', 'nosniff')
  response.headers.set('X-XSS-Protection', '1; mode=block')
  response.headers.set('Referrer-Policy', 'strict-origin-when-cross-origin')
  response.headers.set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')

  // Allow iframe embedding from CMS admin for visual editor
  const isVisualEditor = request.nextUrl.searchParams.get('_visual') === '1'
  if (isVisualEditor) {
    response.headers.set('X-Frame-Options', 'ALLOW-FROM https://cms.bioco.ch')
    response.headers.set(
      'Content-Security-Policy',
      "frame-ancestors 'self' https://cms.bioco.ch"
    )
  } else {
    response.headers.set('X-Frame-Options', 'DENY')
  }

  return response
}

export const config = {
  matcher: ['/((?!api|_next/static|_next/image|favicon.ico|images/|icons/).*)'],
}
