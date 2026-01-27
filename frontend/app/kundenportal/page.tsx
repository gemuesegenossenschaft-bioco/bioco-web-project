import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { EventsBanner } from '@/components/EventsBanner'

export default function KundenportalPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section id="I-01" className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <h1>Kundenportal</h1>
            <h2>Gateway</h2>
            <div className="portal-gateway">
              <div className="portal-tile">
                <div className="portal-icon">🦆</div>
                <h3>Mitglieder-Portal</h3>
                <p>Extern</p>
              </div>
              <div className="portal-tile">
                <div className="portal-icon">🦆</div>
                <h3>Einsatzplanung</h3>
                <p>Extern</p>
              </div>
            </div>
          </section>

          <EventsBanner />
        </div>
      </main>
      <Footer />
    </>
  )
}