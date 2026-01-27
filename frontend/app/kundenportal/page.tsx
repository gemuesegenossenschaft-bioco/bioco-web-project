import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { getPageData, PageData } from '@/lib/processwire'

function StaticKundenportalContent() {
  return (
    <>
      <Header />
      <Hero title="Kundenportal" subtitle="Zugang für Mitglieder" />
      <main className="main-content">
        <div className="content-grid">
          <section id="I-01" className="content-card">
            <h2>Mitgliederbereich</h2>
            <ul>
              <li>Kachel: Mitglieder-Portal (extern)</li>
              <li>Kachel: Einsatzplanung (extern)</li>
            </ul>
          </section>
          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicKundenportalContent({ data }: { data: PageData }) {
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
        </div>
      </main>
      <Footer />
    </>
  )
}

export default async function KundenportalPage() {
  // Always show static content (production version)
  return <StaticKundenportalContent />
}
