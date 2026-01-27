import { NextRequest, NextResponse } from 'next/server'
import { sendFormEmail } from '@/lib/email'

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()

    // Validate required fields
    if (!body.name || !body.email) {
      return NextResponse.json(
        { success: false, error: 'Bitte füllen Sie alle Pflichtfelder aus.' },
        { status: 400 }
      )
    }

    // Send email via SMTP (same recipient as visit form)
    await sendFormEmail({
      formType: 'event-signup',
      data: body,
      subject: body.eventTitle ? `Event-Anmeldung: ${body.eventTitle}` : 'Neue Event-Anmeldung',
    })

    return NextResponse.json({ success: true })
  } catch (error) {
    console.error('Event signup form error:', error)
    return NextResponse.json(
      { success: false, error: 'Es ist ein Fehler aufgetreten.' },
      { status: 500 }
    )
  }
}
