import { NextRequest, NextResponse } from 'next/server'
import { sendFormEmail } from '@/lib/email'
import { buildIntranetSignupPayload, validateMembership } from '@/lib/membership'
import { forwardToIntranet } from '@/lib/intranetSignup'

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()

    const v = validateMembership(body)
    if (!v.ok) {
      return NextResponse.json(
        { success: false, error: 'Bitte füllen Sie alle Pflichtfelder aus.', fieldErrors: v.errors },
        { status: 400 }
      )
    }

    await sendFormEmail({
      formType: 'membership',
      data: body,
    })

    const responseBody: { success: true; forwarded?: boolean } = { success: true }

    // D.2b — best-effort forward to intranet.bioco.ch (system of record). This
    // must never fail the user's submission: the email above (#50 fallback)
    // already guarantees the signup isn't lost.
    if (process.env.INTRANET_SIGNUP_URL) {
      try {
        const intranetPayload = buildIntranetSignupPayload(body)
        const result = await forwardToIntranet(intranetPayload)
        responseBody.forwarded = result.ok
        if (!result.ok) {
          console.error('Intranet forward failed:', result.error || result.errors)
        }
      } catch (forwardError) {
        console.error('Intranet forward threw:', forwardError)
        responseBody.forwarded = false
      }
    }

    return NextResponse.json(responseBody)
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
