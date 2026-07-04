import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function ImpressumPage() {
  const cmsSections = await getPageSections('impressum')
  return <CmsVisualEditorPage sections={cmsSections} />
}
