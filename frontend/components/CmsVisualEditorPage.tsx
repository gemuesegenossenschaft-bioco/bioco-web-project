import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Suspense } from 'react'
import { VisualEditorWrapper } from '@/components/sections/VisualEditorWrapper'
import type { ContentSection } from '@/lib/processwire-types'

interface CmsVisualEditorPageProps {
  sections: ContentSection[]
}

/**
 * Lightweight SSR fallback: renders section content as plain HTML
 * without importing client components. Shown until client JS hydrates.
 */
function SectionsFallback({ sections }: { sections: ContentSection[] }) {
  return (
    <div className="cms-sections">
      {sections.map((section) => (
        <div key={section.id}>
          {section.title ? <h2>{section.title}</h2> : null}
          {section.text ? (
            <div dangerouslySetInnerHTML={{ __html: section.text }} />
          ) : null}
        </div>
      ))}
    </div>
  )
}

export function CmsVisualEditorPage({ sections }: CmsVisualEditorPageProps) {
  return (
    <>
      <Header />
      <main className="main-content">
        <Suspense fallback={<SectionsFallback sections={sections} />}>
          <VisualEditorWrapper sections={sections} />
        </Suspense>
      </main>
      <Footer />
    </>
  )
}
