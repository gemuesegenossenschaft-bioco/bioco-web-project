import { Metadata } from 'next'
import { getHomepageContent } from '@/lib/processwire'
import { FALLBACK_HERO, FALLBACK_HOMEPAGE_SECTIONS, mergeSections } from '@/lib/fallback-content'
import { HomeClient } from '@/components/HomeClient'
import { generateMetadata as generateSeoMetadata } from '@/lib/seo'

// Static fallback metadata
const FALLBACK_METADATA: Metadata = {
  title: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
  description: 'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft. Wöchentliche Gemüsekörbe vom Geisshof in Gebenstorf.',
  openGraph: {
    title: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
    description: 'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft.',
    type: 'website',
  },
}

// Dynamic metadata from CMS
export async function generateMetadata(): Promise<Metadata> {
  const cmsContent = await getHomepageContent()
  
  if (cmsContent?.seo?.title || cmsContent?.seo?.description) {
    return generateSeoMetadata(cmsContent.seo, {
      title: FALLBACK_METADATA.title as string,
      description: FALLBACK_METADATA.description as string,
      path: '/',
    })
  }
  
  return FALLBACK_METADATA
}

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
