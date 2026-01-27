import { SubscribeForm } from '@/components/forms/SubscribeForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'

export default function NewsletterPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-large">
            <div className="plant-pattern"></div>
            <h1>Newsletter abonnieren</h1>
            <SubscribeForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}