/**
 * ProcessWire CMS Client
 * 
 * Handles communication with the ProcessWire CMS API at cms.bioco.ch
 * Uses X-API-Key authentication and ISR-compatible caching.
 */

// Default to cms.bioco.ch for the new unified API
const FALLBACK_BASE_URL = 'https://cms.bioco.ch'

function normalizeBaseUrl(value?: string | null): string {
  if (!value) return FALLBACK_BASE_URL
  return value.replace(/\/+$/, '')
}

const RESOLVED_BASE_URL = normalizeBaseUrl(
  process.env.PROCESSWIRE_BASE_URL ||
    process.env.PROCESSWIRE_API_URL ||
    process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL ||
    process.env.NEXT_PUBLIC_PROCESSWIRE_API_URL
)

export const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL ||
  (process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : 'http://localhost:3000')

// API Key for X-API-Key header authentication
const API_KEY = process.env.PROCESSWIRE_API_KEY || process.env.PROCESSWIRE_API_TOKEN

/**
 * Check if ProcessWire CMS is configured
 */
export function isProcessWireConfigured(): boolean {
  return Boolean(RESOLVED_BASE_URL)
}

/**
 * Build the full API URL for a given path
 * The unified API is at /api/ with URL segments for routing
 */
export function cmsApiUrl(path: string): string {
  const safePath = path.startsWith('/') ? path : `/${path}`
  // Unified API at /api/ level
  return `${RESOLVED_BASE_URL}/api${safePath}`
}

/**
 * Build headers for CMS requests
 * Uses X-API-Key header for authentication (not Bearer token)
 */
export function buildCmsHeaders(): HeadersInit {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  }
  
  if (API_KEY) {
    headers['X-API-Key'] = API_KEY
  }
  
  return headers
}

/**
 * Build fetch options with ISR revalidation
 */
export function cmsFetchOptions(revalidateSeconds: number): { next: { revalidate: number } } {
  return {
    next: { revalidate: revalidateSeconds },
  }
}

/**
 * Generic fetch function for CMS JSON endpoints
 * Includes error handling and ISR support
 */
export async function fetchCmsJson<T>(
  path: string,
  init?: RequestInit & { revalidate?: number }
): Promise<T> {
  const { revalidate = 60, ...requestInit } = init || {}
  
  const response = await fetch(cmsApiUrl(path), {
    ...requestInit,
    headers: {
      ...buildCmsHeaders(),
      ...(requestInit?.headers || {}),
    },
    ...cmsFetchOptions(revalidate),
  })

  if (!response.ok) {
    throw new Error(`ProcessWire API ${path} responded with ${response.status}`)
  }

  return (await response.json()) as T
}

/**
 * Fetch CMS content with graceful error handling
 * Returns null on failure instead of throwing
 */
export async function fetchCmsJsonSafe<T>(
  path: string,
  init?: RequestInit & { revalidate?: number }
): Promise<T | null> {
  try {
    return await fetchCmsJson<T>(path, init)
  } catch (error) {
    console.error(`CMS fetch error for ${path}:`, error)
    return null
  }
}

export const processwireBaseUrl = RESOLVED_BASE_URL
export const processwireApiKey = API_KEY
