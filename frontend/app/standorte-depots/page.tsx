import { Metadata } from 'next'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'
import { getPageSectionsWithSeo } from '@/lib/processwire'

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

export const revalidate = 60

export default async function StandortePage() {
  const { sections } = await getPageSectionsWithSeo('standorte-depots')
  return <CmsVisualEditorPage sections={sections} />
}
