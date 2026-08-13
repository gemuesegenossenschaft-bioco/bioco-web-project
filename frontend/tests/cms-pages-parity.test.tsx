import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render } from '@testing-library/react'
import { readFileSync } from 'node:fs'
import path from 'node:path'
import { SectionRenderer } from '@/components/sections/SectionRenderer'
import { resolveComponentRegistryEntry } from '@/lib/componentRegistry'
import { loadSeed, seedToSections } from './helpers/seedToSections'
import type { ContentSection } from '@/lib/processwire-types'

// F.4 — content parity for the 8 converted routes: the CMS seed rendered
// through SectionRenderer must carry every heading, paragraph, link and
// component of the formerly hardcoded page, and the rewritten page source
// must no longer contain the German body copy.

vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => <img data-testid="next-image" {...props} />,
}))
vi.mock('next/link', () => ({
  default: ({ href, children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => (
    <a href={String(href)} {...props}>{children}</a>
  ),
}))
vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))
vi.mock('@/components/forms/CaptchaField', () => ({
  CaptchaField: () => <div data-testid="captcha-field" />,
}))
vi.mock('@/hooks/useEventsFeed', () => ({
  useEventsFeed: () => ({ upcoming: [], past: [], isLoading: false }),
}))
vi.mock('@/components/EventsSection', () => ({
  EventsSection: ({ archiveUrl }: { archiveUrl?: string }) => <div data-testid="events-section-standard" data-archive-url={archiveUrl} />,
}))
// Leaflet maps inject external scripts in useEffect — mock for jsdom stability.
vi.mock('@/components/DepotMap', () => ({ DepotMap: () => <div data-testid="depot-map" /> }))
vi.mock('@/components/GeisshofMap', () => ({ GeisshofMap: () => <div data-testid="geisshof-map" /> }))
vi.mock('@/hooks/useGroupCards', () => ({
  useGroupCards: () => ({
    groups: [
      { id: 'g1', title: 'Feldgruppe', text: '<p>Gemeinsam auf dem Feld</p>', image: null, imageAlt: '' },
      { id: 'g2', title: 'Elki-Gruppe', text: '<p>Für Familien</p>', image: null, imageAlt: '' },
    ],
    isLoading: false,
  }),
}))

// Content-parity tests must never touch the network: any component that
// fetches on mount gets an instant empty JSON response instead of a socket
// that stalls against the sandbox proxy and hangs the worker at teardown.
vi.stubGlobal(
  'fetch',
  vi.fn(() =>
    Promise.resolve(
      new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } })
    )
  )
)

function pageSource(routeDir: string): string {
  return readFileSync(path.resolve(__dirname, '..', 'app', routeDir, 'page.tsx'), 'utf8')
}

function expectThinCmsPage(
  routeDir: string,
  slug: string,
  absentSignatures: string[],
  fetchFn = 'getPageSections'
) {
  const src = pageSource(routeDir)
  expect(src, `app/${routeDir}/page.tsx must fetch CMS sections for '${slug}'`).toContain(
    `${fetchFn}('${slug}')`
  )
  for (const signature of absentSignatures) {
    expect(src, `app/${routeDir}/page.tsx must not hardcode "${signature}"`).not.toContain(signature)
  }
}

// ---------------------------------------------------------------------------
// /impressum
// ---------------------------------------------------------------------------
describe('/impressum parity (seed: impressum)', () => {
  const sections = seedToSections(loadSeed('impressum'))

  it('renders one data-owned h1 followed by the original h2 sections', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)).toEqual(['Impressum'])
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).not.toContain('Impressum')
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toEqual([
      'Kontakt',
      'Vertretungsberechtigte Personen',
      'Haftungsausschluss',
      'Urheberrecht',
    ])
  })

  it('keeps every paragraph block and the mailto link', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const text = container.textContent || ''
    expect(text).toContain('Gemüsegenossenschaft biocò')
    expect(text).toContain('5412 Gebenstorf')
    expect(text).toContain('Betriebsgruppe der Gemüsegenossenschaft biocò')
    expect(text).toContain('Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte')
    expect(text).toContain('unterliegen dem schweizerischen Urheberrecht')
    expect(container.querySelector('a[href="mailto:info@bioco.ch"]')).toBeTruthy()
  })

  it('page source is a thin CMS page without German body copy', () => {
    expectThinCmsPage('impressum', 'impressum', [
      'Vertretungsberechtigte Personen',
      'grösster Sorgfalt',
      'schweizerischen Urheberrecht',
      '5412 Gebenstorf',
    ])
  })
})

