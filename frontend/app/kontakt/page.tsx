import { ContactForm } from '@/components/forms/ContactForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { getPageData } from '@/lib/processwire'

export default async function ContactPage() {
  const data = await getPageData('/kontakt/')
  
  return (
    <>
      <Header />
      <Hero 
        title={data?.hero_title || 'Kontakt'} 
        subtitle={data?.hero_subtitle || 'Wir freuen uns auf deine Nachricht'} 
      />
      <main className="main-content">
        <div className="content-grid">
          {data?.body && (
            <section className="content-card">
              <div dangerouslySetInnerHTML={{ __html: data.body }} />
            </section>
          )}
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
