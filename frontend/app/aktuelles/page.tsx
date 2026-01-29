import { getPageSections } from '@/lib/processwire'
import { FALLBACK_AKTUELLES_INTRO } from '@/lib/fallback-content'
import { AktuellesClient } from '@/components/AktuellesClient'
import { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Aktuelles & Events | biocò Gemüsegenossenschaft Baden',
  description: 'Neuigkeiten, Schnuppertage und Events der biocò Gemüsegenossenschaft. Erlebe solidarische Landwirtschaft auf dem Geisshof.',
  keywords: 'aktuelles, events, schnuppertage, bioco, solidarische landwirtschaft, baden, brugg',
  openGraph: {
    title: 'Aktuelles & Events | biocò Gemüsegenossenschaft Baden',
    description: 'Neuigkeiten, Schnuppertage und Events der biocò Gemüsegenossenschaft.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function AktuellesPage() {
  // Fetch CMS content
  const cmsSections = await getPageSections('aktuelles')

  return <AktuellesClient sections={cmsSections} />
}
