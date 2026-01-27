'use client'

import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { PageData, PageSection } from '@/lib/processwire'

interface DynamicPageProps {
  data: PageData | null
  fallback?: React.ReactNode
  showHero?: boolean
  showEventsBanner?: boolean
}

function Section({ section }: { section: PageSection }) {
  return (
    <section id={section.id} className="content-card">
      {section.title && <h2>{section.title}</h2>}
      {section.content && (
        <div dangerouslySetInnerHTML={{ __html: section.content }} />
      )}
    </section>
  )
}

export function DynamicPage({ 
  data, 
  fallback, 
  showHero = true,
  showEventsBanner = true 
}: DynamicPageProps) {
  // If no data and fallback provided, show fallback
  if (!data && fallback) {
    return <>{fallback}</>
  }

  // If no data and no fallback, show error
  if (!data) {
    return (
      <>
        <Header />
        <main className="main-content">
          <div className="content-grid">
            <section className="content-card">
              <h1>Seite nicht gefunden</h1>
              <p>Die angeforderte Seite konnte nicht geladen werden.</p>
            </section>
          </div>
        </main>
        <Footer />
      </>
    )
  }

  const heroTitle = data.hero_title || data.title
  const heroSubtitle = data.hero_subtitle || ''

  return (
    <>
      <Header />
      {showHero && (
        <Hero
          title={heroTitle}
          subtitle={heroSubtitle}
        />
      )}
      <main className="main-content">
        <div className="content-grid">
          {/* Page title if no hero */}
          {!showHero && (
            <section className="content-card">
              <h1>{data.title}</h1>
            </section>
          )}

          {/* Main body content */}
          {data.body && (
            <section className="content-card">
              <div dangerouslySetInnerHTML={{ __html: data.body }} />
            </section>
          )}

          {/* Dynamic sections */}
          {data.sections && data.sections.map((section, index) => (
            <Section key={section.id || index} section={section} />
          ))}

          {/* Events banner */}
          {showEventsBanner && <EventsBanner />}
        </div>
      </main>
      <Footer />
    </>
  )
}

// Wrapper for pages that need to show static content as fallback
export function DynamicPageWithFallback({
  data,
  StaticContent,
  ...props
}: DynamicPageProps & { StaticContent: React.ComponentType }) {
  if (!data) {
    return <StaticContent />
  }
  return <DynamicPage data={data} {...props} />
}
