import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'

export default function IntranetPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>Intranet</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Das Intranet von biocò ist der interne Bereich für alle Mitglieder der Genossenschaft. Hier findest du wichtige Dokumente, Informationen und Tools für die tägliche Arbeit mit biocò.</p>
              
            <h3 style={{ fontSize: '1.5rem', marginTop: '24px', marginBottom: '12px' }}>Was findest du im Intranet?</h3>
            <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              <li><strong>Verteilplan</strong> – Dienstag und Freitag Abholpläne</li>
              <li><strong>Fahrspesen-Rückforderungsformular</strong> – Für Fahrspesen-Rückerstattungen</li>
              <li><strong>Interne Dokumente</strong> – Alle wichtigen Unterlagen</li>
              <li><strong>Mitgliederbereich</strong> – Persönliche Informationen und Einstellungen</li>
            </ul>

            <h3 style={{ fontSize: '1.5rem', marginTop: '24px', marginBottom: '12px' }}>Zugang zum Intranet</h3>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>Das Intranet ist nur für Mitglieder der Genossenschaft zugänglich. Du benötigst einen Zugang, um dich anzumelden.</p>
              
            <div style={{ marginBottom: '24px' }}>
              <a 
                href="https://intranet.bioco.ch" 
                target="_blank" 
                rel="noopener noreferrer"
                className="btn btn-primary btn-organic"
              >
                Zum Intranet →
              </a>
            </div>

            <h3 style={{ fontSize: '1.5rem', marginTop: '24px', marginBottom: '12px' }}>Fragen?</h3>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Hast du Fragen zum Intranet oder benötigst du Hilfe beim Zugang? Dann kontaktiere uns unter <a href="mailto:info@bioco.ch">info@bioco.ch</a>.</p>
          </section>

          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Dokumente</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Hier findest du die wichtigsten Dokumente aus dem Intranet:</p>
            
            <div className="document-list">
              <div className="document-item" style={{ marginBottom: '24px' }}>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '8px' }}>Verteilplan Dienstag und Freitag</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>Der aktuelle Verteilplan für die Gemüsekorb-Abholung.</p>
                <a 
                  href="https://bioco.ch/intranet-dokumente/" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="btn btn-secondary btn-organic"
                  style={{ display: 'inline-block' }}
                >
                  Verteilplan herunterladen (PDF)
                </a>
              </div>

              <div className="document-item">
                <h3 style={{ fontSize: '1.25rem', marginBottom: '8px' }}>Fahrspesen Rückforderungsformular</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>Formular für die Rückforderung von Fahrspesen.</p>
                <a 
                  href="https://bioco.ch/intranet-dokumente/" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="btn btn-secondary btn-organic"
                  style={{ display: 'inline-block' }}
                >
                  Formular herunterladen (PDF)
                </a>
              </div>
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
