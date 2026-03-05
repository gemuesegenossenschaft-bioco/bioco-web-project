import { NextResponse } from 'next/server'
import { revalidatePath, revalidateTag } from 'next/cache'

export const dynamic = 'force-dynamic'

export async function POST(request: Request) {
  let body: {
    secret?: string
    path?: string
    paths?: string[]
    tag?: string
    tags?: string[]
    layout?: boolean
  }
  try {
    body = await request.json()
  } catch {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const secret = body.secret
  if (!secret || secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const normalizePath = (value?: string) => {
    if (!value || typeof value !== 'string') return null
    const trimmed = value.trim()
    if (!trimmed) return null
    return trimmed.startsWith('/') ? trimmed : `/${trimmed}`
  }

  const paths = [
    normalizePath(body.path),
    ...(Array.isArray(body.paths) ? body.paths.map(normalizePath) : []),
  ].filter((v): v is string => Boolean(v))

  const uniquePaths = paths.filter((path, index) => paths.indexOf(path) === index)
  for (const path of uniquePaths) {
    revalidatePath(path)
  }

  if (body.layout || uniquePaths.length === 0) {
    revalidatePath('/', 'layout')
  }

  const tags = [
    body.tag,
    ...(Array.isArray(body.tags) ? body.tags : []),
  ]
    .map((tag) => (typeof tag === 'string' ? tag.trim() : ''))
    .filter((tag) => tag.length > 0)

  const uniqueTags = tags.filter((tag, index) => tags.indexOf(tag) === index)
  for (const tag of uniqueTags) {
    revalidateTag(tag)
  }

  return NextResponse.json({
    revalidated: true,
    paths: uniquePaths,
    layout: Boolean(body.layout || uniquePaths.length === 0),
    tags: uniqueTags,
  })
}
