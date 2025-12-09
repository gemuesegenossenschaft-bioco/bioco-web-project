import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { getStaticEventItems } from '@/components/AktuellesData'
import Image from 'next/image'
import Link from 'next/link'
import { Metadata } from 'next'

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

export default function MitmachenPage() {
  const schnuppertage = getStaticEventItems().filter(
    (item) => item.status !== 'past'
  )

  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          {/* Page Header with H1 */}
          <section className="bento-card bento-card-fullwidth">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h1 style={{ fontSize: '2.5rem', margin: 0 }}>Mitmachen bei biocò</h1>
            </div>
            <div className="card-body">
              <p className="card-text">
                Werde Teil unserer Gemüsegenossenschaft und erlebe <Link href="/solawi">solidarische Landwirtschaft</Link> hautnah. 
                Hier erfährst du, wie du dich einbringen kannst und was Mitarbeit bei biocò bedeutet.
              </p>
            </div>
          </section>

          <section id="D-01" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Was es braucht, damit wir gesundes Gemüse haben</h3>
            </div>
            <div className="card-body">
              <div style={{ marginBottom: 'var(--spacing-md)' }}>
                <Image
                  src="/images/mitmachen/bioco_anpacken_einzel.JPG"
                  alt="Mitarbeit bei solidarischer Landwirtschaft auf dem Geisshof Gebenstorf"
                  width={800}
                  height={600}
                  style={{ width: '100%', height: 'auto', borderRadius: '12px' }}
                />
              </div>
              <h4 className="card-title">Mitarbeit bei biocò</h4>
              <p className="card-text">Jedes Mitglied bringt sich ein und unterstützt die Genossenschaft aktiv. Die Mitarbeit ist ein wichtiger Teil unserer <Link href="/solawi">solidarischen Landwirtschaft</Link>.</p>
            
            <div style={{ marginTop: '16px' }}>
            </div>

            <div style={{ marginTop: '16px' }}>
              <h3>Tätigkeitsbereiche</h3>
              <p>Du kannst dich in verschiedenen Bereichen einbringen:</p>
              <ul>
                <li><strong>Feld/Anbau:</strong> Säen, Pflanzen, Jäten, Ernten, Unkraut bekämpfen</li>
                <li><strong>Logistik:</strong> Gemüse waschen, sortieren, packen, verteilen</li>
                <li><strong>Administration:</strong> Büroarbeit, Rechnungen, Kommunikation</li>
                <li><strong>Events/Organisation:</strong> Schnuppertage, Veranstaltungen, Gemeinschaftsanlässe</li>
                <li><strong>Andere:</strong> Nach Absprache kannst du auch andere Fähigkeiten einbringen</li>
              </ul>
            </div>

            <div style={{ marginTop: '16px' }}>
              <h3>Planung</h3>
              <p>Nach der Anmeldung erhältst du Zugang zum Intranet. Dort kannst du:</p>
              <ul>
                <li>Deine bevorzugten Tage angeben (Mo-Sa)</li>
                <li>Deine bevorzugten Zeiten wählen (morgens, nachmittags, abends)</li>
                <li>Tätigkeitsbereiche auswählen</li>
                <li>Arbeitseinsätze planen und buchen</li>
              </ul>
            </div>

            <div id="anmelden" style={{ marginTop: '24px' }}>
              <CTA
                text="Jetzt anmelden"
                href="#anmelden"
                variant="primary"
              />
            </div>
            </div>
          </section>

          <section id="D-02" className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Gruppen & Gemeinschaft</h3>
            </div>
            <div className="card-body">
              <div style={{ marginBottom: 'var(--spacing-md)' }}>
                <Image
                  src="/images/mitmachen/zusammen-arbeiten.JPG"
                  alt="Gemeinschaft bei solidarischer Landwirtschaft biocò Baden-Brugg"
                  width={800}
                  height={600}
                  style={{ width: '100%', height: 'auto', borderRadius: '12px' }}
                />
              </div>
              <p className="card-text">Bei biocò gibt es verschiedene Arbeitsgruppen und Gemeinschaftsaktivitäten, die das Herzstück unserer Genossenschaft bilden:</p>
              <ul>
                <li><strong>Elki:</strong> Familienaktivitäten und gemeinsame Anlässe. Die Elki-Gruppe organisiert speziell für Familien mit Kindern ausgerichtete Aktivitäten auf dem Hof. Kinder können spielerisch den Anbau kennenlernen, gemeinsam ernten und die Natur entdecken. Diese Aktivitäten stärken das Gemeinschaftsgefühl und ermöglichen es, auch den jüngsten Mitgliedern die Werte der solidarischen Landwirtschaft zu vermitteln.</li>
                <li><strong>Kräutergruppe:</strong> Spezialisiert auf Kräuter und Gewürze. Diese Gruppe widmet sich dem Anbau, der Pflege und der Verarbeitung von Kräutern und Gewürzen. Mitglieder lernen verschiedene Kräuterarten kennen, erfahren mehr über deren Verwendung in der Küche und können ihre eigenen Kräuterprodukte herstellen. Die Kräutergruppe trägt zur Vielfalt unseres Angebots bei und bietet eine spezielle Nische für interessierte Mitglieder.</li>
                <li><strong>BG (Betriebsgruppe):</strong> Aktive Mitarbeit in der Betriebsorganisation. Die Betriebsgruppe koordiniert die strategischen Entscheidungen, plant die Anbauzyklen, organisiert die Logistik und sorgt für die reibungslose Abwicklung des täglichen Betriebs. Mitglieder der BG bringen ihre Expertise in verschiedenen Bereichen ein und gestalten die Zukunft der Genossenschaft aktiv mit.</li>
              </ul>
              <p className="card-text" style={{ marginTop: '16px' }}>Diese Gruppen ermöglichen es, sich nach eigenen Interessen und Fähigkeiten einzubringen und die Genossenschaft aktiv mitzugestalten. Jede Gruppe trägt auf ihre Weise zum Erfolg und zur Gemeinschaft bei biocò bei.</p>
            </div>
          </section>

          <section id="D-02b" className="bento-card bento-card-flat events-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Schnuppertage</h3>
            </div>
            <div className="card-body">
              <h4 className="card-title" style={{ color: 'var(--bioco-green-dark)' }}>
                Komm schnuppern: So geht solidarischer Gemüseanbau.
              </h4>
              <p className="card-text">
                Möchtest du dein Gemüse in Gemeinschaft anbauen und erfahren, wie es sich anfühlt, Teil einer Solawi zu sein?
                Dann komm an einen unserer Schnuppertage vorbei. Geniesse einen Nachmittag auf dem Geisshof in Gebenstorf AG,
                auf dem Feld umgeben von Natur und Tieren, Wildpflanzen, Bäumen, Beerensträuchern und Kräuterspirale.
              </p>
              <p className="card-text">
                Für deine Mithilfe am Schnuppernachmittag bekommst du als Dankeschön eine Tasche voll frisch geerntetem Demeter-Gemüse
                und ein kleines zVieri von uns spendiert. Nach dem Schnuppernachmittag darfst du gerne noch auf dem Gemeinschaftsplatz bleiben
                und etwas grillieren – es hat eine Feuerstelle, einen Sandkasten und viel Wiese. Neugierig? Schau vorbei und mach mit.
              </p>
              <div className="events-list">
                {schnuppertage.map((item, idx) => (
                  <div key={item.id || idx} className="event-item">
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                      <strong style={{ color: 'var(--bioco-green-dark)' }}>{item.title}</strong>
                      <span style={{ color: 'var(--text-secondary)' }}>{item.date}</span>
                      {item.timeLabel && (
                        <span style={{ color: 'var(--text-secondary)' }}>{item.timeLabel}</span>
                      )}
                    </div>
                    <p className="card-text" style={{ margin: '8px 0' }}>
                      Jeweils 14 - 17 Uhr in Gebenstorf AG
                    </p>
                    <p className="card-text">
                      Wir sind eine solidarische Landwirtschaft und bauen auf unserem Hof biologisches Gemüse in Demeter Qualität selber an.
                      Du möchtest bioco Luft schnuppern und dabei mehr erfahren? Wir bieten Schnuppertage auf dem Hof an. Was dich erwartet:
                    </p>
                    <ul>
                      <li>Gemeinschaft auf dem Feld, umgeben von Natur</li>
                      <li>Unser Hof liegt auf einem Hügel über Gebenstorf AG</li>
                      <li>Deine Hilfe auf dem Feld</li>
                      <li>Danke: du bekommst eine Tasche frisch geerntetes Demeter-Gemüse</li>
                      <li>Kleines zVieri von uns spendiert</li>
                      <li>Hof und Demeteranbau kennenlernen</li>
                      <li>Möglichkeit anschliessend auf dem Gemeinschaftsplatz zu bräteln</li>
                    </ul>
                    <p className="card-text">
                      Uns ist ein achtsamer Umgang mit der Natur wichtig, wir lassen viel Platz für Wildpflanzen, haben eine Kräuterspirale,
                      eine Naschecke mit Beeren, Sandkasten und Enten auf dem Hof. Auf dem Gemeinschaftsplatz hat es einen Sandkasten für Kinder
                      und eine Feuerstelle. Falls du noch bleiben möchtest, kannst du anschliessend gerne etwas über dem Feuer bräteln.
                      Ein kleines zVieri wird spendiert und du hast auch die Gelegenheit, den Hof kennenzulernen und Fragen zum Demeter-Anbau zu stellen.
                    </p>
                    <p className="card-text" style={{ fontStyle: 'italic', color: 'var(--text-secondary)' }}>
                      Hinweis: Formulareingänge gehen an medien@bioco.ch.
                    </p>
                    <Link href="#anmelden" className="btn btn-secondary" style={{ marginTop: '8px', alignSelf: 'flex-start' }}>
                      Jetzt anmelden
                    </Link>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section id="D-03" className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Familien & Kinder auf dem Geisshof</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 'var(--spacing-md)', alignItems: 'start' }}>
                <div>
                  <Image
                    src="/images/ernte/bioco_ernte-kürbis-hoch.JPG"
                    alt="Frisch geerntetes Demeter-Gemüse vom Geisshof"
                    width={800}
                    height={600}
                    style={{ width: '100%', height: 'auto', borderRadius: '12px' }}
                  />
                </div>
                <div>
                  <h4 className="card-title">Kinder sind willkommen</h4>
                  <p className="card-text">
                    Familien und Kinder sind sehr regelmässige Helfer auf dem Geisshof. Die Einbindung von Kindern 
                    in den Prozess des Gemüseanbaus ist ein zentraler Bestandteil der biocò-Kultur.
                  </p>
                  <p className="card-text">
                    Auf dem Geisshof erleben Kinder hautnah, wie Gemüse wächst, gepflegt wird und geerntet wird. 
                    Sie lernen spielerisch den Kreislauf der Natur kennen und entwickeln ein tiefes Verständnis für 
                    die Herkunft ihrer Nahrung. Diese praktische Erfahrung prägt nicht nur ihr Verhältnis zu Lebensmitteln, 
                    sondern stärkt auch das Gemeinschaftsgefühl zwischen den Generationen.
                  </p>
                  <p className="card-text">
                    Die Elki-Gruppe organisiert spezielle Aktivitäten für Familien, bei denen Kinder aktiv mithelfen 
                    können – sei es beim Säen, Jäten, Ernten oder beim gemeinsamen Verarbeiten des Gemüses. Diese 
                    gemeinsamen Erlebnisse schaffen bleibende Erinnerungen und fördern das Verständnis für nachhaltige 
                    Landwirtschaft von klein auf.
                  </p>
                </div>
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
