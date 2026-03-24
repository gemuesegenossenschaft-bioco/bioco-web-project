import { NextRequest, NextResponse } from 'next/server'
import { sendFormEmail } from '@/lib/email'
import { verifyTurnstileToken } from '@/lib/turnstile'

const CAPTCHA_ERROR = 'Bitte bestätigen Sie, dass Sie kein Roboter sind.'

function getClientIp(request: NextRequest): string | null {
  const forwardedFor = request.headers.get('x-forwarded-for')
  if (!forwardedFor) return null
  return forwardedFor.split(',')[0]?.trim() || null
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()
    const captcha = await verifyTurnstileToken(body.captchaToken, getClientIp(request))

    if (!captcha.ok) {
      return NextResponse.json({ success: false, error: CAPTCHA_ERROR }, { status: 400 })
    }

    if (!body.firstName || !body.lastName || !body.email || !body.address || !body.zip || !body.city || !body.privacyAccept) {
      return NextResponse.json(
        { success: false, error: 'Bitte füllen Sie alle Pflichtfelder aus.' },
        { status: 400 }
      )
    }

    await sendFormEmail({
      formType: 'membership',
      data: body,
    })

    return NextResponse.json({ success: true })
  } catch (error: any) {
    console.error('Membership form error:', error)

    let errorMessage = 'Es ist ein Fehler aufgetreten.'
    if (error?.message) {
      if (error.message.includes('SMTP_PASS') || error.message.includes('SMTP')) {
        errorMessage = 'E-Mail-Konfiguration fehlt. Bitte kontaktieren Sie den Administrator.'
      } else if (error.message.includes('email') || error.message.includes('domain') || error.message.includes('SMTP')) {
        errorMessage = 'E-Mail konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.'
      } else {
        errorMessage = error.message
      }
    }

    return NextResponse.json(
      { success: false, error: errorMessage },
      { status: 500 }
    )
  }
}
