import { MetadataRoute } from 'next'
import { SITE_URL } from '@/lib/cmsClient'
import { getAllPages } from '@/lib/processwire'

// Core pages with explicit priority
const CORE_PAGES: Record<string, { changeFrequency: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never'; priority: number }> = {
  '/': { changeFrequency: 'weekly', priority: 1.0 },
  '/abos': { changeFrequency: 'monthly', priority: 0.9 },
  '/gemuese': { changeFrequency: 'weekly', priority: 0.8 },
  '/mitmachen': { changeFrequency: 'monthly', priority: 0.8 },
  '/standorte-depots': { changeFrequency: 'monthly', priority: 0.8 },
  '/wir': { changeFrequency: 'monthly', priority: 0.7 },
  '/solawi': { changeFrequency: 'monthly', priority: 0.7 },
  '/aktuelles': { changeFrequency: 'weekly', priority: 0.6 },
  '/kontakt': { changeFrequency: 'yearly', priority: 0.5 },
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const baseUrl = SITE_URL || 'https://bioco.ch'
  
  // Start with core pages
  const entries: MetadataRoute.Sitemap = Object.entries(CORE_PAGES).map(([path, config]) => ({
    url: `${baseUrl}${path === '/' ? '' : path}`,
    lastModified: new Date(),
    changeFrequency: config.changeFrequency,
    priority: config.priority,
  }))

  // Fetch dynamic CMS pages
  try {
    const cmsPages = await getAllPages()
    
    for (const page of cmsPages) {
      // Skip if already in core pages or is root
      if (CORE_PAGES[page.path] || page.path === '/') {
        continue
      }
      
      // Skip admin, api, content paths
      if (page.path.startsWith('/admin') || page.path.startsWith('/api') || page.path.startsWith('/content')) {
        continue
      }

      entries.push({
        url: `${baseUrl}${page.path}`,
        lastModified: new Date(),
        changeFrequency: 'monthly',
        priority: 0.5,
      })
    }
  } catch (error) {
    console.error('Failed to fetch CMS pages for sitemap:', error)
  }

  return entries
}
