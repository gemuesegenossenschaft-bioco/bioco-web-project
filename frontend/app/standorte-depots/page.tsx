import { Metadata } from 'next'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { DepotMap } from '@/components/DepotMap'
import { GeisshofMap } from '@/components/GeisshofMap'

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
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>Unsere Standorte & Depots</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>
              Wir unterscheiden zwei Arten von Standorten:
            </p>
            <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', margin: '16px 0', paddingLeft: '20px' }}>
              <li style={{ marginBottom: '8px' }}><strong>Geisshof:</strong> Hier bauen wir unser Gemüse an und arbeiten gemeinsam.</li>
              <li><strong>Depots:</strong> Hier stehen die Gemüsekörbe jeweils dienstags oder freitags zur Abholung bereit.</li>
            </ul>
          </section>

          <section id="E-01" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Anfahrt zum Geisshof</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              Der Geisshof ist unser Bio Bauernhof in Gebenstorf im Aargau, wo wir Bio-Gemüse in
              Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg kannst du hier
              auch direkt vorbeikommen und die solidarische Landwirtschaft kennenlernen.
            </p>

            <div style={{ marginTop: '16px', padding: '16px', backgroundColor: 'var(--bg-secondary)', borderRadius: '8px', marginBottom: '24px' }}>
              <h4 style={{ marginBottom: '12px', color: 'var(--bioco-green)' }}>Anreise & Parken</h4>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '8px' }}><strong>Bitte komm wenn möglich mit dem Velo oder Bus!</strong></p>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '8px' }}>Falls du mit dem Auto kommst:</p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', margin: '0 0 8px 0', paddingLeft: '20px' }}>
                <li>Bitte <strong>nicht auf den Hof hinauffahren</strong></li>
                <li>Parkiere unten an der Strasse</li>
                <li>Halte den <strong>Wendeplatz zwingend frei</strong> (für landwirtschaftliche Fahrzeuge)</li>
              </ul>
              <p style={{ fontSize: '0.9rem', fontStyle: 'italic', color: 'var(--text-secondary)' }}>Danke für deine Rücksichtnahme!</p>
            </div>

            <GeisshofMap />
          </section>

          <section id="E-02" style={{ marginBottom: 'clamp(48px, 8vw, 96px)', scrollMarginTop: '100px' }}>
            <h2>Depot-Standorte für Gemüseabholung</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              Hier findest du alle Depot-Standorte, an denen du dein Gemüse abholen kannst.
              Wähle das Depot, das für dich am besten gelegen ist.
            </p>
            <DepotMap />

            <div style={{ marginTop: '32px' }}>
              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Depot Baden</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                <strong>Gemüse abholen Baden:</strong> Das Depot Baden befindet sich zentral in der Stadt
                und ist ideal für alle, die in Baden und Umgebung wohnen.
              </p>

              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Depot Brugg</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                <strong>Gemüse abholen Brugg:</strong> Unser Depot Brugg bietet eine bequeme Abholmöglichkeit
                für Mitglieder aus Brugg und der umliegenden Region.
              </p>

              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Depot Gebenstorf</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                Direkt beim Geisshof könnt ihr euer Gemüse in Gebenstorf abholen - ideal für
                alle, die den Hof besuchen möchten.
              </p>

              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Depot Wettingen</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                Das Depot Wettingen ermöglicht eine einfache Abholung für Mitglieder aus Wettingen
                und der näheren Umgebung.
              </p>

              <p style={{ marginTop: '16px', fontStyle: 'italic', fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                <strong>Abholzeiten:</strong> Dienstag und Freitag, ab 16:00 Uhr
              </p>
            </div>
          </section>

          <section id="B-06" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Möchtest du uns kennenlernen?</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
            <div style={{ marginTop: '16px', display: 'flex', gap: 'var(--spacing-md)', flexWrap: 'wrap' }}>
              <CTA text="Nimm Kontakt auf" href="/kontakt" variant="primary" />
              <CTA text="Zu uns finden" href="/standorte-depots" variant="secondary" />
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
