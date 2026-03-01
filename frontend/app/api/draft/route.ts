import { draftMode } from 'next/headers'
import { NextResponse } from 'next/server'

export const dynamic = 'force-dynamic'

export async function GET(request: Request) {
  const { searchParams, origin } = new URL(request.url)
  const secret = searchParams.get('secret')
  const path = searchParams.get('path') || '/'
  const disable = searchParams.get('disable')

  if (!secret || secret !== process.env.DRAFT_SECRET) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const draft = draftMode()

  if (disable === '1') {
    draft.disable()
    return NextResponse.json({ disabled: true })
  }

  draft.enable()
  return NextResponse.redirect(new URL(path, origin), 307)
}
