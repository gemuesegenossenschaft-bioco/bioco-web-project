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

            <div style={{ marginTop: '24px' }}>
              <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Tätigkeitsbereiche</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>Du kannst dich in verschiedenen Bereichen einbringen:</p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
                <li><strong>Feld/Anbau:</strong> Säen, Pflanzen, Jäten, Ernten, Unkraut bekämpfen</li>
                <li><strong>Logistik:</strong> Gemüse waschen, sortieren, packen, verteilen</li>
                <li><strong>Administration:</strong> Büroarbeit, Rechnungen, Kommunikation</li>
                <li><strong>Events/Organisation:</strong> Schnuppertage, Veranstaltungen, Gemeinschaftsanlässe</li>
                <li><strong>Andere:</strong> Nach Absprache kannst du auch andere Fähigkeiten einbringen</li>
              </ul>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>Planung</h3>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>Nach der Anmeldung erhältst du Zugang zum Intranet. Dort kannst du:</p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
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
          </section>

          <section id="D-02" className="two-column-section" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <div className="two-column-text">
              <h2>Gruppen & Gemeinschaft</h2>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                Bei biocò gibt es verschiedene Arbeitsgruppen und Gemeinschaftsaktivitäten, die das Herzstück unserer Genossenschaft bilden:
              </p>
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                <li style={{ marginBottom: '16px' }}>
                  <strong>Elki:</strong> Familienaktivitäten und gemeinsame Anlässe. Die Elki-Gruppe organisiert speziell für Familien mit Kindern ausgerichtete Aktivitäten auf dem Hof. Kinder können spielerisch den Anbau kennenlernen, gemeinsam ernten und die Natur entdecken. Diese Aktivitäten stärken das Gemeinschaftsgefühl und ermöglichen es, auch den jüngsten Mitgliedern die Werte der solidarischen Landwirtschaft zu vermitteln.
                </li>
                <li style={{ marginBottom: '16px' }}>
                  <strong>Kräutergruppe:</strong> Spezialisiert auf Kräuter und Gewürze. Diese Gruppe widmet sich dem Anbau, der Pflege und der Verarbeitung von Kräutern und Gewürzen. Mitglieder lernen verschiedene Kräuterarten kennen, erfahren mehr über deren Verwendung in der Küche und können ihre eigenen Kräuterprodukte herstellen. Die Kräutergruppe trägt zur Vielfalt unseres Angebots bei und bietet eine spezielle Nische für interessierte Mitglieder.
                </li>
                <li style={{ marginBottom: '16px' }}>
                  <strong>BG (Betriebsgruppe):</strong> Aktive Mitarbeit in der Betriebsorganisation. Die Betriebsgruppe koordiniert die strategischen Entscheidungen, plant die Anbauzyklen, organisiert die Logistik und sorgt für die reibungslose Abwicklung des täglichen Betriebs. Mitglieder der BG bringen ihre Expertise in verschiedenen Bereichen ein und gestalten die Zukunft der Genossenschaft aktiv mit.
                </li>
              </ul>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                Diese Gruppen ermöglichen es, sich nach eigenen Interessen und Fähigkeiten einzubringen und die Genossenschaft aktiv mitzugestalten. Jede Gruppe trägt auf ihre Weise zum Erfolg und zur Gemeinschaft bei biocò bei.
              </p>
            </div>
            <div className="two-column-image">
              <Image
                src="/images/mitmachen/zusammen-arbeiten.JPG"
                alt="Gemeinschaft bei solidarischer Landwirtschaft biocò Baden-Brugg"
                fill
                style={{ objectFit: 'cover', borderRadius: '24px' }}
              />
            </div>
          </section>

          <section id="D-02b" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Schnuppertage</h2>
            <h3 style={{ fontSize: '1.25rem', marginTop: '16px', marginBottom: '12px', color: 'var(--bioco-green-dark)' }}>
              Komm schnuppern: So geht solidarischer Gemüseanbau.
            </h3>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Möchtest du dein Gemüse in Gemeinschaft anbauen und erfahren, wie es sich anfühlt, Teil einer Solawi zu sein?
              Dann komm an einen unserer Schnuppertage vorbei. Geniesse einen Nachmittag auf dem Geisshof in Gebenstorf AG,
              auf dem Feld umgeben von Natur und Tieren, Wildpflanzen, Bäumen, Beerensträuchern und Kräuterspirale.
            </p>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              Für deine Mithilfe am Schnuppernachmittag bekommst du als Dankeschön eine Tasche voll frisch geerntetem Demeter-Gemüse
              und ein kleines zVieri von uns spendiert. Nach dem Schnuppernachmittag darfst du gerne noch auf dem Gemeinschaftsplatz bleiben
              und etwas grillieren – es hat eine Feuerstelle, einen Sandkasten und viel Wiese. Neugierig? Schau vorbei und mach mit.
            </p>
            <div className="events-list">
              {schnuppertage.slice(0, 3).map((item, idx) => (
                <div key={item.id || idx} className="event-item" style={{ background: 'rgba(var(--bioco-green-rgb), 0.25)', padding: '16px', borderRadius: '12px', marginBottom: '16px' }}>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '4px', marginBottom: '8px' }}>
                    <strong style={{ color: 'var(--bioco-green-dark)' }}>{item.title}</strong>
                    <span style={{ color: 'var(--text-secondary)' }}>{item.date}</span>
                    {item.timeLabel && (
                      <span style={{ color: 'var(--text-secondary)' }}>{item.timeLabel}</span>
                    )}
                  </div>
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', margin: '8px 0' }}>
                    Jeweils 14 - 17 Uhr in Gebenstorf AG
                  </p>
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                    Wir sind eine solidarische Landwirtschaft und bauen auf unserem Hof biologisches Gemüse in Demeter Qualität selber an.
                    Du möchtest bioco Luft schnuppern und dabei mehr erfahren? Wir bieten Schnuppertage auf dem Hof an. Was dich erwartet:
                  </p>
                  <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                    <li>Gemeinschaft auf dem Feld, umgeben von Natur</li>
                    <li>Unser Hof liegt auf einem Hügel über Gebenstorf AG</li>
                    <li>Deine Hilfe auf dem Feld</li>
                    <li>Danke: du bekommst eine Tasche frisch geerntetes Demeter-Gemüse</li>
                    <li>Kleines zVieri von uns spendiert</li>
                    <li>Hof und Demeteranbau kennenlernen</li>
                    <li>Möglichkeit anschliessend auf dem Gemeinschaftsplatz zu bräteln</li>
                  </ul>
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                    Uns ist ein achtsamer Umgang mit der Natur wichtig, wir lassen viel Platz für Wildpflanzen, haben eine Kräuterspirale,
                    eine Naschecke mit Beeren, Sandkasten und Enten auf dem Hof. Auf dem Gemeinschaftsplatz hat es einen Sandkasten für Kinder
                    und eine Feuerstelle. Falls du noch bleiben möchtest, kannst du anschliessend gerne etwas über dem Feuer bräteln.
                    Ein kleines zVieri wird spendiert und du hast auch die Gelegenheit, den Hof kennenzulernen und Fragen zum Demeter-Anbau zu stellen.
                  </p>
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', fontStyle: 'italic', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                    Hinweis: Formulareingänge gehen an medien@bioco.ch.
                  </p>
                  <Link href="#anmelden" className="btn btn-primary btn-organic" style={{ display: 'inline-block', marginTop: '8px' }}>
                    Jetzt anmelden
                  </Link>
                </div>
              ))}
            </div>
          </section>

          <section id="D-03" className="two-column-section" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <div className="two-column-image">
              <Image
                src="/images/ernte/bioco_ernte-kürbis-hoch.JPG"
                alt="Frisch geerntetes Demeter-Gemüse vom Geisshof"
                fill
                style={{ objectFit: 'cover', borderRadius: '24px' }}
              />
            </div>
            <div className="two-column-text">
              <h2>Familien & Kinder auf dem Geisshof</h2>
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
