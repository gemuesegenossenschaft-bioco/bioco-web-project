import { Metadata } from 'next'
import { getPageSections } from '@/lib/processwire'
import { WirClient } from '@/components/WirClient'

export const metadata: Metadata = {
  title: 'Über uns | Bio Bauernhof Baden | biocò Gemüsegenossenschaft',
  description: 'biocò Gemüsegenossenschaft: Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof Gebenstorf. Demeter-zertifiziertes Gemüse für Baden-Brugg.',
  keywords: 'bio bauernhof, solidarische landwirtschaft, gemüsegenossenschaft, baden, brugg, gebenstorf, demeter, geisshof',
  openGraph: {
    title: 'Über uns | Solidarische Landwirtschaft Baden | biocò',
    description: 'Seit 2014 solidarische Landwirtschaft auf dem Geisshof Gebenstorf. Demeter-zertifiziertes Bio-Gemüse für Baden-Brugg.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60
// Debug: force dynamic while diagnosing CMS fetch issues
export const dynamic = 'force-dynamic'

// Fallback content
const FALLBACK_INTRO = {
  title: 'biocò: Die Gemüsegenossenschaft',
  text: 'Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof in Gebenstorf. Lerne unser Team, unsere Geschichte und die Werte kennen, die unsere <a href="/solawi">solidarische Landwirtschaft</a> prägen.',
}

export default async function WirPage() {
  // Fetch CMS sections
  const cmsSections = await getPageSections('wir')
  
  // Get intro section
  const introSection = cmsSections.find(s => s.id === 'intro')
  
  const intro = {
    title: introSection?.title || FALLBACK_INTRO.title,
    text: introSection?.text || FALLBACK_INTRO.text,
  }

  return <WirClient intro={intro} sections={cmsSections} />
}
