import { NextResponse } from 'next/server'
import { buildCmsHeaders, cmsApiUrl, cmsFetchOptions } from '@/lib/cmsClient'

export const revalidate = 300
export const dynamic = 'force-static'

// Fallback response when API is unavailable
const FALLBACK_RESPONSE = {
  success: true,
  generatedAt: new Date().toISOString(),
  upcoming: [],
  past: [],
  fallback: true,
}

export async function GET() {
  // If API URL not configured, return empty but successful response
  if (!process.env.PROCESSWIRE_BASE_URL && !process.env.PROCESSWIRE_API_URL) {
    console.warn('PROCESSWIRE base URL not configured, using fallback')
    return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
  }

  try {
    const response = await fetch(
      cmsApiUrl('/events.php'),
      {
        ...cmsFetchOptions(revalidate),
        headers: buildCmsHeaders(),
        signal: AbortSignal.timeout(5000),
      }
    )

    if (!response.ok) {
      console.warn(`Events API returned ${response.status}, using fallback`)
      return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
    }

    const data = await response.json()
    
    // Validate response structure
    if (!data || typeof data !== 'object' || !Array.isArray(data.upcoming)) {
      console.warn('Invalid events API response structure, using fallback')
      return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
    }
    
    const res = NextResponse.json(data, { status: 200 })
    res.headers.set('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=300')
    return res
  } catch (error) {
    console.warn('Failed to fetch ProcessWire events, using fallback:', error)
    return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
  }
}

