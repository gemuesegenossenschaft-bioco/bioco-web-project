import { Metadata } from 'next'
import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

export const metadata: Metadata = {
  title: 'Mitmachen bei solidarischer Landwirtschaft | biocò Baden',
  description: 'Werde Teil der Gemüsegenossenschaft biocò. Solidarische Landwirtschaft leben: Mitarbeit auf dem Geisshof und frisches Demeter-Gemüse für Baden-Brugg.',
  keywords: 'mitmachen, solidarische landwirtschaft, gemüsegenossenschaft, baden, brugg, mitarbeit, geisshof',
  openGraph: {
    title: 'Mitmachen bei solidarischer Landwirtschaft | biocò Baden',
    description: 'Werde Teil der Gemüsegenossenschaft biocò. Solidarische Landwirtschaft leben.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function MitmachenPage() {
  const cmsSections = await getPageSections('mitmachen')
  return <CmsVisualEditorPage sections={cmsSections} />
}
