/**
 * ProcessWire API Functions
 * 
 * Typed fetch functions for all CMS content endpoints.
 * All functions return null/empty on failure for graceful degradation.
 */

import { fetchCmsJsonSafe, buildCmsHeaders, cmsApiUrl, cmsFetchOptions } from './cmsClient'
import type {
  PageData,
  PageIndexItem,
  PageIndexResponse,
  NavigationItem,
  HeroResponse,
  HomepageContent,
  SectionsResponse,
  GroupsResponse,
  GroupCard,
  ContentSection,
  EventsResponse,
  EventItem,
  AktuellesResponse,
  AktuellesNewsItem,
  InstagramResponse,
  InstagramPost,
  HealthResponse,
  SeoData,
  GlobalSettingsResponse,
  GlobalTypographySettings,
} from './processwire-types'

// Re-export types for convenience
export type { PageData, NavigationItem, ContentSection, GroupCard, EventItem, AktuellesNewsItem, SeoData }
export type { GlobalTypographySettings }

function normalizeMediaUrl(url?: string | null): string {
  const raw = String(url || '').trim()
  if (!raw) return ''
  try {
    const parsed = new URL(raw)
    let path = parsed.pathname
    // Fix duplicated CMS prefixes returned by ProcessWire on this setup.
    while (path.includes('/cms/cms/')) {
      path = path.replace('/cms/cms/', '/cms/')
    }
    // On cms.bioco.ch, media files are served from /site/assets, not /cms/site/assets.
    if (parsed.hostname === 'cms.bioco.ch' && path.startsWith('/cms/site/')) {
      path = path.replace(/^\/cms/, '')
    }
    parsed.pathname = path
    return encodeURI(parsed.toString())
  } catch {
    return encodeURI(raw)
  }
}

function normalizeSectionMedia(section: ContentSection): ContentSection {
  const seen = new Set<string>()
  const images: Array<{ url: string; alt: string }> = []

  function push(url?: string | null, alt?: string | null) {
    const safeUrl = normalizeMediaUrl(url)
    if (!safeUrl || seen.has(safeUrl)) return
    try {
      const path = new URL(safeUrl).pathname
      // Ignore directory URLs like ".../site/assets/files/1708/".
      if (path.endsWith('/')) return
    } catch {
      if (safeUrl.endsWith('/')) return
    }
    seen.add(safeUrl)
    images.push({ url: safeUrl, alt: String(alt || section.imageAlt || section.title || '').trim() || 'Bild' })
  }

  if (Array.isArray(section.images)) {
    section.images.forEach((img) => push(img?.url, img?.alt))
  }
  if (Array.isArray(section.media)) {
    section.media
      .filter((m) => m && m.type === 'image')
      .forEach((m) => push(m.url, m.alt))
  }
  push(section.image, section.imageAlt)

  const image = normalizeMediaUrl(section.image)
  if (images.length === 0) {
    return image ? { ...section, image } : section
  }
  return { ...section, image: image || section.image, images }
}

function normalizeSections(sections: ContentSection[]): ContentSection[] {
  if (!Array.isArray(sections) || sections.length === 0) return []
  return sections.map(normalizeSectionMedia)
}

// ============================================================================
// Health Check
// ============================================================================

/**
 * Check if CMS API is available
 */
export async function checkCmsHealth(): Promise<boolean> {
  const response = await fetchCmsJsonSafe<HealthResponse>('/health', { revalidate: 30 })
  return response?.status === 'ok'
}

// ============================================================================
// Homepage Content
// ============================================================================

/**
 * Get full homepage content (hero + sections)
 */
export async function getHomepageContent(): Promise<HomepageContent | null> {
  const data = await fetchCmsJsonSafe<HomepageContent>('/content/homepage', { revalidate: 60 })
  if (data && Array.isArray(data.sections)) {
    data.sections = normalizeSections(data.sections)
  }
  return data
}

/**
 * Get hero content only
 */
export async function getHeroContent(): Promise<HeroResponse | null> {
  return fetchCmsJsonSafe<HeroResponse>('/content/hero', { revalidate: 60 })
}

// ============================================================================
// Page Sections
// ============================================================================

/**
 * Get sections for a specific page
 */
export async function getPageSections(pageName: string): Promise<ContentSection[]> {
  const response = await fetchCmsJsonSafe<SectionsResponse>(
    `/content/sections/${encodeURIComponent(pageName)}`,
    { revalidate: 60 }
  )
  return normalizeSections(response?.sections || [])
}

