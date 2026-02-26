import type { Metadata } from 'next'
import './globals.css'
import { MatomoScript } from '@/components/MatomoScript'
import { MarkerScript } from '@/components/MarkerScript'
import { OrganizationSchema, LocalBusinessSchema } from '@/components/StructuredData'
import { PathnameBodyClass } from '@/components/PathnameBodyClass'

export const metadata: Metadata = {
  title: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
  description: 'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft. Wöchentliche Gemüsekörbe vom Geisshof in Gebenstorf für die Region Baden-Brugg.',
  keywords: 'solidarische landwirtschaft, bio gemüse, demeter, gemüsegenossenschaft, baden, brugg, gebenstorf, aargau',
  icons: { icon: '/icon.png' },
  openGraph: {
    title: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
    description: 'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft.',
    type: 'website',
    locale: 'de_CH',
    url: 'https://bioco.ch',
    siteName: 'biocò',
  },
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="de">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet" />
      </head>
      <body>
        <PathnameBodyClass />
        <OrganizationSchema />
        <LocalBusinessSchema />
        {children}
        <MatomoScript />
        <MarkerScript />
      </body>
    </html>
  )
}
