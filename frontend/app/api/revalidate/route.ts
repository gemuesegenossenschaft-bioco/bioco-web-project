import { NextResponse } from 'next/server'
import { revalidatePath } from 'next/cache'

export const dynamic = 'force-dynamic'

export async function POST(request: Request) {
  let body: { secret?: string; path?: string }
  try {
    body = await request.json()
  } catch {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const secret = body.secret
  if (!secret || secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const path = body.path
  if (path) {
    revalidatePath(path)
  } else {
    revalidatePath('/', 'layout')
  }

  return NextResponse.json({ revalidated: true, path: path || '/' })
}
