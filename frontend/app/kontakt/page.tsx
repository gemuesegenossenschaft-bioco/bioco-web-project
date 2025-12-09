import { ContactForm } from '@/components/forms/ContactForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import Link from 'next/link'

export default function ContactPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Kontakt</h3>
            </div>
            <div className="card-body">
              <p className="card-text" style={{ marginBottom: 'var(--spacing-md)' }}>
                Hast du Fragen zu biocò? Wir freuen uns auf deine Nachricht! Wir melden uns in der Regel innerhalb von 2-3 Werktagen bei dir zurück.
              </p>

              <div style={{ 
                background: 'var(--bg-secondary)', 
                padding: 'var(--spacing-md)', 
                borderRadius: 'var(--radius-sm)',
                marginBottom: 'var(--spacing-md)'
              }}>
                <h4 style={{ marginBottom: 'var(--spacing-sm)', color: 'var(--text-primary)' }}>
                  Du bist bereits Mitglied?
                </h4>
                <p className="card-text" style={{ marginBottom: 'var(--spacing-sm)' }}>
                  Als Mitglied hast du Zugang zum Intranet, wo du alle wichtigen Informationen, Dokumente und Tools findest.
                </p>
                <Link href="/intranet" className="btn btn-primary" style={{ display: 'inline-block' }}>
                  Zum Intranet →
                </Link>
              </div>

              <div style={{ 
                background: 'var(--bg-secondary)', 
                padding: 'var(--spacing-md)', 
                borderRadius: 'var(--radius-sm)',
                marginBottom: 'var(--spacing-md)'
              }}>
                <h4 style={{ marginBottom: 'var(--spacing-sm)', color: 'var(--text-primary)' }}>
                  Möchtest du Mitglied werden?
                </h4>
                <p className="card-text" style={{ marginBottom: 'var(--spacing-sm)' }}>
                  Interessierst du dich für ein Gemüseabo oder möchtest du mehr über die Mitgliedschaft erfahren? Hier findest du alle Informationen zur Anmeldung.
                </p>
                <Link href="/mitmachen" className="btn btn-primary" style={{ display: 'inline-block' }}>
                  biocò werden →
                </Link>
              </div>

              <div style={{ marginTop: 'var(--spacing-lg)', paddingTop: 'var(--spacing-md)', borderTop: '2px solid var(--border-color)' }}>
                <h4 style={{ marginBottom: 'var(--spacing-sm)', color: 'var(--text-primary)' }}>
                  Allgemeine Anfragen
                </h4>
                <p className="card-text" style={{ marginBottom: 'var(--spacing-md)' }}>
                  Für alle anderen Fragen nutze bitte das Kontaktformular unten. Wir beantworten deine Anfrage gerne persönlich.
                </p>
              </div>

              <ContactForm />
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}