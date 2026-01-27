import { WaitingListForm } from '@/components/forms/WaitingListForm'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { Hero } from '@/components/Hero'
import { getPageData } from '@/lib/processwire'

export default async function WaitingListPage() {
  const data = await getPageData('/warteliste/')
  
  return (
    <>
      <Header />
      <Hero 
        title={data?.hero_title || 'Warteliste'} 
        subtitle={data?.hero_subtitle || 'Trag dich ein für nächste Saison'} 
      />
      <main className="main-content">
        <div className="content-grid">
          {data?.body && (
            <section className="content-card">
              <div dangerouslySetInnerHTML={{ __html: data.body }} />
            </section>
          )}
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
