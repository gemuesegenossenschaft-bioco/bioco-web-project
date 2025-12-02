import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { EventsBanner } from '@/components/EventsBanner'
import { CTA } from '@/components/CTA'
import { PricingCalculator } from '@/components/PricingCalculator'
import Link from 'next/link'

export default function MitmachenPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section id="H-03" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Es gibt bei biocò vier Möglichkeiten der Partizipation</h3>
            </div>
            <div className="card-body">
              <PricingCalculator />
            </div>
          </section>

          <section id="H-02" className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Zuerst testen? (Probeabo)</h3>
            </div>
            <div className="card-body">
              <p className="card-text">Besuche einen unserer <Link href="/aktuelles">Schnuppertage</Link> oder teste unser Probe-Abo.</p>
              <p className="card-text" style={{ marginTop: '16px' }}><strong>Bedingungen für das Probeabo:</strong></p>
              <ul>
                <li>3 Monate lang</li>
                <li>Für CHF 300.-</li>
                <li>3 Einsätze</li>
              </ul>
            </div>
          </section>

          <section id="H-06" className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Nächste Schritte nach der Anmeldung</h3>
            </div>
            <div className="card-body">
            <div className="next-steps">
              <div className="step-item">
                <div className="step-number">1</div>
                <div>
                  <h3>Bestätigungs-E-Mail</h3>
                  <p>Du erhältst eine E-Mail mit Bestätigungslink (Double Opt-In). Bitte bestätige deine Anmeldung.</p>
                </div>
              </div>
              <div className="step-item">
                <div className="step-number">2</div>
                <div>
                  <h3>Rechnung</h3>
                  <p>Nach Bestätigung erhältst du eine Rechnung per 31. Januar. Du kannst quartalsweise oder das ganze Jahr bezahlen.</p>
                </div>
              </div>
              <div className="step-item">
                <div className="step-number">3</div>
                <div>
                  <h3>Start</h3>
                  <p>Du erhältst Zugang zum Intranet und kannst deine Arbeitseinsätze planen. Ab Januar startet die Gemüseverteilung!</p>
                </div>
              </div>
            </div>
            </div>
          </section>

          <section className="bento-card events-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Nächste Events</h3>
            </div>
            <div className="card-body">
              <EventsBanner showTitle={false} variant="embedded" />
            </div>
          </section>

          <section id="H-07" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Häufige Fragen (FAQ)</h3>
            </div>
            <div className="card-body">
            <div className="faq-list">
              <div className="faq-item">
                <h3>Was passiert, wenn ich nicht genug Gemüse bekomme?</h3>
                <p>Wir teilen Arbeit und Ertrag. Bei Ernteausfall aufgrund von Wetter oder anderen Umständen kann es vorkommen, dass die Ernte kleiner ausfällt. Dies ist Teil der solidarischen Landwirtschaft.</p>
              </div>
              <div className="faq-item">
                <h3>Kann ich das Abo pausieren?</h3>
                <p>Das Abo läuft jährlich vom 1. Januar bis 31. Dezember. Eine Pausierung ist nicht möglich, aber du kannst mit einer Frist von 2 Monaten zum Jahresende kündigen.</p>
              </div>
              <div className="faq-item">
                <h3>Was, wenn ich umziehe?</h3>
                <p>Bei Umzug kannst du dein Depot wechseln. Kontaktiere uns einfach unter <a href="mailto:info@bioco.ch">info@bioco.ch</a> oder nutze das <Link href="/kontakt">Kontaktformular</Link>.</p>
              </div>
              <div className="faq-item">
                <h3>Wie funktioniert die Kündigung?</h3>
                <p>Die Kündigung muss spätestens <strong>2 Monate vor Ende des Kalenderjahres</strong> (also bis 31. Oktober) erfolgen. Schicke uns eine E-Mail oder nutze das Kontaktformular.</p>
              </div>
              <div className="faq-item">
                <h3>Was ist der Unterschied zwischen Anteilsscheinen und Abo?</h3>
                <p><strong>Anteilsscheine</strong> sind Genossenschaftsanteile (CHF 250 pro Anteil), die für die Mitgliedschaft benötigt werden. Das <strong>Gemüseabo</strong> ist der Jahresbeitrag für den wöchentlichen Gemüsekorb. Beide sind für ein Gemüseabo nötig.</p>
              </div>
              <div className="faq-item">
                <h3>Kann ich mehrere Anteilsscheine kaufen?</h3>
                <p>Ja! Du kannst zusätzliche Anteilsscheine erwerben, auch ohne Gemüsekorb.</p>
              </div>
              <div className="faq-item">
                <h3>Wie plane ich meine Arbeitseinsätze?</h3>
                <p>Nach der Anmeldung erhältst du Zugang zum Intranet. Dort kannst du deine bevorzugten Tage, Zeiten und Tätigkeitsbereiche angeben und Arbeitseinsätze planen.</p>
              </div>
              <div className="faq-item">
                <h3>Was ist ein Probe-Abo?</h3>
                <p>Ein Probe-Abo läuft über 3 Monate und ermöglicht dir, biocò kennenzulernen. Du zahlst den proportionalen Anteil am Jahrespreis. Eine Umstellung auf ein Jahresabo ist jederzeit möglich.</p>
              </div>
            </div>
            </div>
          </section>

          <section id="H-08" className="bento-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Warteliste</h3>
            </div>
            <div className="card-body">
              <p className="card-text">Wenn alle Gemüsekörbe vergeben sind, kannst du dich auf die Warteliste setzen lassen. Du erhältst Vorrang, wenn ein Platz frei wird.</p>
              <Link href="/warteliste" className="btn btn-secondary" style={{ marginTop: '16px', display: 'inline-block' }}>
                Auf Warteliste setzen
              </Link>
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