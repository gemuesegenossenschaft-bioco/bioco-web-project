import { ContactForm } from '@/components/forms/ContactForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'

export default function ContactPage() {
  return (
    <>
      <Header />
      <Hero title="Kontakt" subtitle="Wir freuen uns auf deine Nachricht" />
      <main className="main-content">
        <div className="content-grid">
          <section className="content-card">
            <h2>Schreib uns</h2>
            <ContactForm />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
