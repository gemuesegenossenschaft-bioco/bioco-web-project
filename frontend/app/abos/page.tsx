import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Suspense } from 'react'
import { VisualEditorPageSwitch } from '@/components/VisualEditorPageSwitch'
import { SectionRenderer } from '@/components/sections/SectionRenderer'
import { Metadata } from 'next'
import { ProductSchema } from '@/components/StructuredData'
import { getPageSectionsWithSeo } from '@/lib/processwire'
import { generateMetadata as generateSeoMetadata } from '@/lib/seo'

// Static fallback metadata
const FALLBACK_METADATA: Metadata = {
  title: 'Gemüseabo Baden | Demeter Gemüse wöchentlich | biocò',
  description: 'Gemüseabo für die Region Baden-Brugg: Wöchentlich frisches Bio-Gemüse in Demeter-Qualität. Solidarische Landwirtschaft vom Geisshof Gebenstorf.',
  keywords: 'gemüseabo, demeter gemüse, bio gemüse, baden, brugg, gebenstorf, wöchentlicher gemüsekorb',
  openGraph: {
    title: 'Gemüseabo Baden | Demeter Gemüse wöchentlich | biocò',
    description: 'Gemüseabo für die Region Baden-Brugg: Wöchentlich frisches Bio-Gemüse in Demeter-Qualität.',
    type: 'website',
  },
}

// Dynamic metadata from CMS
export async function generateMetadata(): Promise<Metadata> {
  const { seo } = await getPageSectionsWithSeo('abos')

  if (seo?.title || seo?.description) {
    return generateSeoMetadata(seo, {
      title: FALLBACK_METADATA.title as string,
      description: FALLBACK_METADATA.description as string,
      path: '/abos',
    })
  }

  return FALLBACK_METADATA
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default async function AbosPage() {
  // Content is fully CMS-driven (content_sections), editable in the Visual Editor.
  // The Gemüse-Abos pricing table is the `pricing_table` component; all other blocks
  // are CMS rich text / registered components. No hardcoded page copy.
  const { sections } = await getPageSectionsWithSeo('abos')

  const content = (
    <>
      <ProductSchema />
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          <SectionRenderer sections={sections} pagePath="/abos" />
        </div>
      </main>
      <Footer />
    </>
  )

  return (
    <Suspense fallback={content}>
      <VisualEditorPageSwitch sections={sections}>{content}</VisualEditorPageSwitch>
    </Suspense>
  )
}
