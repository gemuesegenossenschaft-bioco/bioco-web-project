import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { CTA } from '@/components/CTA'
import { getPageData, PageData } from '@/lib/processwire'

function StaticErnteContent() {
  return (
    <>
      <Header />
      <Hero title="Unser Gemüse: Frisch, lokal, Demeter." subtitle="Vielfalt & Qualität" />
      <main className="main-content">
        <div className="content-grid">
          <section id="B-02" className="content-card">
            <h2>Galerie</h2>
            <p>Filter: Alles · Körbe · Feld · Portraits</p>
            <p>Grid: 2-Spalten mobil</p>
          </section>

          <section id="B-03" className="content-card">
            <h2>Saisonkalender</h2>
            <p>Tabs Jan–Dez → Listen: Jetzt/Bald/Lagerware</p>
          </section>

          <section id="B-04" className="content-card">
            <h2>Demeter-Qualität</h2>
            <p>„Warum Demeter?" – kurzer Text + Akkordeon</p>
          </section>

          <EventsBanner />
          <CTA text="Jetzt Abo sichern" href="/mitmachen" variant="primary" />
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicErnteContent({ data }: { data: PageData }) {
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
          <CTA text="Jetzt Abo sichern" href="/mitmachen" variant="primary" />
        </div>
      </main>
      <Footer />
    </>
  )
}

export default async function ErntePage() {
  const data = await getPageData('/ernte/')
  if (data && (data.body || data.sections?.length)) {
    return <DynamicErnteContent data={data} />
  }
  return <StaticErnteContent />
}
