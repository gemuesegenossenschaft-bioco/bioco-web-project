import { getHomepageContent } from '@/lib/processwire'
import { FALLBACK_HERO, FALLBACK_HOMEPAGE_SECTIONS, mergeSections } from '@/lib/fallback-content'
import { HomeClient } from '@/components/HomeClient'

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function Home() {
  // Fetch CMS content with fallback
  const cmsContent = await getHomepageContent()
  
  // Use CMS content if available, otherwise use fallbacks
  const hero = cmsContent?.hero?.headline 
    ? cmsContent.hero 
    : FALLBACK_HERO
  
  const sections = mergeSections(cmsContent?.sections, FALLBACK_HOMEPAGE_SECTIONS)

  return <HomeClient hero={hero} sections={sections} />
}
