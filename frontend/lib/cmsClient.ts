import { draftMode } from 'next/headers'

const FALLBACK_BASE_URL = 'https://cms.bioco.ch'

function normalizeBaseUrl(value?: string | null): string {
  if (!value) return FALLBACK_BASE_URL
  return value.replace(/\/+$/, '')
}

function isLocalCmsUrl(value: string): boolean {
  const v = (value || '').toLowerCase()
  return v.includes('localhost') || v.includes('127.0.0.1')
}

const baseCandidates = [
  process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL,
  process.env.NEXT_PUBLIC_PROCESSWIRE_API_URL,
  process.env.PROCESSWIRE_BASE_URL,
  process.env.PROCESSWIRE_API_URL,
]

const firstUsableBaseUrl =
  baseCandidates.find((v) => {
    if (!v) return false
    if (process.env.NODE_ENV === 'production' && isLocalCmsUrl(v)) return false
    return true
  }) || FALLBACK_BASE_URL

const RESOLVED_BASE_URL = normalizeBaseUrl(firstUsableBaseUrl)

export const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL || 'https://bioco.ch'

const API_KEY = process.env.PROCESSWIRE_API_KEY || process.env.PROCESSWIRE_API_TOKEN

export function cmsApiUrl(path: string): string {
  const safePath = path.startsWith('/') ? path : `/${path}`
  // API is at root /api/ level (not /site/api/)
  return `${RESOLVED_BASE_URL}/api${safePath}`
}

export function buildCmsHeaders(): HeadersInit | undefined {
  if (!API_KEY) return undefined
  return { 'X-API-Key': API_KEY }
}

export function cmsFetchOptions(revalidateSeconds: number) {
  return {
    next: { revalidate: revalidateSeconds },
    // Removed cache: 'force-cache' to avoid conflict with revalidate
  }
}

function getDraftUrl(url: string): string {
  try {
    const { isEnabled } = draftMode()
    if (isEnabled && process.env.DRAFT_SECRET) {
      const sep = url.includes('?') ? '&' : '?'
      return `${url}${sep}preview_token=${process.env.DRAFT_SECRET}`
    }
  } catch {
    // draftMode() throws outside of request context (e.g. build time)
  }
  return url
}

export async function fetchCmsJson<T>(
  path: string,
  init?: RequestInit & { revalidate?: number }
): Promise<T> {
  const { revalidate, ...requestInit } = init || {}
  const url = getDraftUrl(cmsApiUrl(path))
  const response = await fetch(url, {
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

export async function fetchCmsJsonSafe<T>(
  path: string,
  init?: RequestInit & { revalidate?: number }
): Promise<T | null> {
  try {
    return await fetchCmsJson<T>(path, init)
  } catch {
    return null
  }
}

export const processwireBaseUrl = RESOLVED_BASE_URL
export const processwireApiKey = API_KEY
