import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Suspense } from 'react'
import { VisualEditorWrapper } from '@/components/sections/VisualEditorWrapper'
import type { ContentSection } from '@/lib/processwire-types'

interface CmsVisualEditorPageProps {
  sections: ContentSection[]
}

export function CmsVisualEditorPage({ sections }: CmsVisualEditorPageProps) {
  return (
    <>
      <Header />
      <main className="main-content">
        <Suspense fallback={null}>
          <VisualEditorWrapper sections={sections} />
        </Suspense>
      </main>
      <Footer />
    </>
  )
}
