import { SubscribeForm } from '@/components/forms/SubscribeForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'

export default function NewsletterPage() {
  return (
    <>
      <Header />
      <Hero title="Newsletter" subtitle="Bleib auf dem Laufenden" />
      <main className="main-content">
        <div className="content-grid">
          <section className="content-card">
            <h2>Newsletter abonnieren</h2>
            <SubscribeForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
