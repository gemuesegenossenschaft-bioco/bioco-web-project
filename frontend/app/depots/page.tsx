import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { getPageData, PageData } from '@/lib/processwire'

function StaticDepotsContent() {
  return (
    <>
      <Header />
      <Hero title="Depots" subtitle="Abholstationen in der Region" />
      <main className="main-content">
        <div className="content-grid">
          <section id="E-01" className="content-card">
            <h2>Depots – Map & Liste</h2>
            <p>Karte Baden/Brugg/Wettingen/Windisch/Ennetbaden</p>
            <p>Adressliste + Zeiten (falls vorhanden)</p>
          </section>
          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicDepotsContent({ data }: { data: PageData }) {
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

export default async function DepotsPage() {
  // Always show static content (production version)
  return <StaticDepotsContent />
}
