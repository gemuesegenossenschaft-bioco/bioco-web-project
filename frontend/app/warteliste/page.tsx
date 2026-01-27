import { WaitingListForm } from '@/components/forms/WaitingListForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'

export default function WaitingListPage() {
  return (
    <>
      <Header />
      <Hero title="Warteliste" subtitle="Trag dich ein für nächste Saison" />
      <main className="main-content">
        <div className="content-grid">
          <section className="content-card">
            <h2>Warteliste Anmeldung</h2>
            <WaitingListForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
