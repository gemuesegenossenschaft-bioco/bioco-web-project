import { Metadata } from 'next'
import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

export const metadata: Metadata = {
  title: 'biocò werden | Mitglied werden | biocò Baden',
  description: 'Werde Mitglied der Gemüsegenossenschaft biocò. Wähle dein Gemüseabo und werde Teil unserer solidarischen Landwirtschaft.',
  keywords: 'biocò werden, mitglied werden, gemüseabo, anmeldung, solidarische landwirtschaft',
  openGraph: {
    title: 'biocò werden | Mitglied werden | biocò Baden',
    description: 'Werde Mitglied der Gemüsegenossenschaft biocò.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function BiocoWerdenPage() {
  const cmsSections = await getPageSections('bioco-werden')
  return <CmsVisualEditorPage sections={cmsSections} />
}
