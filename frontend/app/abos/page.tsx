import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { EventsBanner } from '@/components/EventsBanner'
import { CTA } from '@/components/CTA'
import { PersonIcons } from '@/components/PersonIcons'
import Image from 'next/image'
import Link from 'next/link'
import { Metadata } from 'next'
import { ProductSchema } from '@/components/StructuredData'
import { getPageSectionsWithSeo } from '@/lib/processwire'
import { generateMetadata as generateSeoMetadata } from '@/lib/seo'

// Static fallback metadata
const FALLBACK_METADATA: Metadata = {
  title: 'Gemüseabo Baden | Demeter Gemüse wöchentlich | biocò',
  description: 'Gemüseabo für die Region Baden-Brugg: Wöchentlich frisches Bio-Gemüse in Demeter-Qualität. Solidarische Landwirtschaft vom Geisshof Gebenstorf.',
  keywords: 'gemüseabo, demeter gemüse, bio gemüse, baden, brugg, gebenstorf, wöchentlicher gemüsekorb',
  openGraph: {
    title: 'Gemüseabo Baden | Demeter Gemüse wöchentlich | biocò',
    description: 'Gemüseabo für die Region Baden-Brugg: Wöchentlich frisches Bio-Gemüse in Demeter-Qualität.',
    type: 'website',
  },
}

