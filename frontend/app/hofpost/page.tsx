import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { getPageData, PageData } from '@/lib/processwire'

function StaticHofpostContent() {
  return (
    <>
      <Header />
      <Hero title="Hofpost" subtitle="Neuigkeiten vom Geisshof" />
      <main className="main-content">
        <div className="content-grid">
          <section id="G-01" className="content-card">
            <h2>Hofpost Übersicht</h2>
            <p>Blog/News mit Karten-Grid; Einzelansichten</p>
          </section>

          <section id="G-02" className="content-card">
            <h2>Nächste Events</h2>
            <p>Zentrale Eventliste; Filter/Tags optional</p>
          </section>
          
          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicHofpostContent({ data }: { data: PageData }) {
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

export default async function HofpostPage() {
  const data = await getPageData('/hofpost/')
  if (data && (data.body || data.sections?.length)) {
    return <DynamicHofpostContent data={data} />
  }
  return <StaticHofpostContent />
}
