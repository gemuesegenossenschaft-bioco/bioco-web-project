import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { CTA } from '@/components/CTA'
import { getPageData, PageData } from '@/lib/processwire'

function StaticAnpackenContent() {
  return (
    <>
      <Header />
      <Hero title="Anpacken" subtitle="Gemeinsam auf dem Feld" />
      <main className="main-content">
        <div className="content-grid">
          <section id="D-01" className="content-card">
            <h2>Anpacken – Info</h2>
            <p>Umfang ~4 Halbtage/Jahr; Beispiele: Feld/Logistik</p>
            <CTA text="Anmeldung" href="/mitmachen" variant="primary" />
          </section>
          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicAnpackenContent({ data }: { data: PageData }) {
  return (
    <>
      <Header />
      <Hero title={data.hero_title || data.title} subtitle={data.hero_subtitle || ''} />
      <main className="main-content">
        <div className="content-grid">
          {data.body && (
            <section className="content-card">
              <div dangerouslySetInnerHTML={{ __html: data.body }} />
            </section>
          )}
          {data.sections?.map((section, i) => (
            <section key={section.id || i} id={section.id} className="content-card">
              {section.title && <h2>{section.title}</h2>}
              {section.content && <div dangerouslySetInnerHTML={{ __html: section.content }} />}
            </section>
          ))}
          <EventsBanner />
          <CTA text="Anmeldung" href="/mitmachen" variant="primary" />
        </div>
      </main>
      <Footer />
    </>
  )
}

export default async function AnpackenPage() {
  // Always show static content (production version)
  return <StaticAnpackenContent />
}
