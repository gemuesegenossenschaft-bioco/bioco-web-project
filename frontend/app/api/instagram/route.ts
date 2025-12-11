import { NextResponse } from 'next/server'
import { buildCmsHeaders, cmsApiUrl, cmsFetchOptions } from '@/lib/cmsClient'

export const revalidate = 3600
export const dynamic = 'force-static'

export async function GET() {
  try {
    const response = await fetch(cmsApiUrl('/instagram.php'), {
      ...cmsFetchOptions(revalidate),
      headers: buildCmsHeaders(),
    })

    if (!response.ok) {
      throw new Error('Failed to fetch Instagram posts')
    }

    const data = await response.json()
    const res = NextResponse.json(data)
    res.headers.set('Cache-Control', 'public, s-maxage=3600, stale-while-revalidate=3600')
    return res
  } catch (error) {
    console.error('Error fetching Instagram posts:', error)
    // Return empty array on error, frontend will show static content
    return NextResponse.json({ success: false, posts: [], count: 0 })
  }
}

















