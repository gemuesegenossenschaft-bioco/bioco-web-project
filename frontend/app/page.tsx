import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { EventsBanner } from '@/components/EventsBanner'
import { CTA } from '@/components/CTA'
import Link from 'next/link'
import { CarrotIcon, LeafIcon, PlantIcon, SeedIcon } from '@/components/Icon'
import { getPageData, PageData } from '@/lib/processwire'

// Static fallback content (used when CMS data not available)
function StaticHomeContent() {
  return (
    <>
      <Header />
      <Hero
        title="Frisches Demeter-Gemüse – fast jede Woche."
        subtitle=""
      />
      <main className="main-content">
        <div className="content-grid">
          <section id="A-02" className="content-card">
            <h2>Willkommen bei biocò</h2>
            <p>biocò ist eine Gemüsegenossenschaft in der Region Baden-Brugg, die seit 2014 auf dem Geisshof in Gebenstorf eine Vielzahl von Biogemüse in Demeter-Qualität anbaut und wöchentlich an ihre Mitglieder verteilt.</p>
          </section>

          <section id="A-03" className="content-card">
            <h2>Das ist drin: Saisonal & Demeter</h2>
            <p>Wöchentlich erhalten unsere Mitglieder einen Gemüsekorb mit frischem, saisonalem Gemüse in Demeter-Qualität.</p>
            <ul className="icon-list">
              <li>
                <span className="icon-list-icon"><CarrotIcon /></span>
                <span>Wöchentlicher Gemüsekorb</span>
              </li>
              <li>
                <span className="icon-list-icon"><LeafIcon /></span>
                <span>Saisonalität – das Gemüse der Jahreszeit</span>
              </li>
              <li>
                <span className="icon-list-icon"><SeedIcon /></span>
                <span>Demeter-Qualität – höchste Bio-Standards</span>
              </li>
            </ul>
            <CTA
              text="Ernte entdecken"
              href="/ernte"
              variant="primary"
            />
          </section>

          <section id="A-04" className="content-card">
            <h2>Gemeinschaft & Solidarität</h2>
            <p>biocò basiert auf den Prinzipien der Solidarischen Landwirtschaft.</p>
            <ul className="icon-list">
              <li>
                <span className="icon-list-icon"><PlantIcon /></span>
                <span><strong>Mitarbeit</strong> – <Link href="/anpacken">Anpacken auf dem Feld</Link></span>
              </li>
              <li>
                <span className="icon-list-icon"><LeafIcon /></span>
                <span><strong>Transparenz</strong> – Solidarische Landwirtschaft (CSA)</span>
              </li>
              <li>
                <span className="icon-list-icon"><SeedIcon /></span>
                <span><strong>Gemeinschaft</strong> – Jede(r) bringt sich ein</span>
              </li>
              <li>
                <span className="icon-list-icon"><CarrotIcon /></span>
                <span><strong>Lokal/Region</strong> – <Link href="/wir">Hof: Geisshof</Link></span>
              </li>
            </ul>
          </section>

          <section id="A-05" className="content-card">
            <h2>Wie funktioniert's?</h2>
            <ul className="icon-list">
              <li>
                <span className="icon-list-icon"><CarrotIcon /></span>
                <span>Info-Event besuchen – <Link href="/depots">Schnuppertage</Link></span>
              </li>
              <li>
                <span className="icon-list-icon"><LeafIcon /></span>
                <span>Probe-Abo testen – <Link href="/abos">3-Monats-Test</Link></span>
              </li>
              <li>
                <span className="icon-list-icon"><PlantIcon /></span>
                <span>Mitglied werden – <Link href="/mitmachen">Anmeldung</Link></span>
              </li>
            </ul>
          </section>

          <section id="A-06" className="content-card">
            <h2>Konzept-Überblick (CSA, 3 Schritte)</h2>
            <ol className="numbered-list">
              <li><strong>Anmeldung</strong> – Werde Genossenschafter/in</li>
              <li><strong>Bestätigung/Rechnung</strong> – Du erhältst eine Bestätigung</li>
              <li><strong>Abholen</strong> – Wöchentlich Gemüsekorb in einem der <Link href="/depots">Depots</Link></li>
            </ol>
            <p><strong>Hinweis:</strong> Wir teilen Arbeit, Ertrag und Risiko.</p>
          </section>

          <section id="A-07" className="content-card aktuelles-block">
            <h2>Aktuelles</h2>
            <div className="aktuelles-item">
              <h3>24. Oktober 2025</h3>
              <p>Letzter biocò Schnuppertag des Jahres auf dem Geisshof</p>
            </div>
            <div className="aktuelles-item">
              <h3>15. November 2025</h3>
              <p>Fondue-Abend</p>
            </div>
            <div className="aktuelles-item">
              <h3>27. November 2025</h3>
              <p>Außerordentliche Generalversammlung</p>
            </div>
            <Link href="/hofpost" className="btn btn-primary" style={{ marginTop: '16px' }}>
              Alle Aktuelles ansehen
            </Link>
          </section>

          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}

// Dynamic content from CMS
function DynamicHomeContent({ data }: { data: PageData }) {
  const heroTitle = data.hero_title || 'Frisches Demeter-Gemüse – fast jede Woche.'
  const heroSubtitle = data.hero_subtitle || ''

  return (
    <>
      <Header />
      <Hero title={heroTitle} subtitle={heroSubtitle} />
      <main className="main-content">
        <div className="content-grid">
          {/* Render body content if available */}
          {data.body && (
            <section className="content-card">
              <div dangerouslySetInnerHTML={{ __html: data.body }} />
            </section>
          )}

          {/* Render sections from CMS */}
          {data.sections && data.sections.map((section, index) => (
            <section key={section.id || index} id={section.id} className="content-card">
              {section.title && <h2>{section.title}</h2>}
              {section.content && (
                <div dangerouslySetInnerHTML={{ __html: section.content }} />
              )}
            </section>
          ))}

          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}

export default async function Home() {
  // Fetch page data from ProcessWire
  const data = await getPageData('/')
  
  // If CMS has content (body or sections), use dynamic content
  // Otherwise fall back to static content
  if (data && (data.body || (data.sections && data.sections.length > 0))) {
    return <DynamicHomeContent data={data} />
  }
  
  // Fallback to static content
  return <StaticHomeContent />
}