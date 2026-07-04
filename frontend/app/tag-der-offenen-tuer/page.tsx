import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function VisitDayPage() {
  const cmsSections = await getPageSections('tag-der-offenen-tuer')
  return <CmsVisualEditorPage sections={cmsSections} />
}
