// ProcessWire API client for headless CMS

const API_URL = process.env.NEXT_PUBLIC_PROCESSWIRE_API_URL || process.env.PROCESSWIRE_API_URL || 'http://localhost/api'

export interface PageSection {
  id?: string
  title?: string
  content?: string
}

export interface PageImage {
  url: string
  description: string
  width?: number
  height?: number
}

export interface PageData {
  id: number
  title: string
  url: string
  template?: string
  body?: string
  hero_title?: string
  hero_subtitle?: string
  summary?: string
  logo_image?: PageImage
  hero_image?: PageImage
  sidebar_content?: string
  gallery_images?: PageImage[]
  footer_content?: string
  css_variant?: string
  sections?: PageSection[]
  children?: PageData[]
}

export async function getPageData(path: string): Promise<PageData | null> {
  try {
    // Ensure path has proper format for API
    const apiPath = path.startsWith('/') ? path : `/${path}`
    const response = await fetch(`${API_URL}/pages?path=${encodeURIComponent(apiPath)}`, {
      next: { revalidate: 60 }, // Revalidate every 60 seconds
    })
    
    if (!response.ok) {
      console.error('API response not ok:', response.status)
      return null
    }
    
    return await response.json()
  } catch (error) {
    console.error('Error fetching page data:', error)
    return null
  }
}

export async function getNavigation(): Promise<PageData[]> {
  try {
    const response = await fetch(`${API_URL}/navigation`, {
      next: { revalidate: 300 }, // Revalidate every 5 minutes
    })
    
    if (!response.ok) {
      return []
    }
    
    return await response.json()
  } catch (error) {
    console.error('Error fetching navigation:', error)
    return []
  }
}
