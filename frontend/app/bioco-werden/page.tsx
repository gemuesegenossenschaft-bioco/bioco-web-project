import { Metadata } from 'next'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'
import { getPageSectionsWithSeo } from '@/lib/processwire'

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

export const revalidate = 60

export default async function BiocoWerdenPage() {
  const { sections } = await getPageSectionsWithSeo('bioco-werden')
  return <CmsVisualEditorPage sections={sections} />
}
