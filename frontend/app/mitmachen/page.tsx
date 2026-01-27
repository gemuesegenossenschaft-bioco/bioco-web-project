import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { getPageData, PageData } from '@/lib/processwire'
import Link from 'next/link'

function StaticMitmachenContent() {
  return (
    <>
      <Header />
      <Hero title="Werde jetzt Teil von biocò" subtitle="Kurze Einleitung; Foto Hände+Gemüse" />
      <main className="main-content">
        <div className="content-grid">
          <section id="H-02" className="content-card">
            <h2>Zuerst testen?</h2>
            <ul>
              <li>Info-Event → <Link href="/hofpost">Hofpost / Nächste Events</Link></li>
              <li>Probe-Abo → <Link href="/abos">Abos</Link></li>
            </ul>
          </section>

          <section id="H-03" className="content-card">
            <h2>Commitment-Check</h2>
            <p>Checkliste: Anteile, Beitrag, Bindung, Mitarbeit, Risiko</p>
          </section>

          <section id="H-04" className="content-card">
            <h2>Anmeldung (Smart Form)</h2>
            <p>Angaben → Abo → Anteile → Depot/Zahlung → Mitarbeit → Bestätigung</p>
          </section>

          <EventsBanner />

          <section id="H-06" className="content-card">
            <h2>Nächstes (3 Schritte)</h2>
            <p>Bestätigungs-Mail → Rechnung → Start</p>
          </section>

          <section id="H-07" className="content-card">
            <h2>Warteliste (conditional)</h2>
            <p>Kurzformular: Vorname, Name, E-Mail, Wunsch-Abo; ersetzt Anmeldung wenn voll</p>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}

function DynamicMitmachenContent({ data }: { data: PageData }) {
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

export default async function MitmachenPage() {
  const data = await getPageData('/mitmachen/')
  if (data && (data.body || data.sections?.length)) {
    return <DynamicMitmachenContent data={data} />
  }
  return <StaticMitmachenContent />
}
