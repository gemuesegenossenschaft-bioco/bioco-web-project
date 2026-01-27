import { VisitDayForm } from '@/components/forms/VisitDayForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'

export default function VisitDayPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <h1>Tag der offenen Tür - Anmeldung</h1>
            <VisitDayForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}