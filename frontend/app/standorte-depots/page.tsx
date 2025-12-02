import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { DepotMap } from '@/components/DepotMap'
import { GeisshofMap } from '@/components/GeisshofMap'
import Link from 'next/link'
import { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Standorte & Depots Baden-Brugg | Gemüse abholen | biocò',
  description: 'Gemüseabholung in Baden, Brugg und Umgebung. Finde dein Depot für frisches Bio-Gemüse aus solidarischer Landwirtschaft vom Geisshof Gebenstorf.',
  keywords: 'depot, standorte, baden, brugg, gebenstorf, gemüseabholung, bio gemüse, geisshof',
  openGraph: {
    title: 'Standorte & Depots Baden-Brugg | Gemüse abholen | biocò',
    description: 'Gemüseabholung in Baden, Brugg und Umgebung. Finde dein Depot für frisches Bio-Gemüse.',
    type: 'website',
  },
}

export default function StandortePage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="standorte-layout">
          {/* Page Header with H1 */}
          <section className="bento-card bento-card-fullwidth">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h1 style={{ fontSize: '2.5rem', margin: 0 }}>Unsere Standorte & Depots</h1>
            </div>
            <div className="card-body">
              <p className="card-text">
                Wir unterscheiden zwei Arten von Standorten:
              </p>
              <ul style={{ margin: '16px 0', paddingLeft: '20px' }}>
                <li style={{ marginBottom: '8px' }}><strong>Geisshof:</strong> Hier bauen wir unser Gemüse an und arbeiten gemeinsam.</li>
                <li><strong>Depots:</strong> Hier stehen die Gemüsekörbe jeweils dienstags oder freitags zur Abholung bereit.</li>
              </ul>
            </div>
          </section>

          <section id="E-01" className="bento-card bento-card-fullwidth standorte-map-section">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h2>Anfahrt zum Geisshof</h2>
            </div>
            <div className="card-body">
              <p className="card-text">
                Der Geisshof ist unser Bio Bauernhof in Gebenstorf im Aargau, wo wir Bio-Gemüse in 
                Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg kannst du hier 
                auch direkt vorbeikommen und die solidarische Landwirtschaft kennenlernen.
              </p>
              
              <div style={{ marginTop: '16px', padding: '16px', backgroundColor: 'var(--bg-secondary)', borderRadius: '8px' }}>
                <h4 style={{ marginBottom: '12px', color: 'var(--bioco-green)' }}>Anreise & Parken</h4>
                <p style={{ marginBottom: '8px' }}><strong>Bitte komm wenn möglich mit dem Velo oder Bus!</strong></p>
                <p style={{ marginBottom: '8px' }}>Falls du mit dem Auto kommst:</p>
                <ul style={{ margin: '0 0 8px 0', paddingLeft: '20px' }}>
                  <li>Bitte <strong>nicht auf den Hof hinauffahren</strong></li>
                  <li>Parkiere unten an der Strasse</li>
                  <li>Halte den <strong>Wendeplatz zwingend frei</strong> (für landwirtschaftliche Fahrzeuge)</li>
                </ul>
                <p style={{ fontSize: '0.9rem', fontStyle: 'italic' }}>Danke für deine Rücksichtnahme!</p>
              </div>

              <GeisshofMap />
            </div>
          </section>

          <section id="E-02" className="bento-card standorte-map-section standorte-depot-fullwidth">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h2>Depot-Standorte für Gemüseabholung</h2>
            </div>
            <div className="card-body">
              <p className="card-text">
                Hier findest du alle Depot-Standorte, an denen du dein Gemüse abholen kannst. 
                Wähle das Depot, das für dich am besten gelegen ist.
              </p>
              <DepotMap />
              
              <div style={{ marginTop: '24px' }}>
                <h3>Depot Baden</h3>
                <p>
                  <strong>Gemüse abholen Baden:</strong> Das Depot Baden befindet sich zentral in der Stadt 
                  und ist ideal für alle, die in Baden und Umgebung wohnen.
                </p>
                
                <h3>Depot Brugg</h3>
                <p>
                  <strong>Gemüse abholen Brugg:</strong> Unser Depot Brugg bietet eine bequeme Abholmöglichkeit 
                  für Mitglieder aus Brugg und der umliegenden Region.
                </p>
                
                <h3>Depot Gebenstorf</h3>
                <p>
                  Direkt beim Geisshof könnt ihr euer Gemüse in Gebenstorf abholen – ideal für 
                  alle, die den Hof besuchen möchten.
                </p>
                
                <h3>Depot Wettingen</h3>
                <p>
                  Das Depot Wettingen ermöglicht eine einfache Abholung für Mitglieder aus Wettingen 
                  und der näheren Umgebung.
                </p>
                
                <p style={{ marginTop: '16px', fontStyle: 'italic' }}>
                  <strong>Abholzeiten:</strong> Dienstag und Freitag, ab 16:00 Uhr
                </p>
              </div>
            </div>
          </section>

          

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