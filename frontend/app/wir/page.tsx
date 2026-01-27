import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { EventsBanner } from '@/components/EventsBanner'
import { CTA } from '@/components/CTA'
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
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          {/* Page Header with H1 */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>biocò:<br />Die Gemüse-<br />genossenschaft</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
              Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof in Gebenstorf. 
              Lerne unser Team, unsere Geschichte und die Werte kennen, die unsere <Link href="/solawi">solidarische Landwirtschaft</Link> prägen.
            </p>
          </section>

          {/* Erste Zeile: Wir */}
          <section id="F-01" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Wir</h2>
            <h3 style={{ fontSize: '1.5rem', marginTop: '16px', marginBottom: '12px' }}>Team & Hof</h3>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>biocò ist eine Gemeinschaft von engagierten Menschen, die gemeinsam für frisches, regionales <Link href="/gemuese">Demeter-Gemüse</Link> sorgen.</p>
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '24px', marginTop: '24px' }}>
              <div>
                <div style={{ position: 'relative', width: '100%', aspectRatio: '1', marginBottom: '16px', borderRadius: '24px', overflow: 'hidden' }}>
                  <Image
                    src="/images/team/hofteam_matthias.JPG"
                    alt="Matthias vom Hof-Team - Demeter Landwirtschaft Geisshof"
                    fill
                    style={{ objectFit: 'cover' }}
                  />
                </div>
                <h3>Matthias</h3>
                <p>Hof-Team</p>
              </div>
              
              <div>
                <div style={{ position: 'relative', width: '100%', aspectRatio: '1', marginBottom: '16px', borderRadius: '24px', overflow: 'hidden' }}>
                  <Image
                    src="/images/team/bioco_hofteam_christian.JPG"
                    alt="Michael vom Hof-Team - Demeter Landwirtschaft Geisshof"
                    fill
                    style={{ objectFit: 'cover' }}
                  />
                </div>
                <h3>Michael</h3>
                <p>Hof-Team</p>
              </div>
              
              <div>
                <div style={{ position: 'relative', width: '100%', aspectRatio: '4/3', marginBottom: '16px', borderRadius: '24px', overflow: 'hidden' }}>
                  <Image
                    src="/images/team/alle-mitglieder-bioco.jpeg"
                    alt="Mitglieder der Gemüsegenossenschaft biocò - Solidarische Landwirtschaft Baden"
                    fill
                    style={{ objectFit: 'cover' }}
                  />
                </div>
                <h3>Alle Mitglieder</h3>
                <p>Jede(r) Genossenschafter/in bringt sich ein – ob bei der Feldarbeit, in der Logistik oder bei Events.</p>
              </div>
              
              <div>
                <div style={{ position: 'relative', width: '100%', aspectRatio: '4/3', marginBottom: '16px', borderRadius: '24px', overflow: 'hidden' }}>
                  <Image
                    src="/images/team/betriebsgruppe.JPG"
                    alt="Betriebsgruppe der Gemüsegenossenschaft biocò Gebenstorf"
                    fill
                    style={{ objectFit: 'cover', objectPosition: 'bottom' }}
                  />
                </div>
                <h3>Betriebsgruppe (BG)</h3>
                <p>Die Betriebsgruppe koordiniert den Anbau, die Logistik und die Organisation der Genossenschaft.</p>
              </div>
            </div>
          </section>

          <section id="F-01b" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Der Geisshof</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              Wir bewirtschaften einen Bio Bauernhof in Baden – genauer gesagt den Geisshof in 
              Gebenstorf im Aargau. Seit 2014 ist dieser Ort das Herzstück von biocò, wo wir 
              Bio-Gemüse in Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg 
              versorgen wir die Region mit frischem, saisonalem Gemüse. Hier finden die Feldarbeit, 
              die Gemüseaufbereitung und viele gemeinsame Anlässe statt.
            </p>
              
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '24px', marginBottom: '24px' }}>
              <div style={{ position: 'relative', width: '100%', aspectRatio: '4/3', borderRadius: '24px', overflow: 'hidden' }}>
                <Image
                  src="/images/hof/bioco_hof_luftaufnahme_grosses-feld.JPG"
                  alt="Bio-Gemüse Anbaufläche auf dem Geisshof Gebenstorf"
                  fill
                  style={{ objectFit: 'cover' }}
                />
              </div>
              <div style={{ position: 'relative', width: '100%', aspectRatio: '4/3', borderRadius: '24px', overflow: 'hidden' }}>
                <Image
                  src="/images/hof/bioco_hof_luftaufnahme-kleines-feld.JPG"
                  alt="Demeter Gemüsefeld auf dem Geisshof in Gebenstorf"
                  fill
                  style={{ objectFit: 'cover' }}
                />
              </div>
            </div>
              
            <p style={{ marginTop: '16px' }}>
              <Link href="/standorte-depots" className="btn btn-secondary btn-organic" style={{ display: 'inline-block' }}>
                Anfahrtsweg zum Geisshof
              </Link>
            </p>
          </section>

          <section id="F-02" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Mission & Leitbild</h2>
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '24px', marginTop: '24px' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Solidarität</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>
                  Wir teilen Arbeit und Ertrag. Solidarische Landwirtschaft bedeutet, 
                  dass Produzentinnen und Konsumentinnen zusammenarbeiten und füreinander einstehen.
                </p>
                <p style={{ marginTop: '12px' }}>
                  <Link href="/solawi" className="btn btn-secondary btn-organic" style={{ display: 'inline-block', fontSize: '0.875rem' }}>
                    → Mehr über solidarische Landwirtschaft erfahren
                  </Link>
                </p>
              </div>
              
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Nachhaltigkeit</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Wir arbeiten nach biologisch-dynamischen Prinzipien (Demeter) und fördern 
                  Biodiversität, Kreislaufwirtschaft und gesunde Böden.
                </p>
              </div>
              
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Gemeinschaft</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  biocò lebt von der Gemeinschaft. Jede(r) bringt sich ein, lernt voneinander und 
                  gestaltet die Genossenschaft aktiv mit.
                </p>
              </div>
              
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Regionalität</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Unser Gemüse wächst direkt in der Region Baden-Brugg. Kurze Wege, frische Ernte, 
                  lokale Verbundenheit.
                </p>
              </div>
            </div>

            <div style={{ marginTop: '32px' }}>
              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Gotti-System</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                Neumitglieder werden von einem "Gotti" oder "Götti" (Paten) begleitet. Dieses System 
                hilft neuen Mitgliedern, sich in der Genossenschaft zurechtzufinden und zeigt ihnen 
                die Abläufe und Möglichkeiten der Mitarbeit.
              </p>
            </div>
          </section>

          <section id="F-03" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Geschichte</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Die Gemüsegenossenschaft biocò wurde 2014 in Gebenstorf im Aargau gegründet. 
              Aus einer kleinen Gruppe engagierter Menschen aus Baden, Brugg und der Region 
              wurde eine lebendige Gemeinschaft, die solidarische Landwirtschaft lebt.
            </p>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Gestartet wurde auf dem Geisshof in Gebenstorf, wo wir bis heute unser Gemüse anbauen. 
              Über die Jahre haben wir die Anbaufläche erweitert, neue Standorte (Depots) für die 
              Gemüseabholung geschaffen und die Strukturen der Genossenschaft weiterentwickelt.
            </p>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
              Heute versorgen wir Mitglieder in der Region Baden-Brugg wöchentlich mit frischem, 
              saisonalem Demeter-Gemüse und leben gemeinsam die Prinzipien der Solidarischen Landwirtschaft.
            </p>
          </section>

          <section id="F-04" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Timeline</h2>
            <div className="timeline" style={{ marginTop: '24px' }}>
              <div className="timeline-item">
                <div className="timeline-year">2013</div>
                <div className="timeline-content">
                  <h3>Gründung</h3>
                  <p>Die Gründung von biocò fand am 15.11.2013 statt. Da war die Betriebsgruppe bereits sehr, sehr aktiv.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2014</div>
                <div className="timeline-content">
                  <h3>Erste Gartensaison</h3>
                  <p>War dann die erste Gartensaison und ab da gab es die ersten Depots.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2016</div>
                <div className="timeline-content">
                  <h3>Packraum</h3>
                  <p>Der Packraum wird erstellt und in Betrieb genommen. Ein wichtiger Schritt für die Genossenschaft.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2019-2023</div>
                <div className="timeline-content">
                  <h3>Mitgliederwachstum</h3>
                  <p>Weiteres Wachstum der Mitgliederzahl, Optimierung der Anbauplanung und Logistik.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2023</div>
                <div className="timeline-content">
                  <h3>Laufenten</h3>
                  <p>Wir haben zwei Pärchen Laufenten auf dem Hof, die uns bei der Schneckenjagd unterstützen.</p>
                </div>
              </div>
              
              <div className="timeline-item">
                <div className="timeline-year">2025</div>
                <div className="timeline-content">
                  <h3>Neue Website</h3>
                  <p>Launch der neuen Website mit modernem Design und verbesserter Benutzerführung.</p>
                </div>
              </div>
            </div>
          </section>

          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Mitmachen?</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>Werde Teil unserer Gemeinschaft und unterstütze die solidarische Landwirtschaft.</p>
            <CTA
              text="Jetzt Mitglied werden"
              href="/mitmachen"
              variant="primary"
            />
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
