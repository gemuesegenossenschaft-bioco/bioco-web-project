import { Suspense } from 'react'
import { MinimalHeader } from '@/components/MinimalHeader'
import { getPageSections } from '@/lib/processwire'
import { VisualEditorWrapper } from '@/components/sections/VisualEditorWrapper'
import { SectionsFallback } from '@/components/CmsVisualEditorPage'

// ISR: Revalidate every 60 seconds
export const revalidate = 60

// Page chrome (MinimalHeader, bento card, plant pattern) is code-owned;
// the h1 and the membership_form placement come from the CMS sections
// for /anmeldung. The page intentionally has no footer, as before.
export default async function AnmeldungPage() {
  const sections = await getPageSections('anmeldung')
  return (
    <>
      <MinimalHeader />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-fullwidth">
            <div className="plant-pattern"></div>
            <Suspense fallback={<SectionsFallback sections={sections} />}>
              <VisualEditorWrapper sections={sections} />
            </Suspense>
          </section>
        </div>
      </main>
    </>
  )
}
