import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { Suspense } from 'react'
import { VisualEditorPageSwitch } from '@/components/VisualEditorPageSwitch'
import Link from 'next/link'
import { Metadata } from 'next'
import { getPageSections } from '@/lib/processwire'

export const metadata: Metadata = {
  title: 'Was ist Solidarische Landwirtschaft (SoLaWi)? | biocò',
  description: 'Solidarische Landwirtschaft (Solawi/SoLaWi): Gemeinsam Verantwortung tragen für regionales Bio-Gemüse. Erfahre mehr über unser Konzept auf dem Geisshof.',
  keywords: 'solidarische landwirtschaft, solawi, solawi konzept, wie funktioniert solawi, gemüsegenossenschaft, csa, community supported agriculture',
  openGraph: {
    title: 'Was ist Solidarische Landwirtschaft (SoLaWi)? | biocò',
    description: 'Solidarische Landwirtschaft: Gemeinsam Verantwortung tragen für regionales Bio-Gemüse.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60

function hasHeadingHtml(html?: string | null): boolean {
  return /<h[1-6]\b[^>]*>/i.test(String(html || ''))
}

interface SolawiPageProps {
  searchParams?: {
    _visual?: string | string[]
  }
}

export default async function SolawiPage(_: SolawiPageProps) {
  // Fetch CMS content
  const cmsSections = await getPageSections('solawi')
  const introSection = cmsSections.find(s => s.id === 'intro')
  const content = (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          {/* Page Header with H1 */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            {!hasHeadingHtml(introSection?.text) && (
              <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>
                {introSection?.title || 'Solidarische Landwirtschaft'}
              </h1>
            )}
            {introSection?.text ? (
              <div 
                style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                dangerouslySetInnerHTML={{ __html: introSection.text }}
              />
            ) : (
              <>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                  Eine Solawi – Solidarische Landwirtschaft – ist eine gemeinschaftliche Form des Wirtschaftens,
                  bei der Verbraucherinnen und Produzentinnen eine Partnerschaft eingehen. Die Mitglieder tragen
                  gemeinsam die Kosten der landwirtschaftlichen Produktion und erhalten im Gegenzug einen
                  regelmässigen Anteil an frischem Bio-Gemüse aus lokalem Anbau.
                </p>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Statt anonym einzukaufen, entsteht eine direkte, verlässliche Verbindung zu dem Hof, der uns ernährt.
                </p>
              </>
            )}
          </section>

          {/* Was ist Solawi? */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Was ist Solawi? – Definition</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              In einer Solawi tragen alle Beteiligten die Verantwortung für die Lebensmittelproduktion gemeinsam.
              Die Mitglieder finanzieren nicht einzelne Produkte, sondern unterstützen den landwirtschaftlichen
              Betrieb als Ganzes – mit ihren Beiträgen und oft auch mit ihrer Zeit. Im Gegenzug teilen sie die Ernte:
              was auf dem Feld wächst, landet direkt im gemeinsamen Gemüsekorb.
            </p>

            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
              Solidarische Landwirtschaft lebt von <strong>Vertrauen</strong>, <strong>Transparenz</strong> und
              <strong> Gemeinschaft</strong>. Statt anonymer Märkte stehen Menschen und Beziehungen im Zentrum:
              Mitglieder wissen, wer ihr Gemüse anbaut – und der Hof weiss, für wen er arbeitet.
            </p>
          </section>

          {/* Wie funktioniert es? */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Wie funktioniert Solidarische Landwirtschaft?</h2>
            <div style={{ marginTop: '24px' }}>
              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>1. Gemeinsame Finanzierung</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Zu Beginn des Jahres kalkuliert der Betrieb die voraussichtlichen Kosten 
                  (Saatgut, Arbeitskräfte, Maschinen, etc.). Diese Kosten werden auf alle 
                  Mitglieder aufgeteilt. Jedes Mitglied bezahlt einen jährlichen Beitrag und 
                  erwirbt damit einen oder mehrere Anteile.
                </p>
              </div>

              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>2. Wöchentliche Ernte-Anteile</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Im Gegenzug erhalten die Mitglieder wöchentlich ihren Anteil am Ernteertrag. 
                  Was gerade auf dem Feld wächst und reif ist, landet im Gemüsekorb – 
                  saisonal, frisch und vielfältig.
                </p>
              </div>

              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>3. Mitarbeit und Teilhabe</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Ein zentrales Element der Solawi ist die <strong>Mitarbeit</strong>. 
                  Mitglieder helfen bei Feldarbeiten, der Ernte oder der Logistik. 
                  Durch diese Beteiligung entsteht eine direkte Verbindung zur Landwirtschaft 
                  und ein tiefes Verständnis für die Arbeit, die hinter unserem Essen steckt.
                </p>
              </div>

              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>4. Teilen von Risiko und Ertrag</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  In der Solawi tragen alle gemeinsam das Risiko: Hagelt es die Tomaten weg 
                  oder gibt es eine besonders gute Karottenernte? Alle Mitglieder profitieren 
                  oder verzichten gemeinsam. Diese Solidarität unterscheidet die Solawi 
                  fundamental vom klassischen Einkauf im Supermarkt.
                </p>
              </div>
            </div>
          </section>

          {/* Warum Solawi? */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Warum Solawi? – Vorteile für Mitglieder & Umwelt</h2>
            <div style={{ marginTop: '24px' }}>
              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Vorteile für Konsument:innen</h3>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
                <li><strong>Frisches, regionales Gemüse:</strong> Kurze Wege vom Feld zum Teller, maximale Frische</li>
                <li><strong>Transparenz:</strong> Du weisst genau, wo und wie dein Gemüse angebaut wird</li>
                <li><strong>Saisonalität erleben:</strong> Entdecke die Vielfalt saisonaler Gemüsesorten</li>
                <li><strong>Mitbestimmung:</strong> Mitglieder haben Mitspracherecht in der Genossenschaft</li>
                <li><strong>Gemeinschaft:</strong> Gemeinsam gärtnern, feiern und lernen</li>
              </ul>

              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Vorteile für Produzent:innen</h3>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
                <li><strong>Planungssicherheit:</strong> Finanzierung ist zu Jahresbeginn gesichert</li>
                <li><strong>Unabhängigkeit:</strong> Keine Abhängigkeit von Grossverteilern oder Marktpreisen</li>
                <li><strong>Direkter Kontakt:</strong> Persönlicher Austausch mit den Konsument:innen</li>
                <li><strong>Ökologischer Anbau:</strong> Fokus auf Nachhaltigkeit statt Gewinnmaximierung</li>
              </ul>

              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>Vorteile für die Umwelt</h3>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                <li><strong>Biologischer Anbau:</strong> Keine synthetischen Pestizide oder Dünger</li>
                <li><strong>Biodiversität:</strong> Förderung der Artenvielfalt durch vielfältige Fruchtfolgen</li>
                <li><strong>Kurze Transportwege:</strong> Regionale Versorgung statt globaler Lieferketten</li>
                <li><strong>Ressourcenschonung:</strong> Kreislaufwirtschaft und nachhaltige Bodenpflege</li>
              </ul>
            </div>
          </section>

          {/* Solawi bei biocò */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Solidarische Landwirtschaft bei biocò</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Auch bei bioco leben wir dieses Prinzip: Unsere Solawi ist Teil des Geisshofs in Gebenstorf AG,
              der nach strengen biologisch-dynamischen Grundsätzen arbeitet. Das bedeutet geschlossene Kreisläufe,
              schonende Bodenbewirtschaftung und Gemüse, das wirklich aus der Region kommt.
            </p>

            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Mit deinem Anteil und deiner Mitarbeit unterstützt du nicht nur den Anbau hochwertiger Demeter-Gemüsekisten,
              sondern auch eine Landwirtschaft, die sozial, ökologisch und langfristig tragfähig ist. Als lokale
              Gemüsegenossenschaft im Aargau leben wir Transparenz, Beteiligung und echte Nähe.
            </p>

            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Bei bioco bist du nicht nur Abnehmer*in, sondern Teil des Ganzen: Als Mitglied hilfst du bei der Feldarbeit mit,
              erlebst die Jahreszeiten auf dem Acker und siehst, wie echtes Bio-Gemüse aus solidarischer Landwirtschaft entsteht.
            </p>

            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              <strong>So funktioniert unsere Solawi in der Praxis:</strong>
            </p>

            <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              <li>
                <strong>Jahresbeitrag:</strong> Mitglieder bezahlen zu Jahresbeginn ihren Anteil 
                (je nach Korbgrösse CHF 750 – CHF 2&apos;350)
              </li>
              <li>
                <strong>Wöchentlicher Gemüsekorb:</strong> Fast jede Woche könnt ihr euren 
                Anteil in einem der <Link href="/standorte-depots">Depots in Baden, Brugg oder 
                Gebenstorf</Link> abholen
              </li>
              <li>
                <strong>Mitarbeit:</strong> Je nach Abo-Grösse arbeitet ihr 10–40 Halbtage pro 
                Jahr auf dem Feld mit (<Link href="/mitmachen">mehr zu Mitarbeit</Link>)
              </li>
              <li>
                <strong>Demeter-Qualität:</strong> Unser Gemüse erfüllt die strengsten 
                Bio-Standards (<Link href="/gemuese">mehr zu unserem Gemüse</Link>)
              </li>
              <li>
                <strong>Genossenschaftsmodell:</strong> Alle Mitglieder sind Teil der Genossenschaft 
                und können bei Entscheidungen mitbestimmen
              </li>
            </ul>

            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              Als Teil von bioco wirst du zudem Mitglied einer lebendigen, offenen Gemeinschaft rund um den
              einzigartig gelegenen Geisshof. Wir treffen uns zu Anlässen wie Open-Air-Kino auf dem Hof, Fondue über dem Feuer,
              Kräutergruppen-Treffen oder gemütlichen Nachmittagen am Gemeinschaftsplatz. Natur, Tiere, Sandkasten,
              eine aktive Eltern-Kind-Gruppe sowie gemeinsame Einsätze mit frisch gekochtem Essen machen unsere Solawi
              zu einem Ort, an dem man sich schnell zuhause fühlt.
            </p>

            <div style={{ marginTop: '24px' }}>
              <CTA
                text="Jetzt Mitglied werden"
                href="/mitmachen"
                variant="primary"
              />
            </div>
          </section>

          {/* FAQ zu Solawi */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Häufige Fragen zu Solawi</h2>
            <div style={{ marginTop: '24px' }}>
              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Was bedeutet Solawi?</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Solawi ist die Abkürzung für &quot;Solidarische Landwirtschaft&quot;. Auch die Schreibweise 
                  &quot;SoLaWi&quot; ist verbreitet. International spricht man von &quot;Community Supported 
                  Agriculture&quot; (CSA).
                </p>
              </div>

              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Wie unterscheidet sich Solawi vom Abo-Gemüse?</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Bei einem klassischen Gemüseabo kauft man eine Dienstleistung: X Gemüse für 
                  Y Franken. Bei der Solawi finanziert man gemeinsam einen Betrieb und teilt 
                  Risiko und Ertrag. Zudem ist die Mitarbeit und das Genossenschaftsmodell 
                  zentral für das Solawi-Konzept.
                </p>
              </div>

              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Muss ich zwingend mitarbeiten?</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Bei biocò ist Mitarbeit Teil des Konzepts. Je nach Abo-Grösse arbeitet ihr 
                  10–40 Halbtage pro Jahr mit. Die Mitarbeit ist zentral für das Verständnis 
                  und die Verbindung zur Landwirtschaft.
                </p>
              </div>

              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Was passiert bei Ernteausfällen?</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Bei der Solawi tragen alle das Risiko gemeinsam. Gibt es weniger Ernte 
                  (z.B. durch Hagel), gibt es auch weniger im Gemüsekorb. Umgekehrt profitieren 
                  alle von einer besonders guten Saison. Diese Solidarität ist das Herzstück 
                  der Solawi.
                </p>
              </div>

              <div style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Kann ich selbst entscheiden, welches Gemüse ich bekomme?</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Nein, bei der Solawi richtet sich der Gemüsekorb nach dem, was gerade Saison 
                  hat und auf dem Feld wächst. Das ist ein wesentlicher Teil des Konzepts: 
                  Du lernst, saisonal zu kochen und schätzt die Vielfalt der Jahreszeiten.
                </p>
              </div>

              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Wie kann ich mitmachen?</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Informiere dich über unsere <Link href="/abos">Abo-Modelle</Link>, besuche 
                  einen unserer <Link href="/aktuelles">Schnuppertage</Link> oder 
                  <Link href="/kontakt"> kontaktiere uns direkt</Link>. Wir freuen uns auf dich!
                </p>
              </div>
            </div>
          </section>

          {/* Links zurück */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Mehr über biocò</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Erfahre mehr über unsere Gemüsegenossenschaft:
            </p>
            <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
              <li><Link href="/wir">Über uns – Team & Geschichte</Link></li>
              <li><Link href="/gemuese">Unser saisonales Gemüse</Link></li>
              <li><Link href="/abos">Abo-Modelle & Preise</Link></li>
              <li><Link href="/standorte-depots">Depots & Standorte</Link></li>
              <li><Link href="/mitmachen">Mitmachen & Mitglied werden</Link></li>
            </ul>
          </section>

          {/* CTA am Ende */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Bereit für solidarische Landwirtschaft?</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              Werde Teil unserer Solawi-Gemeinschaft und erlebe, wie solidarische Landwirtschaft 
              in der Praxis funktioniert. Wir freuen uns auf dich!
            </p>
            <div style={{ marginTop: '16px', display: 'flex', gap: 'var(--spacing-md)', flexWrap: 'wrap' }}>
              <CTA
                text="Jetzt Mitglied werden"
                href="/mitmachen"
                variant="primary"
              />
              <CTA
                text="Nimm Kontakt auf"
                href="/kontakt"
                variant="secondary"
              />
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )

  return (
    <Suspense fallback={content}>
      <VisualEditorPageSwitch sections={cmsSections}>{content}</VisualEditorPageSwitch>
    </Suspense>
  )
}