// ---------------------------------------------------------------------------
// /datenschutz
// ---------------------------------------------------------------------------
describe('/datenschutz parity (seed: datenschutz)', () => {
  const sections = seedToSections(loadSeed('datenschutz'))

  it('renders one data-owned h1 plus numbered h2 and h3 subheadings', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)).toEqual(['Datenschutzerklärung'])
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toEqual([
      '1. Datenschutz auf einen Blick',
      '2. Verantwortliche Stelle',
      '3. Datenerfassung auf dieser Website',
      '4. Cookies',
      '5. Ihre Rechte',
      '6. SSL-Verschlüsselung',
    ])
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toEqual([
      'Allgemeine Hinweise',
      'Kontaktformular',
      'Double Opt-In (DOI)',
    ])
  })

  it('keeps every paragraph block and the mailto link', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const text = container.textContent || ''
    expect(text).toContain('was mit Ihren personenbezogenen Daten passiert')
    expect(text).toContain('Die verantwortliche Stelle für die Datenverarbeitung')
    expect(text).toContain('zwecks Bearbeitung der Anfrage und für den Fall von Anschlussfragen')
    expect(text).toContain('Double Opt-In Verfahren')
    expect(text).toContain('Matomo Analytics im cookieless Modus')
    expect(text).toContain('Recht auf Berichtigung, Sperrung oder Löschung')
    expect(text).toContain('eine SSL-Verschlüsselung')
    expect(container.querySelector('a[href="mailto:info@bioco.ch"]')).toBeTruthy()
  })

  it('page source is a thin CMS page without German body copy', () => {
    expectThinCmsPage('datenschutz', 'datenschutz', [
      'Datenschutz auf einen Blick',
      'Double Opt-In Verfahren',
      'Matomo Analytics im cookieless Modus',
      'Verantwortliche Stelle',
    ])
  })
})

// ---------------------------------------------------------------------------
// /statuten
// ---------------------------------------------------------------------------
describe('/statuten parity (seed: statuten)', () => {
  const sections = seedToSections(loadSeed('statuten'))

  it('renders exactly one h1 plus the original h2/h3 headings', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Statuten'])
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toContain('Dokumente zum Download')
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toEqual(['Über die Genossenschaft', 'Mitgliedschaft', 'Weitere Informationen'])
  })

  it('keeps every paragraph block, list items and text links', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const text = container.textContent || ''
    expect(text).toContain('regeln die Struktur und Organisation der Genossenschaft')
    expect(text).toContain('Sie wurde 2014 gegründet und betreibt solidarische Landwirtschaft')
    expect(text).toContain('Prinzip der Solidarität')
    expect(text).toContain('CHF 250 pro Anteil')
    const listItems = Array.from(container.querySelectorAll('li')).map((li) => li.textContent)
    expect(listItems).toEqual([
      'Halb Gemüsekorb: 1 Anteil',
      'Standard Gemüsekorb: 2 Anteile',
      'Doppel Gemüsekorb: 4 Anteile',
    ])
    expect(container.querySelector('a[href="mailto:info@bioco.ch"]')).toBeTruthy()
    expect(container.querySelector('a[href="/kontakt"]')).toBeTruthy()
  })

  it('renders the PDF and membership CTAs with their hrefs preserved in the seed', () => {
    const { getByRole } = render(<SectionRenderer sections={sections} />)
    getByRole('button', { name: 'Statuten (PDF)' })
    getByRole('button', { name: 'Reglement (PDF)' })
    getByRole('button', { name: 'Jetzt Mitglied werden' })

    // CTA renders buttons (window.open for files) — assert hrefs at data level.
    const downloads = sections.find((s) => s.id === 'downloads')
    expect(downloads?.buttons?.map((b) => b.href)).toEqual([
      '/statuten/13-11-15_Statuten_bioco.pdf',
      '/statuten/2212_Betriebsreglement.pdf',
    ])
    const mitgliedschaft = sections.find((s) => s.id === 'mitgliedschaft')
    expect(mitgliedschaft?.buttons?.map((b) => b.href)).toEqual(['/mitmachen'])
  })

  it('page source is a thin CMS page without German body copy', () => {
    expectThinCmsPage('statuten', 'statuten', [
      'Dokumente zum Download',
      'CHF 250 pro Anteil',
      'Prinzip der Solidarität',
      '13-11-15_Statuten_bioco.pdf',
    ])
  })
})

