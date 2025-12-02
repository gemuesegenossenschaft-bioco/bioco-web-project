import { NextResponse } from 'next/server'

export async function GET() {
  try {
    // Fetch Instagram posts from ProcessWire API
    const baseUrl = process.env.NEXT_PUBLIC_PROCESSWIRE_URL || 'https://bioco.ch'
    const response = await fetch(`${baseUrl}/site/api/instagram.php`, {
      next: { revalidate: 3600 } // Revalidate every hour
    })

    if (!response.ok) {
      throw new Error('Failed to fetch Instagram posts')
    }

    const data = await response.json()
    return NextResponse.json(data)
  } catch (error) {
    console.error('Error fetching Instagram posts:', error)
    // Return empty array on error, frontend will show static content
    return NextResponse.json({ success: false, posts: [], count: 0 })
  }
}










