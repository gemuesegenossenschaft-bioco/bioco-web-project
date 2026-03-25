import { Metadata } from 'next'
import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

export const metadata: Metadata = {
  title: 'Über uns | Bio Bauernhof Baden | biocò Gemüsegenossenschaft',
  description: 'biocò Gemüsegenossenschaft: Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof Gebenstorf. Demeter-zertifiziertes Gemüse für Baden-Brugg.',
  keywords: 'bio bauernhof, solidarische landwirtschaft, gemüsegenossenschaft, baden, brugg, gebenstorf, demeter, geisshof',
  openGraph: {
    title: 'Über uns | Solidarische Landwirtschaft Baden | biocò',
    description: 'Seit 2014 solidarische Landwirtschaft auf dem Geisshof Gebenstorf. Demeter-zertifiziertes Bio-Gemüse für Baden-Brugg.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function WirPage() {
  const cmsSections = await getPageSections('wir')
  return <CmsVisualEditorPage sections={cmsSections} />
}
