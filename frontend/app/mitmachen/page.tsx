import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { SchnuppertageSection } from '@/components/SchnuppertageSection'
import Image from 'next/image'
import Link from 'next/link'
import { Metadata } from 'next'
import { getPageSections, getGroupCards } from '@/lib/processwire'
import type { GroupCard } from '@/lib/processwire-types'

export const metadata: Metadata = {
  title: 'Mitmachen bei solidarischer Landwirtschaft | biocò Baden',
  description: 'Werde Teil der Gemüsegenossenschaft biocò. Solidarische Landwirtschaft leben: Mitarbeit auf dem Geisshof und frisches Demeter-Gemüse für Baden-Brugg.',
  keywords: 'mitmachen, solidarische landwirtschaft, gemüsegenossenschaft, baden, brugg, mitarbeit, geisshof',
  openGraph: {
    title: 'Mitmachen bei solidarischer Landwirtschaft | biocò Baden',
    description: 'Werde Teil der Gemüsegenossenschaft biocò. Solidarische Landwirtschaft leben.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60
export const dynamic = 'force-dynamic'

export default async function MitmachenPage() {
  // Fetch CMS content with fallbacks
  const [cmsSections, cmsGroups] = await Promise.all([
    getPageSections('mitmachen'),
    getGroupCards(),
  ])
  
  const sections = cmsSections || []
  const groups: GroupCard[] = cmsGroups.length > 0 ? cmsGroups : []
  
  // Get specific sections
  const familienSection = sections.find(s => s.id === 'familien')
  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          {/* Page Header with H1 */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>Mitmachen bei biocò</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
              Werde Teil unserer Gemüsegenossenschaft und erlebe <Link href="/solawi">solidarische Landwirtschaft</Link> hautnah. 
              Hier erfährst du, wie du dich einbringen kannst und was Mitarbeit bei biocò bedeutet.
            </p>
          </section>

          <section id="D-01" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Was es braucht, damit wir gesundes Gemüse haben</h2>
            <h3 style={{ fontSize: '1.5rem', marginTop: '16px', marginBottom: '12px' }}>Mitarbeit bei biocò</h3>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Jedes Mitglied bringt sich ein und unterstützt die Genossenschaft aktiv. Die Mitarbeit ist ein wichtiger Teil unserer <Link href="/solawi">solidarischen Landwirtschaft</Link>.</p>

            <div style={{ 
              display: 'grid', 
              gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', 
              gap: '32px', 
              marginTop: '24px' 
            }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Tätigkeitsbereiche</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>Du kannst dich in verschiedenen Bereichen einbringen:</p>
                <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '0' }}>
                  <li><strong>Feld/Anbau:</strong> Säen, Pflanzen, Jäten, Ernten, Unkraut bekämpfen</li>
                  <li><strong>Logistik:</strong> Gemüse waschen, sortieren, packen, verteilen</li>
                  <li><strong>Administration:</strong> Büroarbeit, Rechnungen, Kommunikation</li>
                  <li><strong>Events/Organisation:</strong> Schnuppertage, Veranstaltungen, Gemeinschaftsanlässe</li>
                  <li><strong>Andere:</strong> Nach Absprache kannst du auch andere Fähigkeiten einbringen</li>
                </ul>
              </div>

              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Planung</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>Nach der Anmeldung erhältst du Zugang zum Intranet. Dort kannst du:</p>
                <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '0' }}>
                  <li>Deine bevorzugten Tage angeben (Mo-Sa)</li>
                  <li>Deine bevorzugten Zeiten wählen (morgens, nachmittags, abends)</li>
                  <li>Tätigkeitsbereiche auswählen</li>
                  <li>Arbeitseinsätze planen und buchen</li>
                </ul>
              </div>
            </div>

            <div id="anmelden" style={{ marginTop: '24px' }} />
          </section>

          <section id="D-02" style={{ marginBottom: 'clamp(24px, 4vw, 48px)' }}>
            <div style={{
              background: 'var(--surface-secondary, #f8f8f6)',
              borderRadius: '24px',
              padding: 'clamp(24px, 4vw, 48px)'
            }}>
              <h2 style={{ marginBottom: '12px' }}>Gruppen & Gemeinschaft</h2>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '32px' }}>
                Bei biocò gibt es verschiedene Arbeitsgruppen und Gemeinschaftsaktivitäten, die das Herzstück unserer Genossenschaft bilden:
              </p>
              
              {/* Card Grid - CMS-driven */}
              <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', 
                gap: '24px',
                marginBottom: '32px'
              }}>
                {groups.map((group) => (
                  <div 
                    key={group.id}
                    style={{ 
                      background: 'var(--bg-primary, #fff)', 
                      borderRadius: '16px', 
                      overflow: 'hidden',
                      boxShadow: '0 2px 8px rgba(0,0,0,0.06)'
                    }}
                  >
                    <div style={{ 
                      position: 'relative', 
                      width: '100%', 
                      aspectRatio: '4/3',
                      background: 'linear-gradient(135deg, rgba(var(--bioco-green-rgb), 0.15), rgba(var(--bioco-green-rgb), 0.05))',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center'
                    }}>
                      {group.image ? (
                        <Image
                          src={group.image}
                          alt={group.imageAlt || group.title}
                          fill
                          style={{ objectFit: 'cover' }}
                        />
                      ) : (
                        <span style={{ fontSize: '3rem', opacity: 0.6 }}>🌿</span>
                      )}
                    </div>
                    <div style={{ padding: '20px' }}>
                      <h3 style={{ fontSize: '1.25rem', marginBottom: '8px' }}>{group.title}</h3>
                      {group.text ? (
                        <div 
                          style={{ fontSize: '0.95rem', lineHeight: '1.6', color: 'var(--text-secondary)' }}
                          dangerouslySetInnerHTML={{ __html: group.text }}
                        />
                      ) : null}
                    </div>
                  </div>
                ))}
              </div>

              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                Diese Gruppen ermöglichen es, sich nach eigenen Interessen und Fähigkeiten einzubringen und die Genossenschaft aktiv mitzugestalten. Jede Gruppe trägt auf ihre Weise zum Erfolg und zur Gemeinschaft bei biocò bei.
              </p>
            </div>
          </section>
        </div>

        {/* Schnuppertage - Full Width Section with Different Background */}
        <div style={{ 
          background: 'linear-gradient(180deg, rgba(var(--bioco-green-rgb), 0.08) 0%, rgba(var(--bioco-green-rgb), 0.03) 100%)',
          paddingTop: 'clamp(24px, 4vw, 48px)',
          paddingBottom: 'clamp(48px, 8vw, 96px)'
        }}>
          <div style={{ maxWidth: '1400px', margin: '0 auto', padding: '0 clamp(16px, 6vw, 96px)' }}>
            <SchnuppertageSection />
          </div>
        </div>

        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: '0 clamp(16px, 6vw, 96px)' }}>
          <section id="D-03" className="familien-two-col" style={{ 
            marginBottom: 'clamp(48px, 8vw, 96px)', 
            marginTop: 'clamp(48px, 8vw, 96px)',
            display: 'grid',
            gridTemplateColumns: 'minmax(280px, 1fr) minmax(280px, 1fr)',
            gap: 'clamp(32px, 5vw, 64px)',
            alignItems: 'start'
          }}>
            <div style={{ 
              position: 'relative', 
              width: '100%', 
              aspectRatio: '4/3', 
              borderRadius: '24px', 
              overflow: 'hidden',
              minHeight: '280px'
            }}>
              {familienSection?.image && <Image
                src={familienSection.image}
                alt={familienSection?.imageAlt || 'Frisch geerntetes Demeter-Gemüse vom Geisshof'}
                fill
                style={{ objectFit: 'cover', borderRadius: '24px' }}
              />}
            </div>
            <div>
              <h2>{familienSection?.title || 'Familien & Kinder auf dem Geisshof'}</h2>
              {familienSection?.text ? (
                <div 
                  style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                  dangerouslySetInnerHTML={{ __html: familienSection.text }}
                />
              ) : (
                <>
                  <h3 style={{ fontSize: '1.25rem', marginTop: '16px', marginBottom: '12px' }}>Kinder sind willkommen</h3>
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                    Familien und Kinder sind sehr regelmässige Helfer auf dem Geisshof. Die Einbindung von Kindern 
                    in den Prozess des Gemüseanbaus ist ein zentraler Bestandteil der biocò-Kultur.
                  </p>
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                    Auf dem Geisshof erleben Kinder hautnah, wie Gemüse wächst, gepflegt wird und geerntet wird. 
                    Sie lernen spielerisch den Kreislauf der Natur kennen und entwickeln ein tiefes Verständnis für 
                    die Herkunft ihrer Nahrung. Diese praktische Erfahrung prägt nicht nur ihr Verhältnis zu Lebensmitteln, 
                    sondern stärkt auch das Gemeinschaftsgefühl zwischen den Generationen.
                  </p>
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                    Die Elki-Gruppe organisiert spezielle Aktivitäten für Familien, bei denen Kinder aktiv mithelfen 
                    können – sei es beim Säen, Jäten, Ernten oder beim gemeinsamen Verarbeiten des Gemüses. Diese 
                    gemeinsamen Erlebnisse schaffen bleibende Erinnerungen und fördern das Verständnis für nachhaltige 
                    Landwirtschaft von klein auf.
                  </p>
                </>
              )}
            </div>
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
