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
} from './processwire-types'

// Re-export types for convenience
export type { PageData, NavigationItem, ContentSection, GroupCard, EventItem, AktuellesNewsItem, SeoData }

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
  return fetchCmsJsonSafe<HomepageContent>('/content/homepage', { revalidate: 60 })
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
  return response?.sections || []
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
    sections: response?.sections || [],
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
  const sections = sectionsData.sections.length > 0 ? sectionsData.sections : (pageSections as ContentSection[])
  
  return { sections, pageData, seo }
}
