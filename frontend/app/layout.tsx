import type { Metadata } from 'next'
import './globals.css'
import { MatomoScript } from '@/components/MatomoScript'
import { MarkerScript } from '@/components/MarkerScript'
import { OrganizationSchema, LocalBusinessSchema } from '@/components/StructuredData'
import { PathnameBodyClass } from '@/components/PathnameBodyClass'
import { BuildVersionWatcher } from '@/components/BuildVersionWatcher'
import { getCurrentBuildId } from '@/lib/buildId'
import { getGlobalSettings } from '@/lib/processwire'

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

function typographyCssVars(settings: Awaited<ReturnType<typeof getGlobalSettings>>): string {
  const h1 = settings?.h1
  const h2 = settings?.h2
  return `:root{` +
    `--cms-h1-color:${h1?.color ?? '#1a1a1a'};` +
    `--cms-h1-font-size-mobile:${h1?.fontSize?.mobile ?? 'calc(1.375rem + 1.5vw)'};` +
    `--cms-h1-font-size-desktop:${h1?.fontSize?.desktop ?? '2.5rem'};` +
    `--cms-h1-line-height:${h1?.lineHeight ?? '1.2'};` +
    `--cms-h1-font-weight:${h1?.fontWeight ?? '700'};` +
    `--cms-h1-letter-spacing:${h1?.letterSpacing ?? '0em'};` +
    `--cms-h2-color:${h2?.color ?? '#1a1a1a'};` +
    `--cms-h2-font-size-mobile:${h2?.fontSize?.mobile ?? 'calc(1.125rem + 0.7vw)'};` +
    `--cms-h2-font-size-desktop:${h2?.fontSize?.desktop ?? '1.75rem'};` +
    `--cms-h2-line-height:${h2?.lineHeight ?? '1.2'};` +
    `--cms-h2-font-weight:${h2?.fontWeight ?? '700'};` +
    `--cms-h2-letter-spacing:${h2?.letterSpacing ?? '0em'};` +
  `}`
}

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const globalSettings = await getGlobalSettings()
  const cssVars = typographyCssVars(globalSettings)
  const buildId = getCurrentBuildId()

  return (
    <html lang="de">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet" />
        <style id="cms-global-typography" dangerouslySetInnerHTML={{ __html: cssVars }} />
        <meta name="bioco-build-id" content={buildId} />
      </head>
      <body>
        <PathnameBodyClass />
        <BuildVersionWatcher initialBuildId={buildId} />
        <OrganizationSchema />
        <LocalBusinessSchema />
        {children}
        <MatomoScript />
        <MarkerScript />
      </body>
    </html>
  )
}
