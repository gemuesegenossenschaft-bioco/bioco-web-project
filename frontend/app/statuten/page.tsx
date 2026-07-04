import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function StatutenPage() {
  const cmsSections = await getPageSections('statuten')
  return <CmsVisualEditorPage sections={cmsSections} />
}
