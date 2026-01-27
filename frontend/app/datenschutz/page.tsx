import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'

export default function DatenschutzPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Datenschutzerklärung</h3>
            </div>
            <div className="card-body">
            <div style={{ marginTop: '24px' }}>
              <h2>1. Datenschutz auf einen Blick</h2>
              <h3>Allgemeine Hinweise</h3>
              <p>
                Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen 
                Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, 
                mit denen Sie persönlich identifiziert werden können.
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>2. Verantwortliche Stelle</h2>
              <p>
                Die verantwortliche Stelle für die Datenverarbeitung auf dieser Website ist:
              </p>
              <p>
                <strong>Gemüsegenossenschaft biocò</strong><br />
                Geisshof<br />
                5412 Gebenstorf<br />
                Schweiz<br />
                E-Mail: <a href="mailto:info@bioco.ch">info@bioco.ch</a>
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>3. Datenerfassung auf dieser Website</h2>
              <h3>Kontaktformular</h3>
              <p>
                Wenn Sie uns per Kontaktformular Anfragen zukommen lassen, werden Ihre Angaben aus dem 
                Anfrageformular inklusive der von Ihnen dort angegebenen Kontaktdaten zwecks Bearbeitung 
                der Anfrage und für den Fall von Anschlussfragen bei uns gespeichert. Diese Daten geben 
                wir nicht ohne Ihre Einwilligung weiter.
              </p>
              <h3>Double Opt-In (DOI)</h3>
              <p>
                Bei allen Formularen verwenden wir ein Double Opt-In Verfahren. Das bedeutet, dass Sie 
                nach der Anmeldung eine E-Mail erhalten, in der Sie um Bestätigung Ihrer Anmeldung gebeten 
                werden. Erst nach Bestätigung werden Ihre Daten gespeichert.
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>4. Cookies</h2>
              <p>
                Diese Website verwendet keine Cookies. Wir verwenden Matomo Analytics im cookieless Modus, 
                um die Nutzung der Website zu analysieren, ohne personenbezogene Daten zu speichern.
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>5. Ihre Rechte</h2>
              <p>
                Sie haben jederzeit das Recht, Auskunft über Ihre bei uns gespeicherten personenbezogenen 
                Daten zu erhalten. Sie haben ausserdem ein Recht auf Berichtigung, Sperrung oder Löschung 
                dieser Daten. Hierzu sowie zu weiteren Fragen zum Thema Datenschutz können Sie sich jederzeit 
                unter der im Impressum angegebenen Adresse an uns wenden.
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>6. SSL-Verschlüsselung</h2>
              <p>
                Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte 
                eine SSL-Verschlüsselung.
              </p>
            </div>
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