// ---------------------------------------------------------------------------
// /anmeldung/danke
// ---------------------------------------------------------------------------
describe('/anmeldung/danke parity (seed: anmeldung-danke)', () => {
  const sections = seedToSections(loadSeed('anmeldung-danke'))

  it('renders exactly one h1 and the intro paragraph', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Vielen Dank für deine Anmeldung!'])
    expect(container.textContent).toContain('Klicke dafür oben rechts auf die Ente')
  })

  it('renders the three numbered steps with their titles and texts', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const stepNumbers = Array.from(container.querySelectorAll('.next-steps .step-number')).map(
      (el) => el.textContent
    )
    expect(stepNumbers).toEqual(['1', '2', '3'])
    const stepTitles = Array.from(container.querySelectorAll('.next-steps .step-item h3')).map(
      (el) => el.textContent
    )
    expect(stepTitles).toEqual(['Bestätigungs-E-Mail', 'Rechnung', 'Start'])
    const text = container.textContent || ''
    expect(text).toContain('E-Mail mit Bestätigungslink (Double Opt-In)')
    expect(text).toContain('Rechnung per 31. Januar')
    expect(text).toContain('Ab Januar startet die Gemüseverteilung!')
  })

  it('keeps the Fragen block with a structured mailto CTA', () => {
    const { container, getByRole } = render(<SectionRenderer sections={sections} />)
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toContain('Fragen?')
    expect(container.textContent).toContain('kannst du uns jederzeit kontaktieren')
    getByRole('link', { name: 'info@bioco.ch' })
    const fragen = sections.find((s) => s.id === 'fragen')
    expect(fragen?.buttons).toEqual([
      { text: 'info@bioco.ch', href: 'mailto:info@bioco.ch', variant: 'secondary' },
    ])
  })

  it('renders the back-to-home CTA with href preserved in the seed', () => {
    const { getByRole } = render(<SectionRenderer sections={sections} />)
    getByRole('button', { name: 'Zurück zur Startseite' })
    const cta = sections.find((s) => s.id === 'zurueck-cta')
    expect(cta?.buttons?.map((b) => b.href)).toEqual(['/'])
  })

  it('page source is a thin CMS page without German body copy', () => {
    expectThinCmsPage('anmeldung/danke', 'anmeldung-danke', [
      'Vielen Dank für deine Anmeldung',
      'Bestätigungs-E-Mail',
      'Ab Januar startet die Gemüseverteilung',
      'Zurück zur Startseite',
    ])
  })
})

// ---------------------------------------------------------------------------
// /newsletter
// ---------------------------------------------------------------------------
describe('/newsletter parity (seed: newsletter)', () => {
  const sections = seedToSections(loadSeed('newsletter'))

  it('renders exactly one h1 and the real subscribe form', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Newsletter abonnieren'])

    // subscribe_form component renders the actual SubscribeForm
    const submit = container.querySelector('input[type="submit"]') as HTMLInputElement
    expect(submit?.value).toBe('Abonnieren')
    expect(container.textContent).toContain('E-Mail-Adresse *')
  })

  it('page source is a thin CMS page without hardcoded heading or form import', () => {
    expectThinCmsPage('newsletter', 'newsletter', [
      'Newsletter abonnieren',
      'SubscribeForm',
      '<h1>',
    ])
  })
})

// ---------------------------------------------------------------------------
// /warteliste
// ---------------------------------------------------------------------------
describe('/warteliste parity (seed: warteliste)', () => {
  const sections = seedToSections(loadSeed('warteliste'))

  it('renders exactly one h1 and the real waiting-list form', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Warteliste'])

    // waiting_list_form component renders the actual WaitingListForm
    const submit = container.querySelector('input[type="submit"]') as HTMLInputElement
    expect(submit?.value).toBe('Anmelden')
    expect(container.textContent).toContain('Interesse an *')
  })

  it('page source is a thin CMS page without hardcoded heading or form import', () => {
    expectThinCmsPage('warteliste', 'warteliste', [
      'Warteliste</h1>',
      'WaitingListForm',
      '<h1>',
    ])
  })
})

// ---------------------------------------------------------------------------
// /tag-der-offenen-tuer
// ---------------------------------------------------------------------------
describe('/tag-der-offenen-tuer parity (seed: tag-der-offenen-tuer)', () => {
  const sections = seedToSections(loadSeed('tag-der-offenen-tuer'))

  it('renders exactly one h1 (exact wording) and the real visit-day form', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Tag der offenen Tür - Anmeldung'])

    // visit_day_form component renders the actual VisitDayForm
    const submit = container.querySelector('input[type="submit"]') as HTMLInputElement
    expect(submit?.value).toBe('Anmelden')
    expect(container.textContent).toContain('Gewünschtes Datum *')
  })

  it('page source is a thin CMS page without hardcoded heading or form import', () => {
    expectThinCmsPage('tag-der-offenen-tuer', 'tag-der-offenen-tuer', [
      'Tag der offenen Tür - Anmeldung',
      'VisitDayForm',
      '<h1>',
    ])
  })
})

