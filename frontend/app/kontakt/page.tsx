import { ContactForm } from '@/components/forms/ContactForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Suspense } from 'react'
import { VisualEditorPageSwitch } from '@/components/VisualEditorPageSwitch'
import { getPageSections } from '@/lib/processwire'
import Link from 'next/link'

export const revalidate = 60

export default async function ContactPage() {
  const sections = await getPageSections('kontakt')

  const content = (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          <section>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>Kontakt</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '32px' }}>
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
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: 'var(--spacing-sm)' }}>
                Als Mitglied hast du Zugang zum Intranet, wo du alle wichtigen Informationen, Dokumente und Tools findest.
              </p>
              <Link href="/intranet" className="btn btn-primary btn-organic" style={{ display: 'inline-block' }}>
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
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: 'var(--spacing-sm)' }}>
                Interessierst du dich für ein Gemüseabo oder möchtest du mehr über die Mitgliedschaft erfahren? Hier findest du alle Informationen zur Anmeldung.
              </p>
              <Link href="/bioco-werden" className="btn btn-primary btn-organic" style={{ display: 'inline-block' }}>
                biocò werden →
              </Link>
            </div>

            <div id="kontakt-formular" style={{ marginTop: 'var(--spacing-lg)', paddingTop: 'var(--spacing-md)', scrollMarginTop: '100px' }}>
              <h4 style={{ marginBottom: 'var(--spacing-sm)', color: 'var(--text-primary)' }}>
                Allgemeine Anfragen
              </h4>
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: 'var(--spacing-md)' }}>
                Für alle anderen Fragen nutze bitte das Kontaktformular unten. Wir beantworten deine Anfrage gerne persönlich.
              </p>
            </div>

            <ContactForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )

  return (
    <Suspense fallback={content}>
      <VisualEditorPageSwitch sections={sections}>{content}</VisualEditorPageSwitch>
    </Suspense>
  )
}