// Dynamic metadata from CMS
export async function generateMetadata(): Promise<Metadata> {
  const { seo } = await getPageSectionsWithSeo('abos')
  
  if (seo?.title || seo?.description) {
    return generateSeoMetadata(seo, {
      title: FALLBACK_METADATA.title as string,
      description: FALLBACK_METADATA.description as string,
      path: '/abos',
    })
  }
  
  return FALLBACK_METADATA
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

export default function AbosPage() {
  return (
    <>
      <ProductSchema />
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          {/* Page Header with H1 */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>Dein wöchentliches Gemüseabo</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
              Wöchentlich frisches Demeter-Gemüse direkt vom Geisshof in deinen Gemüsekorb. 
              Mit deinem Gemüseabo unterstützt du unsere <Link href="/solawi">solidarische Landwirtschaft (Solawi)</Link> und wirst Teil unserer Gemüsegenossenschaft.
              Hier erfährst du alles über unsere Abo-Modelle, Preise und wie du Mitglied werden kannst.
            </p>
          </section>

          <section id="C-01" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Gemüse-Abos</h2>
            <div style={{ marginTop: '24px' }}>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Das Gemüseabo läuft vom 1. Januar bis zum 31. Dezember. Ohne Kündigung verlängert sich das Gemüseabo jeweils um ein Kalenderjahr. Die Kündigungsfrist beträgt zwei Monate auf Ende eines Kalenderjahres.</p>
            
            <div className="pricing-table">
              <table>
                <thead>
                  <tr>
                    <th>Gemüsekorb</th>
                    <th>Personen</th>
                    <th>Jahrespreis</th>
                    <th>Anteilsscheine Kosten</th>
                    <th>Mitarbeit pro Jahr</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <strong>Halb</strong>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '4px' }}>
                        1 Anteilsschein
                      </div>
                    </td>
                    <td>
                      <PersonIcons count={1} />
                    </td>
                    <td>CHF 750.-</td>
                    <td>CHF 250.-</td>
                    <td>
                      10 Arbeitseinsätze<br />
                      <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>à 2 Stunden</span>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <strong>Standard</strong>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '4px' }}>
                        2 Anteilsscheine
                      </div>
                    </td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <PersonIcons count={2} />
                        <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>+</span>
                      </div>
                    </td>
                    <td>CHF 1&apos;280.-</td>
                    <td>CHF 500.-</td>
                    <td>
                      20 Arbeitseinsätze<br />
                      <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>à 2 Stunden</span>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <strong>Doppel</strong>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '4px' }}>
                        4 Anteilsscheine
                      </div>
                    </td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <PersonIcons count={4} />
                        <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>+</span>
                      </div>
                    </td>
                    <td>CHF 2&apos;350.-</td>
                    <td>CHF 1&apos;000.-</td>
                    <td>
                      40 Arbeitseinsätze<br />
                      <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>à 2 Stunden</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <div style={{ marginTop: '24px', textAlign: 'center' }}>
              <Link
                href="/bioco-werden"
                className="btn btn-orange btn-organic"
                style={{ display: 'inline-block', fontSize: '1.125rem', padding: '16px 32px' }}
              >
                Jetzt Abo wählen
              </Link>
            </div>
            
            <div style={{ marginTop: '24px', padding: '16px', background: 'var(--bg-secondary)', borderRadius: '12px' }}>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}><strong>Anteilsscheine:</strong> Jeder Anteilsschein kostet CHF 250.- und ist eine Bedingung für den Bezug eines Gemüsekorbes. Du kannst zusätzliche Anteilsscheine erwerben, um die Genossenschaft stärker zu unterstützen.</p>
              <p style={{ marginTop: '12px', fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}><strong>💡 Tipp:</strong> Geteilte Körbe sparen CHF 110 pro Jahr und reduzieren Logistikaufwand. Wir empfehlen, Körbe zu teilen!</p>
            </div>

            <div style={{ marginTop: '32px' }}>
              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Was ist im Gemüsekorb?</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>
                Bestellen Sie Ihr Biogemüse direkt vom Hof: Unsere Bio Gemüse Kiste kommt 
                wöchentlich frisch vom Geisshof. Die wöchentliche Bio Gemüse Lieferung landet 
                in einem unserer Depots, wo Sie Ihren Gemüsekorb abholen können.
              </p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginTop: '12px', marginBottom: '16px' }}>
                <li>Wöchentlicher Gemüsekorb mit saisonalem Gemüse</li>
                <li>Demeter-Qualität – höchste Bio-Standards</li>
                <li>Frisch vom Geisshof in Gebenstorf</li>
                <li>Abholung in einem der <Link href="/standorte-depots">Standorte</Link> (ab 16:00 uhr abholbereit)</li>
              </ul>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}><Link href="/gemuese">Mehr über unsere Ernte erfahren →</Link></p>
            </div>

            <div style={{ marginTop: '32px' }}>
              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Zahlungsweise</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>Die erste Rechnung wird per 31. Januar fällig. Du kannst wählen:</p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                <li><strong>Quartalsweise:</strong> Du bezahlst vierteljährlich</li>
                <li><strong>Ganzes Jahr:</strong> Du bezahlst den gesamten Jahresbeitrag einmalig</li>
              </ul>
            </div>

            <div style={{ marginTop: '32px' }}>
              <CTA
                text="Jetzt Abo bestellen"
                href="/mitmachen"
                variant="primary"
              />
            </div>
            </div>
          </section>

          {/* Probe-Abo und Anteilsscheine - Two Columns */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 'clamp(32px, 5vw, 64px)', alignItems: 'start', marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <section id="C-02">
              <h2>Probe-Abo</h2>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px', marginTop: '16px' }}>Möchtest du biocò erst einmal kennenlernen? Teste unser Gemüseabo für 3 Monate.</p>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}><strong>Details:</strong></p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
                <li>3 Monate Gemüsekorb</li>
                <li>Proportionaler Anteil am Jahrespreis</li>
                <li>Flexible Umstellung auf Jahresabo möglich</li>
              </ul>
              <CTA
                text="Probe-Abo testen"
                href="/mitmachen"
                variant="secondary"
              />
            </section>

            <section id="C-03">
              <h2>Anteilsscheine ohne Gemüsekorb</h2>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px', marginTop: '16px' }}>Du möchtest biocò unterstützen, ohne ein Gemüseabo zu beziehen? Das ist möglich!</p>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}><strong>Vorteile:</strong></p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                <li>Unterstützung der Genossenschaft</li>
                <li>Vorrang auf der Warteliste für einen Gemüsekorb</li>
                <li>Mitspracherecht in der Genossenschaft</li>
              </ul>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}><strong>Kosten:</strong> CHF 250.- pro Anteilsschein</p>
              <CTA
                text="Anteilsscheine erwerben"
                href="/mitmachen"
                variant="secondary"
              />
            </section>
          </div>

          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Nächste Events</h2>
            <div style={{ marginTop: '24px' }}>
              <EventsBanner showTitle={false} variant="embedded" />
            </div>
          </section>

          <section id="C-04" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Zusatz-Abos</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>In Planung: Partnerangebote wie Eier, Brot, Tofu und weitere regionale Produkte.</p>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Diese werden in Zukunft zusätzlich zum Gemüsekorb angeboten.</p>
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