// ---------------------------------------------------------------------------
// /kundenportal
// ---------------------------------------------------------------------------
describe('/kundenportal parity (seed: kundenportal)', () => {
  const seed = loadSeed('kundenportal')
  const sections = seedToSections(seed)

  it('renders exactly one h1, the Gateway h2 and both portal tiles', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Kundenportal'])
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toContain('Gateway')

    const tiles = Array.from(container.querySelectorAll('.portal-gateway .portal-tile'))
    expect(tiles).toHaveLength(2)
    expect(tiles.map((t) => t.querySelector('h3')?.textContent)).toEqual([
      'Mitglieder-Portal',
      'Einsatzplanung',
    ])
    for (const tile of tiles) {
      expect(tile.querySelector('.portal-icon')?.textContent).toBe('🦆')
      expect(tile.querySelector('p')?.textContent).toBe('Extern')
      // hrefs are empty today → tiles are not links
      expect(tile.tagName).not.toBe('A')
    }
  })

  it('seed requests the events feed as banner variant with limit 3', () => {
    const events = sections.find((s) => s.id === 'events')
    expect(events?.component).toBe('events_feed')
    expect(events?.config).toMatchObject({
      variant: 'banner',
      limit: 3,
      archiveUrl: '/aktuelles',
      title: 'Nächste Events',
      archiveLabel: 'Alle Events ansehen →',
    })
  })

  it("renders the events_feed banner variant via EventsBanner (today's markup)", () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const banner = container.querySelector('.events-banner')
    expect(banner).toBeTruthy()
    expect(banner?.querySelector('h2')?.textContent).toBe('Nächste Events')
    expect(banner?.textContent).toContain('Aktuell keine Events geplant.')
    const allEventsLink = banner?.querySelector('a[href="/aktuelles"]')
    expect(allEventsLink?.textContent).toBe('Alle Events ansehen →')
  })

  it('events_feed without banner variant still renders the standard EventsSection', () => {
    const standard: ContentSection = {
      id: 'events-standard',
      title: '',
      text: '',
      layout: 'component',
      component: 'events_feed',
      config: { archiveUrl: '/events-archive' },
    }
    const { queryByTestId, container } = render(<SectionRenderer sections={[standard]} />)
    expect(queryByTestId('events-section-standard')).toBeTruthy()
    expect(queryByTestId('events-section-standard')).toHaveAttribute('data-archive-url', '/events-archive')
    expect(container.querySelector('.events-banner')).toBeNull()
  })

  it('events_feed registry owns variant, limit and archive config fields', () => {
    const entry = resolveComponentRegistryEntry('events_feed')?.entry
    expect(entry?.cmsFields).toContain('section_config')
    const schema = entry?.configSchema || []
    const variant = schema.find((f) => f.key === 'variant')
    expect(variant?.type).toBe('select')
    expect(variant?.label).toBe('Darstellung')
    expect(schema.find((f) => f.key === 'archiveUrl')?.type).toBe('text')
    expect((variant?.options || []).map((o) => o.value)).toEqual(['standard', 'banner'])
    const limit = schema.find((f) => f.key === 'limit')
    expect(limit?.type).toBe('number')
    expect(limit?.label).toBe('Anzahl Einträge')
  })

  it('page source is a thin CMS page without hardcoded portal content', () => {
    expectThinCmsPage('kundenportal', 'kundenportal', [
      'Mitglieder-Portal',
      'Einsatzplanung',
      'portal-gateway',
      'EventsBanner',
    ])
  })
})

// ===========================================================================
// F.5 — content-heavy routes
// ===========================================================================

