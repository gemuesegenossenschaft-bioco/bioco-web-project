import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { Gallery } from '@/components/Gallery'
import { Saisonkalender } from '@/components/Saisonkalender'
import { EventsSection } from '@/components/EventsSection'
import Link from 'next/link'
import { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Saisonales Demeter Gemüse | Was wächst gerade | biocò',
  description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf für die Region Baden-Brugg.',
  keywords: 'demeter gemüse, bio gemüse, saisonales gemüse, gebenstorf, baden, brugg, gemüseernte',
  openGraph: {
    title: 'Saisonales Demeter Gemüse | Was wächst gerade | biocò',
    description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf.',
    type: 'website',
  },
}

export default function GemusePage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          {/* Page Header with H1 */}
          <section className="bento-card bento-card-fullwidth">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h1 style={{ fontSize: '2.5rem', margin: 0 }}>Was wächst gerade auf dem Geisshof?</h1>
            </div>
            <div className="card-body">
              <p className="card-text">
                Unser saisonales Demeter-Gemüse wächst in der Region Baden-Brugg. 
                Hier erfährst du, welche Gemüsesorten gerade Saison haben und in deinem Gemüsekorb landen.
              </p>
            </div>
          </section>

          {/* Erste Zeile: Saisonkalender (2/3) und Events (1/3) */}
          <div className="ernte-top-row">
            <section id="B-04" className="bento-card">
              <div className="plant-pattern"></div>
              <div className="card-header">
                <h2>Saisonkalender</h2>
              </div>
              <div className="card-body">
                <p className="card-text">Wann ist welches Gemüse verfügbar? Entdecke unsere saisonale Vielfalt.</p>
                <Saisonkalender />
              </div>
            </section>

            <EventsSection limit={3} />
          </div>

          {/* Dritte Zeile: Demeter und Pictures nebeneinander (50/50) */}
          <div className="ernte-bottom-row">
            <section id="B-05" className="bento-card">
              <div className="plant-pattern"></div>
              <div className="card-header">
                <h2>Demeter-Qualität</h2>
              </div>
              <div className="card-body">
                <div className="demeter-info">
                  <h3>Warum Demeter?</h3>
                  <p>
                    Demeter ist die höchste Qualitätsstufe im biologischen Landbau. Als Demeter-zertifizierter Betrieb 
                    gehen wir über die Anforderungen von Bio Suisse hinaus und arbeiten nach den strengsten 
                    biologisch-dynamischen Richtlinien.
                  </p>
                  
                  <div className="demeter-accordion">
                    <details>
                      <summary>Biologisch-dynamische Landwirtschaft</summary>
                      <p>
                        Die biologisch-dynamische Landwirtschaft betrachtet den Hof als lebendigen Organismus. 
                        Wir arbeiten mit speziellen Präparaten, die die Bodenfruchtbarkeit und Pflanzengesundheit fördern. 
                        Der Mond- und Planetenrhythmus wird in die Anbauplanung einbezogen.
                      </p>
                    </details>
                    
                    <details>
                      <summary>Kein Einsatz von synthetischen Mitteln</summary>
                      <p>
                        Wir verzichten vollständig auf synthetische Dünger, Pestizide und Herbizide. Stattdessen 
                        setzen wir auf natürliche Methoden zur Bodenpflege, Schädlingsbekämpfung und 
                        Pflanzenstärkung.
                      </p>
                    </details>
                    
                    <details>
                      <summary>Kreislaufwirtschaft</summary>
                      <p>
                        Auf dem Geisshof betreiben wir eine geschlossene Kreislaufwirtschaft. Kompost, 
                        Gründüngung und Fruchtfolgen sorgen für gesunde Böden und nachhaltige Erträge.
                      </p>
                    </details>
                    
                    <details>
                      <summary>Biodiversität</summary>
                      <p>
                        Wir fördern die Artenvielfalt durch Hecken, Blumenstreifen und vielfältige Fruchtfolgen. 
                        Dies schafft Lebensraum für Nützlinge und trägt zu einem gesunden Ökosystem bei.
                      </p>
                    </details>
                  </div>

                  <p style={{ marginTop: '16px' }}>
                    <a 
                      href="https://www.demeter.ch" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="btn btn-secondary"
                      style={{ display: 'inline-block' }}
                    >
                      Mehr über Demeter erfahren →
                    </a>
                  </p>
                </div>
              </div>
            </section>

            <section id="B-02" className="bento-card">
              <div className="plant-pattern"></div>
              <div className="card-header">
                <h2>Was wir anbauen</h2>
              </div>
              <div className="card-body">
                <p className="card-text">Einblicke in unsere Ernte, den Anbau und die Gemeinschaft</p>
                <Gallery />
              </div>
            </section>
          </div>

          {/* Möchtest du uns kennenlernen - Am Ende */}
          <section id="B-06" className="bento-card bento-card-fullwidth kennenlernen-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Möchtest du uns kennenlernen?</h3>
            </div>
            <div className="card-body">
              <p className="card-text">Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
              <div style={{ marginTop: '16px', display: 'flex', gap: 'var(--spacing-md)', flexWrap: 'wrap' }}>
                <CTA
                  text="Nimm Kontakt auf"
                  href="/kontakt"
                  variant="primary"
                />
                <CTA
                  text="Zu uns finden"
                  href="/standorte-depots"
                  variant="secondary"
                />
              </div>
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}