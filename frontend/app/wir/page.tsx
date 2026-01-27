import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { getPageData, PageData } from '@/lib/processwire'

function StaticWirContent() {
  return (
    <>
      <Header />
      <Hero title="Wir" subtitle="Team, Hof & Geschichte" />
      <main className="main-content">
        <div className="content-grid">
          <section id="F-01" className="content-card">
            <h2>Team & Hof</h2>
            <p>Profilkarten Team; Hof: Geisshof (Gebenstorf)</p>
          </section>

          <section id="F-02" className="content-card">
            <h2>Mission/Leitbild</h2>
            <p>Werte, Solidarität, Gotti-System (Kurzinfo)</p>
          </section>

          <section id="F-03" className="content-card">
            <h2>Geschichte (Text)</h2>
            <p>Gründung 2014; Entwicklung in Region Baden-Brugg; Depots</p>
          </section>

          <section id="F-04" className="content-card">
            <h2>Geschichte (Timeline)</h2>
            <p>Timeline (Jahre, Meilensteine, Fotos optional)</p>
          </section>

          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicWirContent({ data }: { data: PageData }) {
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

export default async function WirPage() {
  // Always show static content (production version)
  return <StaticWirContent />
}
