import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { Saisonkalender } from '@/components/Saisonkalender'
import Image from 'next/image'
import Link from 'next/link'
import { Metadata } from 'next'
import { getPageSectionsWithSeo } from '@/lib/processwire'
import { generateMetadata as generateSeoMetadata } from '@/lib/seo'

// Static fallback metadata (used when CMS data unavailable)
const FALLBACK_METADATA: Metadata = {
  title: 'Saisonales Demeter Gemüse | Welche Gemüse haben gerade Saison | biocò',
  description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf für die Region Baden-Brugg.',
  keywords: 'demeter gemüse, bio gemüse, saisonales gemüse, gebenstorf, baden, brugg, gemüseernte',
  openGraph: {
    title: 'Saisonales Demeter Gemüse | Welche Gemüse haben gerade Saison | biocò',
    description: 'Entdecke unser saisonales Bio-Gemüse in Demeter-Qualität. Frisch vom Geisshof in Gebenstorf.',
    type: 'website',
  },
}

// Dynamic metadata from CMS
export async function generateMetadata(): Promise<Metadata> {
  const { seo } = await getPageSectionsWithSeo('gemuese')
  
  // If CMS has SEO data, use it; otherwise use fallback
  if (seo?.title || seo?.description) {
    return generateSeoMetadata(seo, {
      title: FALLBACK_METADATA.title as string,
      description: FALLBACK_METADATA.description as string,
      path: '/gemuese',
    })
  }
  
  return FALLBACK_METADATA
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60
export const dynamic = 'force-dynamic'

function hasHeadingHtml(html?: string | null): boolean {
  return /<h[1-6]\b[^>]*>/i.test(String(html || ''))
}

export default async function GemusePage() {
  // Fetch CMS content
  const { sections: cmsSections } = await getPageSectionsWithSeo('gemuese')
  const introSection = cmsSections.find(s => s.id === 'intro')
  const anbauenSection =
    cmsSections.find((s) => s.id === 'B-02') ||
    cmsSections.find((s) => (s.title || '').toLowerCase().includes('anbauen'))
  const galleryImages = anbauenSection?.images || []

  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          {/* Page Header with H1 */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            {!hasHeadingHtml(introSection?.text) && (
              <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>
                {introSection?.title || 'Unser Gemüse'}
              </h1>
            )}
            {introSection?.text ? (
              <div 
                style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                dangerouslySetInnerHTML={{ __html: introSection.text }}
              />
            ) : (
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                Unser saisonales Demeter-Gemüse wächst in der Region Baden-Brugg. 
                Hier erfährst du, welche Gemüsesorten gerade Saison haben und in deinem Gemüsekorb landen.
              </p>
            )}
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
            {!hasHeadingHtml(anbauenSection?.text) && (
              <h2>{anbauenSection?.title || 'Was wir anbauen'}</h2>
            )}
            {anbauenSection?.text ? (
              <div
                style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}
                dangerouslySetInnerHTML={{ __html: anbauenSection.text }}
              />
            ) : (
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Einblicke in unsere Ernte, den Anbau und die Gemeinschaft</p>
            )}
            {galleryImages.length > 0 ? (
              <div className="gallery-grid">
                {galleryImages.map((image, index) => (
                  <div key={`${image.url}-${index}`} className="gallery-item">
                    <Image
                      src={image.url}
                      alt={image.alt || anbauenSection?.title || 'Gemüse'}
                      width={400}
                      height={300}
                      style={{ objectFit: 'cover', width: '100%', height: '100%', borderRadius: '8px' }}
                    />
                  </div>
                ))}
              </div>
            ) : (
              <div className="gallery-placeholder">
                <p>Keine Bilder im CMS gefunden.</p>
              </div>
            )}
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
