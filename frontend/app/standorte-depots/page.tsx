import { Metadata } from 'next'
import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

export const metadata: Metadata = {
  title: 'Standorte & Depots Baden-Brugg | Gemüse abholen | biocò',
  description: 'Gemüseabholung in Baden, Brugg und Umgebung. Finde dein Depot für frisches Bio-Gemüse aus solidarischer Landwirtschaft vom Geisshof Gebenstorf.',
  keywords: 'depot, standorte, baden, brugg, gebenstorf, gemüseabholung, bio gemüse, geisshof',
  openGraph: {
    title: 'Standorte & Depots Baden-Brugg | Gemüse abholen | biocò',
    description: 'Gemüseabholung in Baden, Brugg und Umgebung. Finde dein Depot für frisches Bio-Gemüse.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function StandortePage() {
  const cmsSections = await getPageSections('standorte-depots')
  return <CmsVisualEditorPage sections={cmsSections} />
}
