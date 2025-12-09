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
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          <section>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>biocò werden</h1>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '32px' }}>
              Wähle dein Gemüseabo und werde Teil unserer Gemüsegenossenschaft. 
              Deine Auswahl wird automatisch ins Anmeldeformular übernommen.
            </p>
            <PricingCalculator />
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}

