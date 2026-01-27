import { VisitDayForm } from '@/components/forms/VisitDayForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { getPageData } from '@/lib/processwire'

export default async function VisitDayPage() {
  const data = await getPageData('/tag-der-offenen-tuer/')
  
  return (
    <>
      <Header />
      <Hero 
        title={data?.hero_title || 'Tag der offenen Tür'} 
        subtitle={data?.hero_subtitle || 'Besuche uns auf dem Geisshof'} 
      />
      <main className="main-content">
        <div className="content-grid">
          {data?.body && (
            <section className="content-card">
              <div dangerouslySetInnerHTML={{ __html: data.body }} />
            </section>
          )}
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
