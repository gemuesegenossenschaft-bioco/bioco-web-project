import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import Link from 'next/link'

export default function DankePage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <h1>Vielen Dank für deine Anmeldung!</h1>
            <p style={{ fontSize: '1.125rem', marginBottom: '24px' }}>
              Wir prüfen deine Anmeldung. In der Zwischenzeit kannst du unseren Mitgliederbereich schon anschauen. 
              Klicke dafür oben rechts auf die Ente.
            </p>
            
            <div className="next-steps" style={{ marginTop: '32px' }}>
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

            <div style={{ marginTop: '32px', padding: '24px', background: 'var(--bg-secondary)', borderRadius: '12px' }}>
              <h3>Fragen?</h3>
              <p>Bei Fragen zur Anmeldung oder zu biocò kannst du uns jederzeit kontaktieren:</p>
              <p>
                <a href="mailto:info@bioco.ch" className="btn btn-secondary" style={{ display: 'inline-block', marginTop: '8px' }}>
                  info@bioco.ch
                </a>
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <Link href="/" className="btn btn-primary">
                Zurück zur Startseite
              </Link>
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
