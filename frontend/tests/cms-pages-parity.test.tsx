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
  EventsSection: () => <div data-testid="events-section-standard" />,
}))

function pageSource(routeDir: string): string {
  return readFileSync(path.resolve(__dirname, '..', 'app', routeDir, 'page.tsx'), 'utf8')
}

function expectThinCmsPage(routeDir: string, slug: string, absentSignatures: string[]) {
  const src = pageSource(routeDir)
  expect(src, `app/${routeDir}/page.tsx must fetch CMS sections for '${slug}'`).toContain(
    `getPageSections('${slug}')`
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

  it('renders all headings at their original levels (h3 intro, h2 sections, no h1)', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(container.querySelector('h1')).toBeNull()
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toContain('Impressum')
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

  it('renders all numbered h2 headings, h3 subheadings and no h1', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    expect(container.querySelector('h1')).toBeNull()
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
      'Datenschutzerklärung',
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

  it('keeps the Fragen block with mailto CTA inside the rich text', () => {
    const { container } = render(<SectionRenderer sections={sections} />)
    const h3s = Array.from(container.querySelectorAll('h3')).map((h) => h.textContent)
    expect(h3s).toContain('Fragen?')
    expect(container.textContent).toContain('kannst du uns jederzeit kontaktieren')
    const mailto = container.querySelector('a[href="mailto:info@bioco.ch"]')
    expect(mailto).toBeTruthy()
    expect(mailto?.className).toContain('btn-secondary')
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
    expect(events?.config).toEqual({ variant: 'banner', limit: 3 })
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
    }
    const { queryByTestId, container } = render(<SectionRenderer sections={[standard]} />)
    expect(queryByTestId('events-section-standard')).toBeTruthy()
    expect(container.querySelector('.events-banner')).toBeNull()
  })

  it('events_feed registry entry has German-labelled variant/limit config fields', () => {
    const entry = resolveComponentRegistryEntry('events_feed')?.entry
    expect(entry?.cmsFields).toContain('section_config')
    const schema = entry?.configSchema || []
    const variant = schema.find((f) => f.key === 'variant')
    expect(variant?.type).toBe('select')
    expect(variant?.label).toBe('Darstellung')
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
