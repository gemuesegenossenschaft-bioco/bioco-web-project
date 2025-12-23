import { NextRequest, NextResponse } from 'next/server'
import { sendFormEmail } from '@/lib/email'

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()

    // Validate required fields
    if (!body.name || !body.email || !body.phone || !body.interest || !body.privacy_accept) {
      return NextResponse.json(
        { success: false, error: 'Bitte füllen Sie alle Pflichtfelder aus.' },
        { status: 400 }
      )
    }

    // Send email via SMTP
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
