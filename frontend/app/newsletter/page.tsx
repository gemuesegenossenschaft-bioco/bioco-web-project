import { getPageSections } from '@/lib/processwire'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function NewsletterPage() {
  const cmsSections = await getPageSections('newsletter')
  return <CmsVisualEditorPage sections={cmsSections} />
}