// ---------------------------------------------------------------------------
// /solawi
// ---------------------------------------------------------------------------
describe('/solawi parity (seed: solawi)', () => {
  const sections = seedToSections(loadSeed('solawi'))

  it('renders exactly one h1 and all seven h2 sections in order', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Solidarische Landwirtschaft'])
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toEqual([
      'Was ist Solawi? – Definition',
      'Wie funktioniert Solidarische Landwirtschaft?',
      'Warum Solawi? – Vorteile für Mitglieder & Umwelt',
      'Solidarische Landwirtschaft bei biocò',
      'Häufige Fragen zu Solawi',
      'Mehr über biocò',
      'Bereit für solidarische Landwirtschaft?',
    ])
  })

  it('renders all h3 subsections (numbered steps, Vorteile, FAQ) in order', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toEqual([
      '1. Gemeinsame Finanzierung',
      '2. Wöchentliche Ernte-Anteile',
      '3. Mitarbeit und Teilhabe',
      '4. Teilen von Risiko und Ertrag',
      'Vorteile für Konsument:innen',
      'Vorteile für Produzent:innen',
      'Vorteile für die Umwelt',
      'Was bedeutet Solawi?',
      'Wie unterscheidet sich Solawi vom Abo-Gemüse?',
      'Muss ich zwingend mitarbeiten?',
      'Was passiert bei Ernteausfällen?',
      'Kann ich selbst entscheiden, welches Gemüse ich bekomme?',
      'Wie kann ich mitmachen?',
    ])
  })

  it('keeps paragraph copy and internal links', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const text = container.textContent || ''
    expect(text).toContain('Statt anonym einzukaufen, entsteht eine direkte, verlässliche Verbindung')
    expect(text).toContain("je nach Korbgrösse CHF 750 – CHF 2'350")
    expect(text).toContain('Community Supported Agriculture')
    expect(text).toContain('Open-Air-Kino auf dem Hof')
    for (const href of ['/standorte-depots', '/gemuese', '/abos', '/aktuelles', '/wir', '/kontakt']) {
      expect(container.querySelector(`a[href="${href}"]`), href).toBeTruthy()
    }
  })

  it('preserves the JSX whitespace artifacts byte-exact in the seed', () => {
    const wasIst = sections.find((s) => s.id === 'was-ist-solawi')
    expect(wasIst?.text).toContain('und<strong> Gemeinschaft</strong>')
    expect(wasIst?.text).not.toContain('und <strong>')
    const faq = sections.find((s) => s.id === 'faq')
    expect(faq?.text).toContain('oder<a href="/kontakt"> kontaktiere uns direkt</a>')
  })

  it('renders the membership CTAs with hrefs preserved in the seed', () => {
    const { getAllByRole, getByRole } = render(<SectionRenderer sections={sections} />)
    expect(getAllByRole('button', { name: 'Jetzt Mitglied werden' })).toHaveLength(2)
    getByRole('button', { name: 'Nimm Kontakt auf' })
    const bioco = sections.find((s) => s.id === 'solawi-bei-bioco')
    expect(bioco?.buttons?.map((b) => b.href)).toEqual(['/mitmachen'])
    const cta = sections.find((s) => s.id === 'kennenlernen-cta')
    expect(cta?.buttons?.map((b) => b.href)).toEqual(['/mitmachen', '/kontakt'])
  })

  it('page source is a thin CMS page without German body copy', () => {
    expectThinCmsPage('solawi', 'solawi', [
      'Gemeinsame Finanzierung',
      'Vorteile für Konsument:innen',
      'Herzstück',
      'Open-Air-Kino',
      'hasHeadingHtml',
      'introSection',
    ])
    const src = pageSource('solawi')
    expect(src).toContain('CmsVisualEditorPage')
    // metadata stays exactly as before the conversion
    expect(src).toContain("title: 'Was ist Solidarische Landwirtschaft (SoLaWi)? | biocò'")
    expect(src).toContain('keywords:')
    expect(src).toContain('openGraph:')
  })
})

// ---------------------------------------------------------------------------
// /gemuese
// ---------------------------------------------------------------------------
describe('/gemuese parity (seed: gemuese)', () => {
  const sections = seedToSections(loadSeed('gemuese'))

  it('renders one data-owned h1 and the seeded h2 sections in order', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)).toEqual(['Unser Gemüse'])
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toEqual(['Saisonkalender', 'Demeter-Qualität', 'Möchtest du uns kennenlernen?'])
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toEqual(['Warum Demeter?'])
  })

  it('renders the real Saisonkalender component below its seeded title and intro', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(container.textContent).toContain('Wann ist welches Gemüse verfügbar? Entdecke unsere saisonale Vielfalt.')
    expect(container.querySelector('.saisonkalender')).toBeTruthy()
  })

  it('renders the four Demeter accordion items as details/summary in order', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const summaries = Array.from(container.querySelectorAll('.demeter-accordion details summary')).map(
      (el) => el.textContent
    )
    expect(summaries).toEqual([
      'Biologisch-dynamische Landwirtschaft',
      'Kein Einsatz von synthetischen Mitteln',
      'Kreislaufwirtschaft',
      'Biodiversität',
    ])
    const text = container.textContent || ''
    expect(text).toContain('betrachtet den Hof als lebendigen Organismus')
    expect(text).toContain('verzichten vollständig auf synthetische Dünger')
    expect(text).toContain('geschlossene Kreislaufwirtschaft')
    expect(text).toContain('Hecken, Blumenstreifen und vielfältige Fruchtfolgen')
  })

  it('accordion items stack like the former shared wrapper (no extra vertical margins, full width)', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const wrappers = Array.from(
      container.querySelectorAll('section.demeter-accordion')
    ) as HTMLElement[]
    expect(wrappers).toHaveLength(4)
    for (const wrapper of wrappers) {
      // Today all four <details> share one .demeter-accordion div: no per-item
      // vertical margin (spacing comes from `details { margin-bottom }`), and
      // the group spans the full content-container width.
      expect(wrapper.style.margin).toBe('0px')
      expect(wrapper.style.maxWidth).toBe('')
    }
  })

  it('keeps the Demeter copy, /solawi link and structured external CTA', () => {
    const { container, getByRole } = render(<SectionRenderer sections={sections} />)
    expect(container.textContent).toContain('höchste Qualitätsstufe im biologischen Landbau')
    expect(container.querySelector('a[href="/solawi"]')).toBeTruthy()
    getByRole('button', { name: 'Mehr über Demeter erfahren →' })
    const demeter = sections.find((s) => s.id === 'demeter-link')
    expect(demeter?.buttons).toEqual([
      { text: 'Mehr über Demeter erfahren →', href: 'https://www.demeter.ch', variant: 'secondary' },
    ])
  })

  it('renders the kennenlernen CTAs with hrefs preserved in the seed', () => {
    const { getByRole } = render(<SectionRenderer sections={sections} />)
    getByRole('button', { name: 'Nimm Kontakt auf' })
    getByRole('button', { name: 'Zu uns finden' })
    const cta = sections.find((s) => s.id === 'kennenlernen-cta')
    expect(cta?.buttons?.map((b) => b.href)).toEqual(['/kontakt', '/standorte-depots'])
  })

  it('page source is a thin CMS page keeping generateMetadata, without German body copy', () => {
    expectThinCmsPage(
      'gemuese',
      'gemuese',
      [
        'demeter-accordion',
        'Biologisch-dynamische Landwirtschaft',
        'Warum Demeter?',
        'gallery-grid',
        'Keine Bilder im CMS gefunden',
        'hasHeadingHtml',
        "from '@/components/Saisonkalender'",
        'anbauenSection',
      ],
      'getPageSectionsWithSeo'
    )
    const src = pageSource('gemuese')
    expect(src).toContain('CmsVisualEditorPage')
    // CMS-first metadata stays exactly as before the conversion
    expect(src).toContain('FALLBACK_METADATA')
    expect(src).toContain('export async function generateMetadata()')
    expect(src).toContain("title: 'Saisonales Demeter Gemüse | Welche Gemüse haben gerade Saison | biocò'")
  })
})

