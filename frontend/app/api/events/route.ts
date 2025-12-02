import { NextResponse } from 'next/server'

const CMS_API_URL =
  process.env.PROCESSWIRE_API_URL ?? process.env.NEXT_PUBLIC_PROCESSWIRE_API_URL
const CMS_API_TOKEN = process.env.PROCESSWIRE_API_TOKEN

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
  if (!CMS_API_URL) {
    console.warn('PROCESSWIRE_API_URL not configured, using fallback')
    return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
  }

  try {
    const response = await fetch(`${CMS_API_URL}/events`, {
      headers: CMS_API_TOKEN
        ? { Authorization: `Bearer ${CMS_API_TOKEN}` }
        : undefined,
      next: { revalidate: 300 },
      // Add timeout to fail fast
      signal: AbortSignal.timeout(5000),
    })

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
    
    return NextResponse.json(data, { status: 200 })
  } catch (error) {
    console.warn('Failed to fetch ProcessWire events, using fallback:', error)
    return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
  }
}

