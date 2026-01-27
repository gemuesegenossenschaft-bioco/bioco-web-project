import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { getPageData, PageData } from '@/lib/processwire'
import Link from 'next/link'

function StaticAbosContent() {
  return (
    <>
      <Header />
      <Hero title="Gemüse-Abos" subtitle="Halb, Standard oder Doppel" />
      <main className="main-content">
        <div className="content-grid">
          <section id="C-01" className="content-card">
            <h2>Gemüse-Abos</h2>
            <p>Halb/Standard/Doppel – Leistungen & Preise</p>
            <p>Link → <Link href="/mitmachen">Mitmachen!</Link></p>
          </section>

          <section id="C-02" className="content-card">
            <h2>Probe-Abo</h2>
            <p>3-Monats-Test</p>
            <p>Link → <Link href="/mitmachen">Mitmachen!</Link></p>
          </section>

          <EventsBanner />

          <section id="C-04" className="content-card">
            <h2>Zusatz-Abos</h2>
            <p>Phase 2: Partnerangebote (Eier, Brot, Tofu)</p>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicAbosContent({ data }: { data: PageData }) {
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

export default async function AbosPage() {
  const data = await getPageData('/abos/')
  if (data && (data.body || data.sections?.length)) {
    return <DynamicAbosContent data={data} />
  }
  return <StaticAbosContent />
}