// ---------------------------------------------------------------------------
// /mitmachen
// ---------------------------------------------------------------------------
describe('/mitmachen parity (seed: mitmachen)', () => {
  const seed = loadSeed('mitmachen')
  const sections = seedToSections(seed)

  it('renders exactly one h1 and the h2 sections in order (incl. real Schnuppertage)', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Mitmachen bei biocò'])
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toEqual([
      'Was es braucht, damit wir gesundes Gemüse haben',
      'Gruppen & Gemeinschaft',
      'Schnuppertage',
      'Familien & Kinder auf dem Geisshof',
      'Möchtest du uns kennenlernen?',
    ])
  })

  it('renders all h3 subsections in order (Mitarbeit, CMS group cards, Schnuppern, Familien)', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toEqual([
      'Mitarbeit bei biocò',
      'Tätigkeitsbereiche',
      'Planung',
      'Feldgruppe',
      'Elki-Gruppe',
      'Komm schnuppern: So geht solidarischer Gemüseanbau.',
      'Kinder sind willkommen',
    ])
  })

  it('keeps the Mitarbeit lists (Tätigkeitsbereiche and Planung) complete', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const text = container.textContent || ''
    expect(text).toContain('Feld/Anbau: Säen, Pflanzen, Jäten, Ernten, Unkraut bekämpfen')
    expect(text).toContain('Events/Organisation: Schnuppertage, Veranstaltungen, Gemeinschaftsanlässe')
    expect(text).toContain('Deine bevorzugten Tage angeben (Mo-Sa)')
    expect(text).toContain('Arbeitseinsätze planen und buchen')
    expect(container.querySelector('a[href="/solawi"]')).toBeTruthy()
  })

  it('the gruppen section places the group_cards component between intro and outro paragraphs', () => {
    const gruppen = sections.find((s) => s.id === 'gruppen')
    expect(gruppen?.component).toBe('group_cards')
    expect(gruppen?.title).toBe('Gruppen & Gemeinschaft')
    expect(gruppen?.text).toContain('Arbeitsgruppen und Gemeinschaftsaktivitäten')
    const ids = sections.map((s) => s.id)
    expect(ids.indexOf('gruppen')).toBeLessThan(ids.indexOf('gruppen-outro'))

    const { container } = render(<SectionRenderer sections={sections} />)
    // group cards come live from the CMS groups endpoint (mocked hook here)
    expect(container.textContent).toContain('Gemeinsam auf dem Feld')
    expect(container.textContent).toContain('Für Familien')
    expect(container.textContent).toContain(
      'Diese Gruppen ermöglichen es, sich nach eigenen Interessen und Fähigkeiten einzubringen'
    )
  })

  it('group_cards is a registered renderable component', () => {
    const resolved = resolveComponentRegistryEntry('group_cards')
    expect(resolved?.entry.kind).toBe('renderable')
  })

  it('renders the Familien split section text and the kennenlernen CTAs', () => {
    const { container, getByRole } = render(<SectionRenderer sections={sections} />)
    const text = container.textContent || ''
    expect(text).toContain('Familien und Kinder sind sehr regelmässige Helfer auf dem Geisshof')
    expect(text).toContain('Die Elki-Gruppe organisiert spezielle Aktivitäten für Familien')
    const familien = seed.sections.find((s) => s.section_id === 'familien')
    expect(familien?.section_layout).toBe('split_media_text')
    expect(familien?.image_alt).toBe('Kinder helfen gemeinsam auf dem Geisshof bei biocò')

    getByRole('button', { name: 'Nimm Kontakt auf' })
    getByRole('button', { name: 'Zu uns finden' })
    const cta = sections.find((s) => s.id === 'kennenlernen-cta')
    expect(cta?.buttons?.map((b) => b.href)).toEqual(['/kontakt', '/standorte-depots'])
  })

  it('page source is a thin CMS page without German body copy', () => {
    expectThinCmsPage('mitmachen', 'mitmachen', [
      'Tätigkeitsbereiche',
      'getGroupCards',
      'SchnuppertageSection',
      'familien-two-col',
      'Kinder sind willkommen',
      'hasHeadingHtml',
    ])
    const src = pageSource('mitmachen')
    expect(src).toContain('CmsVisualEditorPage')
    // metadata stays exactly as before the conversion
    expect(src).toContain("title: 'Mitmachen bei solidarischer Landwirtschaft | biocò Baden'")
    expect(src).toContain('keywords:')
    expect(src).toContain('openGraph:')
  })
})

