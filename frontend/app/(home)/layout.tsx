import type { Metadata } from 'next'
import { ReactNode } from 'react'

export const metadata: Metadata = {
  title: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
  description:
    'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft. Wöchentliche Gemüsekörbe vom Geisshof in Gebenstorf.',
  keywords:
    'Gemüsegenossenschaft, Baden-Brugg, Geisshof, Bio Gemüse, Demeter, solidarische Landwirtschaft',
  openGraph: {
    title: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
    description:
      'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft.',
    type: 'website',
    locale: 'de_CH',
    url: 'https://bioco.ch',
    siteName: 'biocò',
    images: [
      {
        url: 'https://bioco.ch/images/hero/bioco_hero-junge-mit-kuerbis.JPG',
        width: 1200,
        height: 630,
        alt: 'Solidarische Landwirtschaft auf dem Feld',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
    description:
      'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft.',
    images: ['https://bioco.ch/images/hero/bioco_hero-junge-mit-kuerbis.JPG'],
  },
  alternates: {
    canonical: 'https://bioco.ch',
  },
}

export default function HomeLayout({ children }: { children: ReactNode }) {
  return <>{children}</>
}