/**
 * Get sections with SEO data for a specific page
 */
export async function getPageSectionsWithSeo(pageName: string): Promise<{ sections: ContentSection[], seo: SeoData | null }> {
  const response = await fetchCmsJsonSafe<SectionsResponse>(
    `/content/sections/${encodeURIComponent(pageName)}`,
    { revalidate: 60 }
  )
  return {
    sections: normalizeSections(response?.sections || []),
    seo: response?.seo || null,
  }
}

// ============================================================================
// Group Cards (Mitmachen)
// ============================================================================

/**
 * Get group cards for Mitmachen page
 */
export async function getGroupCards(): Promise<GroupCard[]> {
  const response = await fetchCmsJsonSafe<GroupsResponse>('/content/groups', { revalidate: 60 })
  return response?.groups || []
}

// ============================================================================
// Generic Page Data
// ============================================================================

/**
 * Get page data by path (legacy endpoint, now via unified API)
 */
export async function getPageData(path: string): Promise<PageData | null> {
  return fetchCmsJsonSafe<PageData>(
    `/content/page?path=${encodeURIComponent(path || '/')}`,
    { revalidate: 600 }
  )
}

/**
 * Get all CMS pages for static params
 */
export async function getAllPages(): Promise<PageIndexItem[]> {
  const response = await fetchCmsJsonSafe<PageIndexResponse>('/content/pages', { revalidate: 600 })
  return response?.items || []
}

// ============================================================================
// Navigation
// ============================================================================

/**
 * Get site navigation items
 */
export async function getNavigation(): Promise<NavigationItem[]> {
  const response = await fetchCmsJsonSafe<NavigationItem[]>('/content/navigation', { revalidate: 1800 })
  return response || []
}

// ============================================================================
// Global Settings
// ============================================================================

/**
 * Get global site settings (typography tokens)
 */
export async function getGlobalSettings(): Promise<GlobalTypographySettings | null> {
  const response = await fetchCmsJsonSafe<GlobalSettingsResponse>('/content/settings', { revalidate: 60 })
  return response?.settings?.typography || null
}

// ============================================================================
// Events
// ============================================================================

/**
 * Get all events (upcoming and past)
 */
export async function getEvents(): Promise<EventsResponse | null> {
  return fetchCmsJsonSafe<EventsResponse>('/content/events', { revalidate: 60 })
}

/**
 * Get upcoming events only
 */
export async function getUpcomingEvents(limit?: number): Promise<EventItem[]> {
  const response = await getEvents()
  const upcoming = response?.upcoming || []
  return limit ? upcoming.slice(0, limit) : upcoming
}

/**
 * Get past events only
 */
export async function getPastEvents(limit?: number): Promise<EventItem[]> {
  const response = await getEvents()
  const past = response?.past || []
  return limit ? past.slice(0, limit) : past
}

// ============================================================================
// Aktuelles / News
// ============================================================================

/**
 * Get news/aktuelles items
 */
export async function getAktuelles(limit: number = 10): Promise<AktuellesNewsItem[]> {
  const response = await fetchCmsJsonSafe<AktuellesResponse>(
    `/content/aktuelles?limit=${limit}`,
    { revalidate: 60 }
  )
  return response?.items || []
}

// ============================================================================
// Instagram
// ============================================================================

/**
 * Get Instagram posts
 */
export async function getInstagramPosts(limit: number = 10): Promise<InstagramPost[]> {
  const response = await fetchCmsJsonSafe<InstagramResponse>(
    `/content/instagram?limit=${limit}`,
    { revalidate: 300 }
  )
  return response?.posts || []
}

// ============================================================================
// Content Helpers
// ============================================================================

/**
 * Get page content by slug (convenience wrapper)
 * Returns sections for pages like mitmachen, gemuese, solawi
 */
export async function getPageContent(slug: string): Promise<{
  sections: ContentSection[]
  pageData: PageData | null
  seo: SeoData | null
}> {
  const [sectionsData, pageData] = await Promise.all([
    getPageSectionsWithSeo(slug),
    getPageData(`/${slug}/`),
  ])
  
  // Prefer SEO from sections endpoint, fallback to pageData
  const seo = sectionsData.seo || pageData?.seo || null

  const pageSections = Array.isArray(pageData?.sections) ? pageData?.sections : []
  const sections = sectionsData.sections.length > 0 ? sectionsData.sections : normalizeSections(pageSections as ContentSection[])
  
  return { sections, pageData, seo }
}