// ---------------------------------------------------------------------------
// /standorte-depots
// ---------------------------------------------------------------------------
describe('/standorte-depots parity (seed: standorte-depots)', () => {
  const sections = seedToSections(loadSeed('standorte-depots'))

  it('renders exactly one h1, the h2 sections in order and the Anreise h4', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Unsere Standorte & Depots'])
    const h2s = Array.from(container.querySelectorAll('h2')).map((h) => h.textContent)
    expect(h2s).toEqual([
      'Anfahrt zum Geisshof',
      'Depot-Standorte für Gemüseabholung',
      'Möchtest du uns kennenlernen?',
    ])
    const h4s = Array.from(container.querySelectorAll('h4')).map((h) => h.textContent)
    expect(h4s).toEqual(['Anreise & Parken'])
  })

  it('renders both maps as the real registered components in order', () => {
    const { container, getByTestId } = render(<SectionRenderer sections={sections} />)
    getByTestId('geisshof-map')
    getByTestId('depot-map')
    const html = container.innerHTML
    expect(html.indexOf('geisshof-map')).toBeLessThan(html.indexOf('depot-map'))
  })

  it('keeps the four depot detail headings and copy byte-exact', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toEqual(['Depot Baden', 'Depot Brugg', 'Depot Gebenstorf', 'Depot Wettingen'])
    const text = container.textContent || ''
    expect(text).toContain('Gemüse abholen Baden:')
    expect(text).toContain('Gemüse abholen Brugg:')
    // plain hyphen, not an en dash (JSX source artifact preserved)
    expect(text).toContain('euer Gemüse in Gebenstorf abholen - ideal')
    expect(text).toContain('Abholzeiten: Dienstag und Freitag, ab 16:00 Uhr')
    expect(text).toContain('Halte den Wendeplatz zwingend frei')
    expect(text).toContain('Danke für deine Rücksichtnahme!')
  })

  it('keeps the depots anchor on the rendered section', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(container.querySelector('#depots')).toBeTruthy()
  })

  it('renders the kennenlernen CTAs with hrefs preserved in the seed', () => {
    const { getByRole } = render(<SectionRenderer sections={sections} />)
    getByRole('button', { name: 'Nimm Kontakt auf' })
    getByRole('button', { name: 'Zu uns finden' })
    const cta = sections.find((s) => s.id === 'kennenlernen-cta')
    expect(cta?.buttons?.map((b) => b.href)).toEqual(['/kontakt', '/standorte-depots'])
  })

  it('page source is a thin CMS page without German body copy', () => {
    expectThinCmsPage('standorte-depots', 'standorte-depots', [
      'Anreise & Parken',
      'Depot Wettingen',
      'GeisshofMap',
      'DepotMap',
      'Abholzeiten',
      'Wendeplatz',
    ])
    const src = pageSource('standorte-depots')
    expect(src).toContain('CmsVisualEditorPage')
    expect(src).toContain('export const revalidate = 60')
    // metadata stays exactly as before the conversion
    expect(src).toContain("title: 'Standorte & Depots Baden-Brugg | Gemüse abholen | biocò'")
    expect(src).toContain('keywords:')
    expect(src).toContain('openGraph:')
  })
})

