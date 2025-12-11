import { NextRequest, NextResponse } from 'next/server'
import { buildCmsHeaders, cmsApiUrl } from '@/lib/cmsClient'

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams
    const token = searchParams.get('token')

    if (!token) {
      return NextResponse.json(
        { success: false, error: 'Kein Token angegeben.' },
        { status: 400 }
      )
    }

    // Send to ProcessWire API
    const response = await fetch(
      cmsApiUrl(`/doi.php/confirm?token=${encodeURIComponent(token)}`),
      {
        method: 'GET',
        headers: buildCmsHeaders(),
      }
    )

    const data = await response.json()

    if (data.success) {
      return NextResponse.json({
        success: true,
        form_type: data.form_type,
      })
    } else {
      return NextResponse.json(
        { success: false, error: data.error || 'Ungültiger oder abgelaufener Bestätigungslink.' },
        { status: 400 }
      )
    }
  } catch (error) {
    console.error('DOI confirmation error:', error)
    return NextResponse.json(
      { success: false, error: 'Es ist ein Fehler aufgetreten.' },
      { status: 500 }
    )
  }
}
