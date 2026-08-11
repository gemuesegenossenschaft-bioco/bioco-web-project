import { NextRequest, NextResponse } from 'next/server'
import { sendFormEmail } from '@/lib/email'
import { RATE_LIMITS, RATE_LIMIT_ERROR_MESSAGE, checkRateLimit, rateLimitKeyFromRequest } from '@/lib/rateLimit'

export async function POST(request: NextRequest) {
  const rateLimit = checkRateLimit(rateLimitKeyFromRequest(request, 'subscribe'), RATE_LIMITS.subscribe)
  if (!rateLimit.allowed) {
    return NextResponse.json(
      { success: false, error: RATE_LIMIT_ERROR_MESSAGE },
      { status: 429, headers: { 'Retry-After': String(rateLimit.retryAfterSeconds) } }
    )
  }

  try {
    const body = await request.json()

    if (!body.email || !body.privacy_accept) {
      return NextResponse.json(
        { success: false, error: 'Bitte füllen Sie alle Pflichtfelder aus.' },
        { status: 400 }
      )
    }

    await sendFormEmail({
      formType: 'subscribe',
      data: body,
    })

    return NextResponse.json({ success: true })
  } catch (error) {
    console.error('Subscribe form error:', error)
    return NextResponse.json(
      { success: false, error: 'Es ist ein Fehler aufgetreten.' },
      { status: 500 }
    )
  }
}