// ---------------------------------------------------------------------------
// /kontakt
// ---------------------------------------------------------------------------
describe('/kontakt parity (seed: kontakt)', () => {
  const sections = seedToSections(loadSeed('kontakt'))

  it('renders exactly one h1, the intro paragraph and the three h4 boxes in order', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Kontakt'])
    expect(container.textContent).toContain(
      'Wir melden uns in der Regel innerhalb von 2-3 Werktagen bei dir zurück.'
    )
    const h4s = Array.from(container.querySelectorAll('h4')).map((h) => h.textContent)
    expect(h4s).toEqual(['Du bist bereits Mitglied?', 'Möchtest du Mitglied werden?', 'Allgemeine Anfragen'])
  })

  it('keeps the intranet and bioco-werden CTAs as structured buttons', () => {
    const { getByRole } = render(<SectionRenderer sections={sections} />)
    getByRole('link', { name: 'Zum Intranet →' })
    getByRole('button', { name: 'biocò werden →' })
    expect(sections.find((s) => s.id === 'intranet-box')?.buttons?.[0].href).toBe('/intranet')
    expect(sections.find((s) => s.id === 'intranet-box')?.config?.buttonNavigation).toBe('document')
    expect(sections.find((s) => s.id === 'mitglied-werden-box')?.buttons?.[0].href).toBe('/bioco-werden')
  })

  it('keeps the contact form anchor on the rendered section', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(container.querySelector('#kontakt-formular-intro')).toBeTruthy()
  })

  it('renders the real contact form', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const submit = container.querySelector('input[type="submit"]') as HTMLInputElement
    expect(submit?.value).toBe('Absenden')
  })

  it('page source is a thin CMS page without hardcoded copy and still without metadata export', () => {
    expectThinCmsPage('kontakt', 'kontakt', [
      'Zum Intranet',
      'ContactForm',
      'Allgemeine Anfragen',
      'kontakt-formular',
      'bg-secondary',
    ])
    const src = pageSource('kontakt')
    expect(src).toContain('CmsVisualEditorPage')
    expect(src).toContain('export const revalidate = 60')
    // the page never exported metadata (inherits the root layout) — keep it that way
    expect(src).not.toContain('metadata')
  })
})

// ---------------------------------------------------------------------------
// /bioco-werden
// ---------------------------------------------------------------------------
describe('/bioco-werden parity (seed: bioco-werden)', () => {
  const sections = seedToSections(loadSeed('bioco-werden'))

  it('renders exactly one h1, the intro paragraph and the real pricing calculator', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['biocò werden'])
    expect(container.textContent).toContain(
      'Deine Auswahl wird automatisch ins Anmeldeformular übernommen.'
    )
    // pricing_calculator renders the actual PricingCalculator (abo tiers)
    expect(container.querySelector('.pricing-calculator')).toBeTruthy()
    expect(container.textContent).toContain('CHF 750.-')
    expect(container.textContent).toContain("CHF 2'350.-")
  })

  it('page source is a thin CMS page without hardcoded heading or calculator import', () => {
    expectThinCmsPage('bioco-werden', 'bioco-werden', [
      'PricingCalculator',
      // intro copy (metadata legitimately keeps 'Wähle dein Gemüseabo')
      'Deine Auswahl wird automatisch',
      '<h1>',
    ])
    const src = pageSource('bioco-werden')
    expect(src).toContain('CmsVisualEditorPage')
    expect(src).toContain('export const revalidate = 60')
    // metadata stays exactly as before the conversion
    expect(src).toContain("title: 'biocò werden | Mitglied werden | biocò Baden'")
    expect(src).toContain('keywords:')
    expect(src).toContain('openGraph:')
  })
})

// ---------------------------------------------------------------------------
// /anmeldung
// ---------------------------------------------------------------------------
describe('/anmeldung parity (seed: anmeldung)', () => {
  const sections = seedToSections(loadSeed('anmeldung'))

  it('renders exactly one h1 and the real membership form', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Anmeldung'])

    // membership_form component renders the actual MembershipForm wizard
    expect(container.textContent).toContain('Schritt 1 von 6')
    expect(container.textContent).toContain('Lies das und bestätige bevor du weiterklickst')
    const form = sections.find((s) => s.id === 'membership-form')
    expect(form?.component).toBe('membership_form')
  })

  it('page source keeps MinimalHeader chrome without Footer, content comes from CMS', () => {
    expectThinCmsPage('anmeldung', 'anmeldung', [
      'MembershipForm',
      '<h1>',
      'Footer',
    ])
    const src = pageSource('anmeldung')
    // code-owned page chrome stays: MinimalHeader + bento card (no Footer rendered)
    expect(src).toContain('MinimalHeader')
    expect(src).toContain('bento-card')
    expect(src).toContain('plant-pattern')
    expect(src).toContain('VisualEditorWrapper')
    expect(src).toContain('export const revalidate = 60')
    // the page never exported metadata (inherits the root layout) — keep it that way
    expect(src).not.toContain('metadata')
  })
})
