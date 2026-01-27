import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'

export default function ImpressumPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Impressum</h3>
            </div>
            <div className="card-body">
            <div style={{ marginTop: '24px' }}>
              <h2>Kontakt</h2>
              <p>
                <strong>Gemüsegenossenschaft biocò</strong><br />
                Geisshof<br />
                5412 Gebenstorf<br />
                Schweiz
              </p>
              <p>
                <strong>E-Mail:</strong> <a href="mailto:info@bioco.ch">info@bioco.ch</a>
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>Vertretungsberechtigte Personen</h2>
              <p>
                Betriebsgruppe der Gemüsegenossenschaft biocò
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>Haftungsausschluss</h2>
              <p>
                Der Inhalt dieser Website wurde mit grösster Sorgfalt erstellt. Für die Richtigkeit, 
                Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.
              </p>
            </div>

            <div style={{ marginTop: '24px' }}>
              <h2>Urheberrecht</h2>
              <p>
                Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen 
                dem schweizerischen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede 
                Art der Verwertung ausserhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen 
                Zustimmung des jeweiligen Autors bzw. Erstellers.
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
