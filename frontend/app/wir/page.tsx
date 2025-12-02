import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { EventsBanner } from '@/components/EventsBanner'
import { CTA } from '@/components/CTA'
import { EventsSection } from '@/components/EventsSection'
import Image from 'next/image'
import Link from 'next/link'
import { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Über uns | Bio Bauernhof Baden | biocò Gemüsegenossenschaft',
  description: 'biocò Gemüsegenossenschaft: Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof Gebenstorf. Demeter-zertifiziertes Gemüse für Baden-Brugg.',
  keywords: 'bio bauernhof, solidarische landwirtschaft, gemüsegenossenschaft, baden, brugg, gebenstorf, demeter, geisshof',
  openGraph: {
    title: 'Über uns | Solidarische Landwirtschaft Baden | biocò',
    description: 'Seit 2014 solidarische Landwirtschaft auf dem Geisshof Gebenstorf. Demeter-zertifiziertes Bio-Gemüse für Baden-Brugg.',
    type: 'website',
  },
}

export default function WirPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          {/* Erste Zeile: Wir */}
          <section id="F-01" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Wir</h3>
            </div>
            <div className="card-body">
              <h4 className="card-title">Team & Hof</h4>
              <p className="card-text">biocò ist eine Gemeinschaft von engagierten Menschen, die gemeinsam für frisches, regionales <Link href="/gemuese">Demeter-Gemüse</Link> sorgen.</p>
            
            <div className="team-grid">
              <div className="team-card">
                <div className="team-card-image" style={{ marginBottom: '16px' }}>
                  <Image
                    src="/images/team/hofteam_matthias.JPG"
                    alt="Matthias vom Hof-Team - Demeter Landwirtschaft Geisshof"
                    width={300}
                    height={300}
                    style={{ width: '100%', height: 'auto', objectFit: 'cover', borderRadius: '8px' }}
                  />
                </div>
                <h3>Matthias</h3>
                <p>Hof-Team</p>
              </div>
              
              <div className="team-card">
                <div className="team-card-image" style={{ marginBottom: '16px' }}>
                  <Image
                    src="/images/team/bioco_hofteam_michael.JPG"
                    alt="Michael vom Hof-Team - Demeter Landwirtschaft Geisshof"
                    width={300}
                    height={300}
                    style={{ width: '100%', height: 'auto', objectFit: 'cover', borderRadius: '8px' }}
                  />
                </div>
                <h3>Michael</h3>
                <p>Hof-Team</p>
              </div>
              
              <div className="team-card">
                <div className="team-card-image" style={{ marginBottom: '16px' }}>
                  <Image
                    src="/images/team/alle-mitglieder-bioco.jpeg"
                    alt="Mitglieder der Gemüsegenossenschaft biocò - Solidarische Landwirtschaft Baden"
                    width={800}
                    height={600}
                    style={{ width: '100%', height: 'auto', objectFit: 'cover', borderRadius: '8px' }}
                  />
                </div>
                <h3>Alle Mitglieder</h3>
                <p>Jede(r) Genossenschafter/in bringt sich ein – ob bei der Feldarbeit, in der Logistik oder bei Events.</p>
              </div>
              
              <div className="team-card">
                <div className="team-card-image" style={{ marginBottom: '16px', aspectRatio: '1', overflow: 'hidden', borderRadius: '8px' }}>
                  <Image
                    src="/images/team/betriebsgruppe.JPG"
                    alt="Betriebsgruppe der Gemüsegenossenschaft biocò Gebenstorf"
                    width={800}
                    height={600}
                    style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'bottom', borderRadius: '8px' }}
                  />
                </div>
                <h3>Betriebsgruppe (BG)</h3>
                <p>Die Betriebsgruppe koordiniert den Anbau, die Logistik und die Organisation der Genossenschaft.</p>
              </div>
            </div>
            </div>
          </section>

          {/* Nächste Events - Full Width */}
          <EventsSection limit={3} />

          <section id="F-01b" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Der Geisshof</h3>
            </div>
            <div className="card-body">
              <p className="card-text">
                Wir bewirtschaften einen Bio Bauernhof in Baden – genauer gesagt den Geisshof in 
                Gebenstorf im Aargau. Seit 2014 ist dieser Ort das Herzstück von biocò, wo wir 
                Bio-Gemüse in Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg 
                versorgen wir die Region mit frischem, saisonalem Gemüse. Hier finden die Feldarbeit, 
                die Gemüseaufbereitung und viele gemeinsame Anlässe statt.
              </p>
              
              <div className="geisshof-images-grid">
                <div>
                  <Image
                    src="/images/hof/bioco_hof_luftaufnahme_grosses-feld.JPG"
                    alt="Bio-Gemüse Anbaufläche auf dem Geisshof Gebenstorf"
                    width={800}
                    height={600}
                    style={{ width: '100%', height: 'auto', borderRadius: '12px' }}
                  />
                </div>
                <div>
                  <Image
                    src="/images/hof/bioco_hof_luftaufnahme-kleines-feld.JPG"
                    alt="Demeter Gemüsefeld auf dem Geisshof in Gebenstorf"
                    width={800}
                    height={600}
                    style={{ width: '100%', height: 'auto', borderRadius: '12px' }}
                  />
                </div>
              </div>
              
              <p style={{ marginTop: 'var(--spacing-md)' }}>
                <Link href="/standorte-depots" className="btn btn-secondary" style={{ display: 'inline-block' }}>
                  Anfahrtsweg zum Geisshof
                </Link>
              </p>
            </div>
          </section>

          <section id="F-02" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Mission & Leitbild</h3>
            </div>
            <div className="card-body">
            
            <div className="mission-values">
              <div className="mission-item">
                <h3>Solidarität</h3>
                <p>
                  Wir teilen Arbeit und Ertrag. Solidarische Landwirtschaft bedeutet, 
                  dass Produzentinnen und Konsumentinnen zusammenarbeiten und füreinander einstehen.
                </p>
                <p style={{ marginTop: '12px' }}>
                  <Link href="/solawi" className="btn btn-secondary" style={{ display: 'inline-block', fontSize: '0.875rem' }}>
                    → Mehr über solidarische Landwirtschaft erfahren
                  </Link>
                </p>
              </div>
              
              <div className="mission-item">
                <h3>Nachhaltigkeit</h3>
                <p>
                  Wir arbeiten nach biologisch-dynamischen Prinzipien (Demeter) und fördern 
                  Biodiversität, Kreislaufwirtschaft und gesunde Böden.
                </p>
              </div>
              
              <div className="mission-item">
                <h3>Gemeinschaft</h3>
                <p>
                  biocò lebt von der Gemeinschaft. Jede(r) bringt sich ein, lernt voneinander und 
                  gestaltet die Genossenschaft aktiv mit.
                </p>
              </div>
              
              <div className="mission-item">
                <h3>Regionalität</h3>
                <p>
                  Unser Gemüse wächst direkt in der Region Baden-Brugg. Kurze Wege, frische Ernte, 
                  lokale Verbundenheit.
                </p>
              </div>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h3>Gotti-System</h3>
              <p>
                Neumitglieder werden von einem "Gotti" oder "Götti" (Paten) begleitet. Dieses System 
                hilft neuen Mitgliedern, sich in der Genossenschaft zurechtzufinden und zeigt ihnen 
                die Abläufe und Möglichkeiten der Mitarbeit.
              </p>
            </div>
            </div>
          </section>

          <section id="F-03" className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Geschichte</h3>
            </div>
            <div className="card-body">
              <p className="card-text">
                Die Gemüsegenossenschaft biocò wurde 2014 in Gebenstorf im Aargau gegründet. 
                Aus einer kleinen Gruppe engagierter Menschen aus Baden, Brugg und der Region 
                wurde eine lebendige Gemeinschaft, die solidarische Landwirtschaft lebt.
              </p>
              <p className="card-text">
                Gestartet wurde auf dem Geisshof in Gebenstorf, wo wir bis heute unser Gemüse anbauen. 
                Über die Jahre haben wir die Anbaufläche erweitert, neue Standorte (Depots) für die 
                Gemüseabholung geschaffen und die Strukturen der Genossenschaft weiterentwickelt.
              </p>
              <p className="card-text">
                Heute versorgen wir Mitglieder in der Region Baden-Brugg wöchentlich mit frischem, 
                saisonalem Demeter-Gemüse und leben gemeinsam die Prinzipien der Solidarischen Landwirtschaft.
              </p>
            </div>
          </section>

          <section id="F-04" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Timeline</h3>
            </div>
            <div className="card-body">
              <div className="timeline">
              <div className="timeline-item">
                <div className="timeline-year">2014</div>
                <div className="timeline-content">
                  <h3>Gründung</h3>
                  <p>biocò wird als Gemüsegenossenschaft gegründet und startet den Anbau auf dem Geisshof.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2015</div>
                <div className="timeline-content">
                  <h3>Erste Erweiterung</h3>
                  <p>Die Anzahl der Mitglieder wächst, erste Depots werden eingerichtet.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2016-2018</div>
                <div className="timeline-content">
                  <h3>Strukturelle Entwicklung</h3>
                  <p>Betriebsgruppe wird etabliert, Strukturen werden professionalisiert, neue Standorte entstehen.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2019-2023</div>
                <div className="timeline-content">
                  <h3>Wachstum & Konsolidierung</h3>
                  <p>Weiteres Wachstum der Mitgliederzahl, Optimierung der Anbauplanung und Logistik.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2024</div>
                <div className="timeline-content">
                  <h3>Neue Website</h3>
                  <p>Launch der neuen Website mit modernem Design und verbesserter Benutzerführung.</p>
                </div>
              </div>
            </div>
            </div>
          </section>

          <section className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Mitmachen?</h3>
            </div>
            <div className="card-body">
              <p className="card-text">Werde Teil unserer Gemeinschaft und unterstütze die solidarische Landwirtschaft.</p>
            <CTA
              text="Jetzt Mitglied werden"
              href="/mitmachen"
              variant="primary"
            />
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