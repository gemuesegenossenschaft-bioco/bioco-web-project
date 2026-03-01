import { Metadata } from 'next'
import { getHomepageContent, getAktuelles } from '@/lib/processwire'
import { getAktuellesItems } from '@/components/AktuellesData'
import type { AktuellesItem } from '@/components/AktuellesData'
import type { AktuellesNewsItem } from '@/lib/processwire-types'
import type { HeroContent } from '@/lib/processwire-types'
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

function mapNewsToAktuellesItem(item: AktuellesNewsItem): AktuellesItem {
  return {
    id: item.id,
    date: item.date,
    title: item.title,
    description: item.summary,
    type: 'aktuelles',
    fullDescription: item.body,
    imageUrl: item.image ?? undefined,
    url: item.url,
  }
}

export default async function Home() {
  const [cmsContent, cmsNews] = await Promise.all([
    getHomepageContent(),
    getAktuelles(10),
  ])
  
  const hero: HeroContent = cmsContent?.hero?.headline
    ? cmsContent.hero
    : { headline: '', subtitle: '', image: null, imageAlt: '' }

  const sections = cmsContent?.sections || []

  const aktuellesItems: AktuellesItem[] =
    cmsNews.length > 0 ? cmsNews.map(mapNewsToAktuellesItem) : getAktuellesItems()

  return (
    <HomeClient hero={hero} sections={sections} aktuellesItems={aktuellesItems} />
  )
}
