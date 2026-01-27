import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import Link from 'next/link'

export default function StatutenPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <h1>Statuten</h1>
            <p>Die Statuten der Gemüsegenossenschaft biocò regeln die Struktur und Organisation der Genossenschaft.</p>
            
            <div style={{ marginTop: '24px', padding: '16px', background: 'var(--bg-secondary)', borderRadius: '12px' }}>
              <h3>Dokumente zum Download</h3>
              <ul style={{ marginTop: '8px' }}>
                <li>
                  <a 
                    href="https://bioco.ch/wp-content/uploads/2018/01/1801_Beitritts-Erklaerung.pdf" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="btn btn-secondary"
                    style={{ display: 'inline-block', marginTop: '8px' }}
                  >
                    Statuten (PDF)
                  </a>
                </li>
                <li>
                  <a 
                    href="https://bioco.ch/wp-content/uploads/2023/12/2023-Abo-Bestellung.pdf" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="btn btn-secondary"
                    style={{ display: 'inline-block', marginTop: '8px' }}
                  >
                    Reglement (PDF)
                  </a>
                </li>
              </ul>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>Über die Genossenschaft</h2>
              <p>
                Die Gemüsegenossenschaft biocò ist eine Genossenschaft nach schweizerischem Recht. 
                Sie wurde 2014 gegründet und betreibt solidarische Landwirtschaft (Community Supported Agriculture)
                auf dem Geisshof in Gebenstorf.
              </p>
              <p>
                Die Genossenschaft basiert auf dem Prinzip der Solidarität: Mitglieder teilen sich 
                Arbeit und Ertrag gemeinsam. Jede(r) Genossenschafter/in bringt sich aktiv ein 
                und trägt zur Gemeinschaft bei.
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>Mitgliedschaft</h2>
              <p>
                Um Mitglied zu werden, benötigst du Anteilsscheine der Genossenschaft (CHF 250 pro Anteil). 
                Die Anzahl der erforderlichen Anteile hängt vom gewählten Gemüsekorb ab:
              </p>
              <ul>
                <li>Halb Gemüsekorb: 1 Anteil</li>
                <li>Standard Gemüsekorb: 2 Anteile</li>
                <li>Doppel Gemüsekorb: 4 Anteile</li>
              </ul>
              <p style={{ marginTop: '16px' }}>
                <Link href="/mitmachen" className="btn btn-primary" style={{ display: 'inline-block' }}>
                  Jetzt Mitglied werden
                </Link>
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>Weitere Informationen</h2>
              <p>
                Für weitere Fragen zu den Statuten oder der Genossenschaft, kontaktiere uns unter{' '}
                <a href="mailto:info@bioco.ch">info@bioco.ch</a> oder nutze das{' '}
                <Link href="/kontakt">Kontaktformular</Link>.
              </p>
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
