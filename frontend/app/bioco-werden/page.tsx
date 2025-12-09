import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { PricingCalculator } from '@/components/PricingCalculator'
import { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'biocò werden | Mitglied werden | biocò Baden',
  description: 'Werde Mitglied der Gemüsegenossenschaft biocò. Wähle dein Gemüseabo und werde Teil unserer solidarischen Landwirtschaft.',
  keywords: 'biocò werden, mitglied werden, gemüseabo, anmeldung, solidarische landwirtschaft',
  openGraph: {
    title: 'biocò werden | Mitglied werden | biocò Baden',
    description: 'Werde Mitglied der Gemüsegenossenschaft biocò.',
    type: 'website',
  },
}

export default function BiocoWerdenPage() {
  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section className="bento-card bento-card-fullwidth">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h1 style={{ fontSize: '2.5rem', margin: 0 }}>biocò werden</h1>
            </div>
            <div className="card-body">
              <p className="card-text">
                Wähle dein Gemüseabo und werde Teil unserer Gemüsegenossenschaft. 
                Deine Auswahl wird automatisch ins Anmeldeformular übernommen.
              </p>
              <PricingCalculator />
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}

