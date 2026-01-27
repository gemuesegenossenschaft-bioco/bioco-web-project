import { SubscribeForm } from '@/components/forms/SubscribeForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { getPageData } from '@/lib/processwire'

export default async function NewsletterPage() {
  const data = await getPageData('/newsletter/')
  
  return (
    <>
      <Header />
      <Hero 
        title={data?.hero_title || 'Newsletter'} 
        subtitle={data?.hero_subtitle || 'Bleib auf dem Laufenden'} 
      />
      <main className="main-content">
        <div className="content-grid">
          {data?.body && (
            <section className="content-card">
              <div dangerouslySetInnerHTML={{ __html: data.body }} />
            </section>
          )}
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
