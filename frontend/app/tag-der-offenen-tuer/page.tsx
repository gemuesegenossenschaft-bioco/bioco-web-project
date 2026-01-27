import { VisitDayForm } from '@/components/forms/VisitDayForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'

export default function VisitDayPage() {
  return (
    <>
      <Header />
      <Hero title="Tag der offenen Tür" subtitle="Besuche uns auf dem Geisshof" />
      <main className="main-content">
        <div className="content-grid">
          <section className="content-card">
            <h2>Anmeldung</h2>
            <VisitDayForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
