import { notFound } from 'next/navigation'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { SectionRenderer } from '@/components/sections/SectionRenderer'
import { getAllPages, getPageContent } from '@/lib/processwire'
import { generateMetadata as generateSeoMetadata } from '@/lib/seo'
import type { Metadata } from 'next'

// ISR: revalidate every 60 seconds
export const revalidate = 60
export const dynamic = 'force-dynamic'

// Allow paths not in generateStaticParams to be rendered on-demand
// This ensures new CMS pages appear without a rebuild
export const dynamicParams = true

const RESERVED_PATHS = new Set([
  'abos',
  'aktuelles',
  'anmeldung',
  'anmeldung/danke',
  'bioco-werden',
  'datenschutz',
  'doi-confirm',
  'gemuese',
  'impressum',
  'intranet',
  'kontakt',
  'kundenportal',
  'mitmachen',
  'newsletter',
  'solawi',
  'standorte-depots',
  'statuten',
  'tag-der-offenen-tuer',
  'warteliste',
  'wir',
])

// Files that should never be handled by the CMS catch-all
const STATIC_FILES = /\.(ico|png|jpg|svg|xml|txt|webmanifest)$/

function normalizeSlug(segments?: string[]) {
  if (!segments || segments.length === 0) return ''
  return segments.join('/')
}

export async function generateStaticParams() {
  const pages = await getAllPages()
  return pages
    .map((page) => page.path.split('/').filter(Boolean))
    .filter((segments) => segments.length > 0)
    .filter((segments) => !RESERVED_PATHS.has(segments.join('/')))
    .map((segments) => ({ slug: segments }))
}

export async function generateMetadata({ params }: { params: { slug?: string[] } }): Promise<Metadata> {
  const slug = normalizeSlug(params.slug)
  if (!slug || RESERVED_PATHS.has(slug) || STATIC_FILES.test(slug)) {
    return {}
  }
  const { seo, pageData } = await getPageContent(slug)
  return generateSeoMetadata(seo, {
    title: pageData?.title || undefined,
    path: `/${slug}`,
  })
}

export default async function CmsPage({ params }: { params: { slug?: string[] } }) {
  const slug = normalizeSlug(params.slug)
  if (!slug || RESERVED_PATHS.has(slug) || STATIC_FILES.test(slug)) {
    notFound()
  }

  const { sections, pageData } = await getPageContent(slug)

  if (!pageData && (!sections || sections.length === 0)) {
    notFound()
  }

  return (
    <>
      <Header />
      <main className="main-content">
        <SectionRenderer sections={sections} />
      </main>
      <Footer />
    </>
  )
}
