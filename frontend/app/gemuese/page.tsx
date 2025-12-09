import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { Gallery } from '@/components/Gallery'
import { Saisonkalender } from '@/components/Saisonkalender'
import Link from 'next/link'
import { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Saisonales Demeter Gemüse | Welche Gemüse haben gerade Saison | biocò',
  description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf für die Region Baden-Brugg.',
  keywords: 'demeter gemüse, bio gemüse, saisonales gemüse, gebenstorf, baden, brugg, gemüseernte',
  openGraph: {
    title: 'Saisonales Demeter Gemüse | Welche Gemüse haben gerade Saison | biocò',
    description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf.',
    type: 'website',
  },
}

export default function GemusePage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          {/* Page Header with H1 */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>Welche Gemüse haben gerade Saison</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
              Unser saisonales Demeter-Gemüse wächst in der Region Baden-Brugg. 
              Hier erfährst du, welche Gemüsesorten gerade Saison haben und in deinem Gemüsekorb landen.
            </p>
          </section>

          <section id="B-04" style={{ marginBottom: 'clamp(48px, 8vw, 96px)', width: '100%', maxWidth: '100%' }}>
            <h2>Saisonkalender</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Wann ist welches Gemüse verfügbar? Entdecke unsere saisonale Vielfalt.</p>
            <div style={{ width: '100%', maxWidth: '100%' }}>
              <Saisonkalender />
            </div>
          </section>

          {/* Demeter - Single Column */}
          <section id="B-05" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Demeter-Qualität</h2>
            <div style={{ marginTop: '16px' }}>
              <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Warum Demeter?</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                Demeter ist die höchste Qualitätsstufe im biologischen Landbau. Als Demeter-zertifizierter Betrieb 
                gehen wir über die Anforderungen von Bio Suisse hinaus und arbeiten nach den strengsten 
                biologisch-dynamischen Richtlinien.
              </p>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
                Unser Gemüse wächst auf dem Geisshof in Gebenstorf im Rahmen unserer <Link href="/solawi">solidarischen Landwirtschaft (Solawi)</Link> – direkt aus der Region Baden-Brugg.
              </p>
              
              <div className="demeter-accordion" style={{ marginBottom: '24px' }}>
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
                  className="btn btn-secondary btn-organic"
                  style={{ display: 'inline-block' }}
                >
                  Mehr über Demeter erfahren →
                </a>
              </p>
            </div>
          </section>

          {/* Was wir anbauen - Single Column */}
          <section id="B-02" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Was wir anbauen</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Einblicke in unsere Ernte, den Anbau und die Gemeinschaft</p>
            <Gallery />
          </section>

          {/* Möchtest du uns kennenlernen - Am Ende */}
          <section id="B-06" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Möchtest du uns kennenlernen?</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
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
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
