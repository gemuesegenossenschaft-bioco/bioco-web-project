const FALLBACK_BASE_URL = 'https://bioco.ch'

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

const API_TOKEN = process.env.PROCESSWIRE_API_TOKEN

export function cmsApiUrl(path: string): string {
  const safePath = path.startsWith('/') ? path : `/${path}`
  // API is at root /api/ level (not /site/api/)
  return `${RESOLVED_BASE_URL}/api${safePath}`
}

export function buildCmsHeaders(): HeadersInit | undefined {
  if (!API_TOKEN) return undefined
  return { Authorization: `Bearer ${API_TOKEN}` }
}

export function cmsFetchOptions(revalidateSeconds: number) {
  return {
    next: { revalidate: revalidateSeconds },
    // Removed cache: 'force-cache' to avoid conflict with revalidate
  }
}

export async function fetchCmsJson<T>(
  path: string,
  init?: RequestInit & { revalidate?: number }
): Promise<T> {
  const { revalidate, ...requestInit } = init || {}
  const response = await fetch(cmsApiUrl(path), {
    ...requestInit,
    headers: {
      ...(requestInit?.headers || {}),
      ...(buildCmsHeaders() || {}),
    },
    ...(revalidate ? cmsFetchOptions(revalidate) : {}),
  })

  if (!response.ok) {
    throw new Error(`ProcessWire API ${path} responded with ${response.status}`)
  }

  return (await response.json()) as T
}

export const processwireBaseUrl = RESOLVED_BASE_URL
export const processwireApiToken = API_TOKEN
