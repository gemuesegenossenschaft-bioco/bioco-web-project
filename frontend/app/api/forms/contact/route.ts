import { NextRequest, NextResponse } from 'next/server'
import { buildCmsHeaders, cmsApiUrl } from '@/lib/cmsClient'

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()

    // Validate required fields
    if (!body.name || !body.email || !body.subject || !body.message) {
      return NextResponse.json(
        { success: false, error: 'Bitte füllen Sie alle Pflichtfelder aus.' },
        { status: 400 }
      )
    }

    // Send to ProcessWire API
    const response = await fetch(cmsApiUrl('/forms.php/contact'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(buildCmsHeaders() || {}),
      },
      body: JSON.stringify(body),
    })

    const data = await response.json()

    if (data.success) {
      return NextResponse.json({ success: true })
    } else {
      return NextResponse.json(
        { success: false, error: data.error || 'Es ist ein Fehler aufgetreten.' },
        { status: 400 }
      )
    }
  } catch (error) {
    console.error('Contact form error:', error)
    return NextResponse.json(
      { success: false, error: 'Es ist ein Fehler aufgetreten.' },
      { status: 500 }
    )
  }
}
