import { Metadata } from 'next'
import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

export const metadata: Metadata = {
  title: 'Was ist Solidarische Landwirtschaft (SoLaWi)? | biocò',
  description: 'Solidarische Landwirtschaft (Solawi/SoLaWi): Gemeinsam Verantwortung tragen für regionales Bio-Gemüse. Erfahre mehr über unser Konzept auf dem Geisshof.',
  keywords: 'solidarische landwirtschaft, solawi, solawi konzept, wie funktioniert solawi, gemüsegenossenschaft, csa, community supported agriculture',
  openGraph: {
    title: 'Was ist Solidarische Landwirtschaft (SoLaWi)? | biocò',
    description: 'Solidarische Landwirtschaft: Gemeinsam Verantwortung tragen für regionales Bio-Gemüse.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function SolawiPage() {
  const cmsSections = await getPageSections('solawi')
  return <CmsVisualEditorPage sections={cmsSections} />
}
