import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import Link from 'next/link'
import { Metadata } from 'next'

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

export default function SolawiPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          {/* Page Header with H1 */}
          <section className="bento-card bento-card-fullwidth">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h1 style={{ fontSize: '2.5rem', margin: 0 }}>Was ist Solidarische Landwirtschaft?</h1>
            </div>
            <div className="card-body">
              <p className="card-text" style={{ fontSize: '1.125rem', lineHeight: '1.75' }}>
                Solidarische Landwirtschaft (kurz: Solawi oder SoLaWi) ist ein alternatives Konzept, 
                bei dem Konsument:innen und Produzent:innen gemeinsam Verantwortung für die 
                Lebensmittelproduktion übernehmen. Anstatt Gemüse einzeln im Laden zu kaufen, 
                finanzieren Mitglieder gemeinsam einen landwirtschaftlichen Betrieb und teilen 
                sich die Ernte – samt Erträgen, Risiken und Freuden.
              </p>
            </div>
          </section>

          {/* Was ist Solawi? */}
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h2>Was ist Solawi? – Definition</h2>
            </div>
            <div className="card-body">
              <p className="card-text">
                Das Konzept der Solidarischen Landwirtschaft stammt ursprünglich aus Japan 
                (Teikei-Bewegung) und den USA (Community Supported Agriculture, CSA). In der 
                Schweiz und Deutschland verbreitet sich das Solawi-Konzept seit den 2000er Jahren.
              </p>
              
              <p className="card-text">
                <strong>Kernprinzip:</strong> Nicht das einzelne Produkt (z.B. ein Kilogramm Karotten) 
                wird verkauft, sondern die Mitglieder einer Solawi finanzieren gemeinsam die 
                Betriebskosten eines Bauernhofs für ein Jahr. Dafür erhalten sie das ganze Jahr 
                über einen Anteil der Ernte – ob reichlich oder weniger üppig, je nach Saison 
                und Wetter.
              </p>

              <p className="card-text">
                Die <strong>SoLaWi</strong> basiert auf <strong>Vertrauen</strong>, 
                <strong> Transparenz</strong> und <strong>Gemeinschaft</strong>. Es geht nicht 
                um Gewinnmaximierung, sondern um faire Bedingungen für alle Beteiligten und 
                um ökologisch nachhaltige Landwirtschaft.
              </p>
            </div>
          </section>

          {/* Wie funktioniert es? */}
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h2>Wie funktioniert Solidarische Landwirtschaft?</h2>
            </div>
            <div className="card-body">
              <div style={{ marginBottom: '16px' }}>
                <h3>1. Gemeinsame Finanzierung</h3>
                <p className="card-text">
                  Zu Beginn des Jahres kalkuliert der Betrieb die voraussichtlichen Kosten 
                  (Saatgut, Arbeitskräfte, Maschinen, etc.). Diese Kosten werden auf alle 
                  Mitglieder aufgeteilt. Jedes Mitglied bezahlt einen jährlichen Beitrag und 
                  erwirbt damit einen oder mehrere Anteile.
                </p>
              </div>

              <div style={{ marginBottom: '16px' }}>
                <h3>2. Wöchentliche Ernte-Anteile</h3>
                <p className="card-text">
                  Im Gegenzug erhalten die Mitglieder wöchentlich ihren Anteil am Ernteertrag. 
                  Was gerade auf dem Feld wächst und reif ist, landet im Gemüsekorb – 
                  saisonal, frisch und vielfältig.
                </p>
              </div>

              <div style={{ marginBottom: '16px' }}>
                <h3>3. Mitarbeit und Teilhabe</h3>
                <p className="card-text">
                  Ein zentrales Element der Solawi ist die <strong>Mitarbeit</strong>. 
                  Mitglieder helfen bei Feldarbeiten, der Ernte oder der Logistik. 
                  Durch diese Beteiligung entsteht eine direkte Verbindung zur Landwirtschaft 
                  und ein tiefes Verständnis für die Arbeit, die hinter unserem Essen steckt.
                </p>
              </div>

              <div style={{ marginBottom: '16px' }}>
                <h3>4. Teilen von Risiko und Ertrag</h3>
                <p className="card-text">
                  In der Solawi tragen alle gemeinsam das Risiko: Hagelt es die Tomaten weg 
                  oder gibt es eine besonders gute Karottenernte? Alle Mitglieder profitieren 
                  oder verzichten gemeinsam. Diese Solidarität unterscheidet die Solawi 
                  fundamental vom klassischen Einkauf im Supermarkt.
                </p>
              </div>
            </div>
          </section>

          {/* Warum Solawi? */}
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h2>Warum Solawi? – Vorteile für Mitglieder & Umwelt</h2>
            </div>
            <div className="card-body">
              <h3>Vorteile für Konsument:innen</h3>
              <ul>
                <li><strong>Frisches, regionales Gemüse:</strong> Kurze Wege vom Feld zum Teller, maximale Frische</li>
                <li><strong>Transparenz:</strong> Du weisst genau, wo und wie dein Gemüse angebaut wird</li>
                <li><strong>Saisonalität erleben:</strong> Entdecke die Vielfalt saisonaler Gemüsesorten</li>
                <li><strong>Mitbestimmung:</strong> Mitglieder haben Mitspracherecht in der Genossenschaft</li>
                <li><strong>Gemeinschaft:</strong> Gemeinsam gärtnern, feiern und lernen</li>
              </ul>

              <h3>Vorteile für Produzent:innen</h3>
              <ul>
                <li><strong>Planungssicherheit:</strong> Finanzierung ist zu Jahresbeginn gesichert</li>
                <li><strong>Unabhängigkeit:</strong> Keine Abhängigkeit von Grossverteilern oder Marktpreisen</li>
                <li><strong>Direkter Kontakt:</strong> Persönlicher Austausch mit den Konsument:innen</li>
                <li><strong>Ökologischer Anbau:</strong> Fokus auf Nachhaltigkeit statt Gewinnmaximierung</li>
              </ul>

              <h3>Vorteile für die Umwelt</h3>
              <ul>
                <li><strong>Biologischer Anbau:</strong> Keine synthetischen Pestizide oder Dünger</li>
                <li><strong>Biodiversität:</strong> Förderung der Artenvielfalt durch vielfältige Fruchtfolgen</li>
                <li><strong>Kurze Transportwege:</strong> Regionale Versorgung statt globaler Lieferketten</li>
                <li><strong>Ressourcenschonung:</strong> Kreislaufwirtschaft und nachhaltige Bodenpflege</li>
              </ul>
            </div>
          </section>

          {/* Solawi bei biocò */}
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h2>Solidarische Landwirtschaft bei biocò</h2>
            </div>
            <div className="card-body">
              <p className="card-text">
                Die <strong>Gemüsegenossenschaft biocò</strong> ist eine Solawi in der Region 
                Baden-Brugg. Seit 2014 bewirtschaften wir den Geisshof in Gebenstorf nach 
                biologisch-dynamischen Prinzipien (Demeter-zertifiziert) und leben das 
                Solawi-Konzept mit über 100 Mitgliedern.
              </p>

              <p className="card-text">
                <strong>So funktioniert's bei biocò:</strong>
              </p>

              <ul>
                <li>
                  <strong>Jahresbeitrag:</strong> Mitglieder bezahlen zu Jahresbeginn ihren Anteil 
                  (je nach Korbgrösse CHF 750 – CHF 2'350)
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

              <p className="card-text">
                Bei biocò erleben Mitglieder hautnah, wie aus einem Samen eine Karotte wird, 
                wie Arbeit und Ernte geteilt werden und wie solidarische Landwirtschaft 
                in der Praxis funktioniert.
              </p>

              <div style={{ marginTop: '24px' }}>
                <CTA
                  text="Jetzt Mitglied werden"
                  href="/mitmachen"
                  variant="primary"
                />
              </div>
            </div>
          </section>

          {/* FAQ zu Solawi */}
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h2>Häufige Fragen zu Solawi</h2>
            </div>
            <div className="card-body">
              <div style={{ marginBottom: '20px' }}>
                <h3>Was bedeutet Solawi?</h3>
                <p className="card-text">
                  Solawi ist die Abkürzung für "Solidarische Landwirtschaft". Auch die Schreibweise 
                  "SoLaWi" ist verbreitet. International spricht man von "Community Supported 
                  Agriculture" (CSA).
                </p>
              </div>

              <div style={{ marginBottom: '20px' }}>
                <h3>Wie unterscheidet sich Solawi vom Abo-Gemüse?</h3>
                <p className="card-text">
                  Bei einem klassischen Gemüseabo kauft man eine Dienstleistung: X Gemüse für 
                  Y Franken. Bei der Solawi finanziert man gemeinsam einen Betrieb und teilt 
                  Risiko und Ertrag. Zudem ist die Mitarbeit und das Genossenschaftsmodell 
                  zentral für das Solawi-Konzept.
                </p>
              </div>

              <div style={{ marginBottom: '20px' }}>
                <h3>Muss ich zwingend mitarbeiten?</h3>
                <p className="card-text">
                  Bei biocò ist Mitarbeit Teil des Konzepts. Je nach Abo-Grösse arbeitet ihr 
                  10–40 Halbtage pro Jahr mit. Die Mitarbeit ist zentral für das Verständnis 
                  und die Verbindung zur Landwirtschaft.
                </p>
              </div>

              <div style={{ marginBottom: '20px' }}>
                <h3>Was passiert bei Ernteausfällen?</h3>
                <p className="card-text">
                  Bei der Solawi tragen alle das Risiko gemeinsam. Gibt es weniger Ernte 
                  (z.B. durch Hagel), gibt es auch weniger im Gemüsekorb. Umgekehrt profitieren 
                  alle von einer besonders guten Saison. Diese Solidarität ist das Herzstück 
                  der Solawi.
                </p>
              </div>

              <div style={{ marginBottom: '20px' }}>
                <h3>Kann ich selbst entscheiden, welches Gemüse ich bekomme?</h3>
                <p className="card-text">
                  Nein, bei der Solawi richtet sich der Gemüsekorb nach dem, was gerade Saison 
                  hat und auf dem Feld wächst. Das ist ein wesentlicher Teil des Konzepts: 
                  Du lernst, saisonal zu kochen und schätzt die Vielfalt der Jahreszeiten.
                </p>
              </div>

              <div style={{ marginBottom: '20px' }}>
                <h3>Wie kann ich mitmachen?</h3>
                <p className="card-text">
                  Informiere dich über unsere <Link href="/abos">Abo-Modelle</Link>, besuche 
                  einen unserer <Link href="/aktuelles">Schnuppertage</Link> oder 
                  <Link href="/kontakt"> kontaktiere uns direkt</Link>. Wir freuen uns auf dich!
                </p>
              </div>
            </div>
          </section>

          {/* Links zurück */}
          <section className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Mehr über biocò</h3>
            </div>
            <div className="card-body">
              <p className="card-text">
                Erfahre mehr über unsere Gemüsegenossenschaft:
              </p>
              <ul>
                <li><Link href="/wir">Über uns – Team & Geschichte</Link></li>
                <li><Link href="/gemuese">Unser saisonales Gemüse</Link></li>
                <li><Link href="/abos">Abo-Modelle & Preise</Link></li>
                <li><Link href="/standorte-depots">Depots & Standorte</Link></li>
                <li><Link href="/mitmachen">Mitmachen & Mitglied werden</Link></li>
              </ul>
            </div>
          </section>

          {/* CTA am Ende */}
          <section className="bento-card bento-card-fullwidth kennenlernen-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Bereit für solidarische Landwirtschaft?</h3>
            </div>
            <div className="card-body">
              <p className="card-text">
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
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}

