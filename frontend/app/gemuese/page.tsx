import { Metadata } from 'next'
import { getPageSectionsWithSeo } from '@/lib/processwire'
import { generateMetadata as generateSeoMetadata } from '@/lib/seo'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

// Static fallback metadata (used when CMS data unavailable)
const FALLBACK_METADATA: Metadata = {
  title: 'Saisonales Demeter Gemüse | Welche Gemüse haben gerade Saison | biocò',
  description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf für die Region Baden-Brugg.',
  keywords: 'demeter gemüse, bio gemüse, saisonales gemüse, gebenstorf, baden, brugg, gemüseernte',
  openGraph: {
    title: 'Saisonales Demeter Gemüse | Welche Gemüse haben gerade Saison | biocò',
    description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf.',
    type: 'website',
  },
}

// Dynamic metadata from CMS
export async function generateMetadata(): Promise<Metadata> {
  const { seo } = await getPageSectionsWithSeo('gemuese')

  // If CMS has SEO data, use it; otherwise use fallback
  if (seo?.title || seo?.description) {
    return generateSeoMetadata(seo, {
      title: FALLBACK_METADATA.title as string,
      description: FALLBACK_METADATA.description as string,
      path: '/gemuese',
    })
  }

  return FALLBACK_METADATA
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function GemusePage() {
  const { sections: cmsSections } = await getPageSectionsWithSeo('gemuese')
  return <CmsVisualEditorPage sections={cmsSections} />
}
