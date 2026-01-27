import { WaitingListForm } from '@/components/forms/WaitingListForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'

export default function WaitingListPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <h1>Warteliste</h1>
            <WaitingListForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}