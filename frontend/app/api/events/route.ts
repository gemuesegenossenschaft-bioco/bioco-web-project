import { NextResponse } from 'next/server'
import { buildCmsHeaders, cmsApiUrl, cmsFetchOptions } from '@/lib/cmsClient'

export const revalidate = 60

// Fallback response when API is unavailable
const FALLBACK_RESPONSE = {
  success: true,
  generatedAt: new Date().toISOString(),
  upcoming: [],
  past: [],
  fallback: true,
}

function isValidEventsResponse(data: unknown): data is { upcoming: unknown[]; past: unknown[] } {
  if (!data || typeof data !== 'object') return false
  const candidate = data as { upcoming?: unknown; past?: unknown }
  return Array.isArray(candidate.upcoming) && Array.isArray(candidate.past)
}

async function fetchEventsFromCms() {
  // Current CMS endpoint first; keep legacy endpoints for compatibility.
  const endpoints = ['/content/events', '/events/', '/events.php']

  for (const endpoint of endpoints) {
    try {
      const response = await fetch(cmsApiUrl(endpoint), {
        ...cmsFetchOptions(revalidate),
        headers: buildCmsHeaders(),
        signal: createTimeoutSignal(5000),
      })

      if (!response.ok) {
        console.warn(`Events API ${endpoint} returned ${response.status}`)
        continue
      }

      const data = await response.json()
      if (!isValidEventsResponse(data)) {
        console.warn(`Events API ${endpoint} returned invalid structure`)
        continue
      }

      return data
    } catch (error) {
      console.warn(`Events API ${endpoint} failed`, error)
    }
  }

  return null
}

function createTimeoutSignal(timeoutMs: number): AbortSignal {
  const abortSignalWithTimeout = AbortSignal as typeof AbortSignal & {
    timeout?: (ms: number) => AbortSignal
  }

  if (typeof abortSignalWithTimeout.timeout === 'function') {
    return abortSignalWithTimeout.timeout(timeoutMs)
  }

  const controller = new AbortController()
  setTimeout(() => controller.abort(), timeoutMs)
  return controller.signal
}

export async function GET() {
  // If API URL not configured, return empty but successful response
  if (!process.env.PROCESSWIRE_BASE_URL && !process.env.PROCESSWIRE_API_URL) {
    console.warn('PROCESSWIRE base URL not configured, using fallback')
    return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
  }

  try {
    const data = await fetchEventsFromCms()
    if (!data) {
      console.warn('All events API endpoints failed, using fallback')
      return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
    }

    const res = NextResponse.json(data, { status: 200 })
    res.headers.set('Cache-Control', 'public, s-maxage=60, stale-while-revalidate=60')
    return res
  } catch (error) {
    console.warn('Failed to fetch ProcessWire events, using fallback:', error)
    return NextResponse.json(FALLBACK_RESPONSE, { status: 200 })
  }
}
