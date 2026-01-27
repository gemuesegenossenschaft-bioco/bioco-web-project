import { cmsApiUrl, cmsFetchOptions } from './cmsClient'

export interface PageData {
  id: number
  title: string
  url: string
  template?: string
  body?: string
  hero_title?: string
  hero_subtitle?: string
  summary?: string
  cta_text?: string
  cta_url?: string
  logo_image?: {
    url: string
    description: string
  }
  hero_image?: {
    url: string
    description: string
  }
  sidebar_content?: string
  gallery_images?: Array<{
    url: string
    description: string
  }>
  footer_content?: string
  css_variant?: string
  sections?: Array<{
    id?: string
    title?: string
    content?: string
  }>
  children?: PageData[]
}

export async function getPageData(path: string): Promise<PageData | null> {
  try {
    const response = await fetch(
      cmsApiUrl(`/pages.php?path=${encodeURIComponent(path || '/')}`),
      cmsFetchOptions(600)
    )
    
    if (!response.ok) {
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
    const response = await fetch(cmsApiUrl('/navigation.php'), cmsFetchOptions(1800))
    
    if (!response.ok) {
      return []
    }
    
    return await response.json()
  } catch (error) {
    console.error('Error fetching navigation:', error)
    return []
  }
}
