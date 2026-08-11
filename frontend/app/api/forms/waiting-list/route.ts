import { NextRequest, NextResponse } from 'next/server'
import { sendFormEmail } from '@/lib/email'
import { verifyTurnstileToken } from '@/lib/turnstile'
import { RATE_LIMITS, RATE_LIMIT_ERROR_MESSAGE, checkRateLimit, rateLimitKeyFromRequest } from '@/lib/rateLimit'

const CAPTCHA_ERROR = 'Bitte bestätigen Sie, dass Sie kein Roboter sind.'

function getClientIp(request: NextRequest): string | null {
  const forwardedFor = request.headers.get('x-forwarded-for')
  if (!forwardedFor) return null
  return forwardedFor.split(',')[0]?.trim() || null
}

export async function POST(request: NextRequest) {
  const rateLimit = checkRateLimit(rateLimitKeyFromRequest(request, 'waiting-list'), RATE_LIMITS['waiting-list'])
  if (!rateLimit.allowed) {
    return NextResponse.json(
      { success: false, error: RATE_LIMIT_ERROR_MESSAGE },
      { status: 429, headers: { 'Retry-After': String(rateLimit.retryAfterSeconds) } }
    )
  }

  try {
    const body = await request.json()
    const captcha = await verifyTurnstileToken(body.captchaToken, getClientIp(request))

    if (!captcha.ok) {
      return NextResponse.json({ success: false, error: CAPTCHA_ERROR }, { status: 400 })
    }

    if (!body.name || !body.email || !body.phone || !body.interest || !body.privacy_accept) {
      return NextResponse.json(
        { success: false, error: 'Bitte füllen Sie alle Pflichtfelder aus.' },
        { status: 400 }
      )
    }

    await sendFormEmail({
      formType: 'waiting-list',
      data: body,
    })

    return NextResponse.json({ success: true })
  } catch (error) {
    console.error('Waiting list form error:', error)
    return NextResponse.json(
      { success: false, error: 'Es ist ein Fehler aufgetreten.' },
      { status: 500 }
    )
  }
}
