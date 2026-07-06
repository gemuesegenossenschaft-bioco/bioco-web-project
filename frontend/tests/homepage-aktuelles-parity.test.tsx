import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render } from '@testing-library/react'
import { readFileSync } from 'node:fs'
import path from 'node:path'
import { loadSeed, seedToSections } from './helpers/seedToSections'
import { HomeClient } from '@/components/HomeClient'
import { AktuellesClient } from '@/components/AktuellesClient'
import type { AktuellesItem } from '@/components/AktuellesData'
import type { HeroContent } from '@/lib/processwire-types'

// F.6 — homepage and /aktuelles are fully CMS-driven: the seeded sections
// (cms/content-seed/home.json + aktuelles.json) are the ONLY content source.
// With an empty sections response the formerly hardcoded German fallbacks
// must not render; code-owned feed chrome (loading/empty states, feed
// headings, 'Rückblick ansehen →') stays.

let mockSearch = ''
let mockFeed: { upcoming: AktuellesItem[]; past: AktuellesItem[]; isLoading: boolean } = {
  upcoming: [],
  past: [],
  isLoading: false,
}

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
  usePathname: () => '/',
  useSearchParams: () => ({
    get: (key: string) => new URLSearchParams(mockSearch).get(key),
  }),
}))
vi.mock('@/components/UtilityNavigation', () => ({ UtilityNavigation: () => null }))
vi.mock('@/components/SecondaryNavigation', () => ({ PrimaryNavigation: () => null }))
vi.mock('@/components/MobileMenu', () => ({ MobileMenu: () => null }))
vi.mock('@/components/Header', () => ({ Header: () => null }))
vi.mock('@/components/Footer', () => ({ Footer: () => null }))
vi.mock('@/components/ItemDetailModal', () => ({ ItemDetailModal: () => null }))
vi.mock('@/components/AktuellesItem', () => ({
  AktuellesItemComponent: ({ item }: { item: AktuellesItem }) => (
    <div data-testid="feed-item">{item.title}</div>
  ),
}))
vi.mock('@/components/ScrollToTopLink', () => ({
  ScrollToTopLink: ({ children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => (
    <a {...props}>{children}</a>
  ),
}))
vi.mock('@/hooks/useEventsFeed', () => ({
  useEventsFeed: () => mockFeed,
}))

beforeEach(() => {
  mockSearch = ''
  mockFeed = { upcoming: [], past: [], isLoading: false }
})

const emptyHero: HeroContent = { headline: '', subtitle: '', image: null, imageAlt: '' }

function componentSource(file: string): string {
  return readFileSync(path.resolve(__dirname, '..', 'components', file), 'utf8')
}

function pageSource(routeDir: string): string {
  return readFileSync(path.resolve(__dirname, '..', 'app', routeDir, 'page.tsx'), 'utf8')
}

// ---------------------------------------------------------------------------
// Homepage (seed: home)
// ---------------------------------------------------------------------------
describe('homepage parity (seed: home)', () => {
  const seededSections = seedToSections(loadSeed('home'))

  it('renders the exact seeded willkommen/gemeinsam/kennenlernen content', () => {
    const { container, getByRole } = render(
      <HomeClient hero={emptyHero} sections={seededSections} aktuellesItems={[]} />
    )

    // Headings (byte-exact seed titles)
    getByRole('heading', { name: 'Willkommen bei biocò' })
    getByRole('heading', { name: 'Gemeinsam, solidarisch, frisch' })
    getByRole('heading', { name: 'Möchtest du uns kennenlernen?' })

    // Body copy from seeded section_text
    const text = container.textContent || ''
    expect(text).toContain('teilen wir nicht nur die Ernte, sondern auch die Verantwortung')
    expect(text).toContain('abgeholt werden kann')
    expect(text).toContain('Seit 2014 bewirtschaften wir den')
    expect(text).toContain('ob auf dem Feld, in der Logistik oder bei der Organisation')
    expect(text).toContain('Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können')

    // Inline links preserved from seed HTML
    expect(container.querySelector('a[href="/solawi"]')).toBeTruthy()
    expect(container.querySelector('a[href="/standorte-depots"]')).toBeTruthy()
    expect(container.querySelector('a[href="/wir"]')).toBeTruthy()
    expect(container.querySelector('a[href="/gemuese"]')).toBeTruthy()

    // Seeded CTAs
    getByRole('button', { name: 'Lerne uns kennen' })
    getByRole('button', { name: 'Was gerade wächst' })
    getByRole('button', { name: 'Nimm Kontakt auf' })
    getByRole('button', { name: 'Zu uns finden' })
  })

  it('renders NOTHING for willkommen/gemeinsam/kennenlernen when the sections response is empty', () => {
    const { container, queryByRole } = render(
      <HomeClient hero={emptyHero} sections={[]} aktuellesItems={[]} />
    )

    const text = container.textContent || ''
    for (const signature of [
      'Willkommen bei biocò',
      'teilen wir nicht nur die Ernte',
      'Gemeinsam, solidarisch, frisch',
      'Seit 2014 bewirtschaften wir den',
      'Möchtest du uns kennenlernen?',
      'Es können viele Fragen auftauchen',
    ]) {
      expect(text, `removed fallback "${signature}" must not render`).not.toContain(signature)
    }
    for (const cta of ['Lerne uns kennen', 'Was gerade wächst', 'Nimm Kontakt auf', 'Zu uns finden']) {
      expect(queryByRole('button', { name: cta }), `removed fallback CTA "${cta}"`).toBeNull()
    }
  })

  it('keeps the code-owned feed chrome with an empty sections response', () => {
    const { container, getByRole } = render(
      <HomeClient hero={emptyHero} sections={[]} aktuellesItems={[]} />
    )

    getByRole('heading', { name: 'Beiträge' })
    getByRole('heading', { name: 'Kommende Events' })
    getByRole('heading', { name: 'Schnuppertage' })
    const text = container.textContent || ''
    expect(text).toContain('Alle Beiträge ansehen')
    expect(text).toContain('Alle Events ansehen')
    expect(text).toContain('Aktuell sind keine allgemeinen Events geplant.')
    expect(text).toContain('Aktuell sind keine Schnuppertage geplant.')
  })

  it('still renders live aktuelles items and events with an empty sections response', () => {
    mockFeed = {
      upcoming: [
        { id: 1, date: '01.09.2026', title: 'Hoffest', description: '', type: 'event', eventType: 'general' },
      ],
      past: [],
      isLoading: false,
    }
    const { getAllByTestId } = render(
      <HomeClient
        hero={emptyHero}
        sections={[]}
        aktuellesItems={[{ id: 7, date: '01.08.2026', title: 'Neues aus dem Feld', description: '', type: 'aktuelles' }]}
      />
    )
    const titles = getAllByTestId('feed-item').map((el) => el.textContent)
    expect(titles).toContain('Neues aus dem Feld')
    expect(titles).toContain('Hoffest')
  })

  it('emits stable VE section markers for the seeded homepage sections', () => {
    mockSearch = '_visual=1'
    const { container } = render(
      <HomeClient hero={emptyHero} sections={seededSections} aktuellesItems={[]} />
    )
    expect(container.querySelector('[data-ve-section-id="willkommen"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-section-id="gemeinsam"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-section-id="kennenlernen"]')).toBeTruthy()
    expect(container.querySelector('[data-section-id="__hero__"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-section-id="willkommen"][data-ve-field="text"]')).toBeTruthy()
  })

  it('HomeClient source no longer contains the hardcoded German fallbacks', () => {
    const src = componentSource('HomeClient.tsx')
    for (const signature of [
      'Willkommen bei biocò',
      'teilen wir nicht nur die Ernte',
      'Seit 2014 bewirtschaften wir den',
      'Möchtest du uns kennenlernen?',
      'Es können viele Fragen auftauchen',
      'Lerne uns kennen',
      'Was gerade wächst',
      'Nimm Kontakt auf',
      'Zu uns finden',
      'Solidarische Landwirtschaft auf dem Feld',
      'Gemeinschaft bei solidarischer Landwirtschaft',
      'Frisch geerntetes Demeter-Gemüse vom Geisshof',
    ]) {
      expect(src, `HomeClient.tsx must not hardcode "${signature}"`).not.toContain(signature)
    }
  })

  it('app/page.tsx keeps its metadata exports and drops the aktuelles fallback shim', () => {
    const src = readFileSync(path.resolve(__dirname, '..', 'app', 'page.tsx'), 'utf8')
    expect(src).not.toContain('getAktuellesItems')
    expect(src).toContain('export async function generateMetadata')
    expect(src).toContain('biocò | Bio-Gemüse aus der Region Baden-Brugg')
    expect(src).toContain('export const revalidate = 60')
  })
})

// ---------------------------------------------------------------------------
// /aktuelles (seed: aktuelles)
// ---------------------------------------------------------------------------
describe('/aktuelles parity (seed: aktuelles)', () => {
  const seededSections = seedToSections(loadSeed('aktuelles'))

  it('renders the seeded intro h1 and kennenlernen CTA section byte-exact', () => {
    const { container, getByRole } = render(
      <AktuellesClient sections={seededSections} aktuellesItems={[]} />
    )

    // Intro: h1 comes from the seeded section_text '<h1>Aktuelles</h1>'
    const h1s = Array.from(container.querySelectorAll('h1')).map((h) => h.textContent)
    expect(h1s).toEqual(['Aktuelles'])

    // Kennenlernen CTA section from the seed
    getByRole('heading', { name: 'Möchtest du uns kennenlernen?' })
    expect(container.textContent).toContain(
      'Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können'
    )
    getByRole('button', { name: 'Nimm Kontakt auf' })
    getByRole('button', { name: 'Zu uns finden' })
  })

  it('renders NOTHING for intro and kennenlernen CTA when the sections response is empty', () => {
    const { container, queryByRole } = render(
      <AktuellesClient sections={[]} aktuellesItems={[]} />
    )

    expect(container.querySelector('h1')).toBeNull()
    const text = container.textContent || ''
    expect(text).not.toContain('Möchtest du uns kennenlernen?')
    expect(text).not.toContain('Es können viele Fragen auftauchen')
    expect(queryByRole('button', { name: 'Nimm Kontakt auf' })).toBeNull()
    expect(queryByRole('button', { name: 'Zu uns finden' })).toBeNull()
  })

  it('keeps the code-owned feed chrome regardless of CMS sections', () => {
    const { getByRole, container } = render(
      <AktuellesClient sections={[]} aktuellesItems={[]} />
    )
    getByRole('heading', { name: 'Beiträge' })
    getByRole('heading', { name: 'Events' })
    getByRole('heading', { name: 'Kommende Events' })
    getByRole('heading', { name: 'Schnuppertage' })
    const text = container.textContent || ''
    expect(text).toContain('Keine Beiträge verfügbar.')
    expect(text).toContain('Aktuell sind keine allgemeinen Events geplant.')
    expect(text).toContain('Aktuell sind keine Schnuppertage geplant.')
  })

  it('keeps the loading state chrome while the events feed loads', () => {
    mockFeed = { upcoming: [], past: [], isLoading: true }
    const { container } = render(<AktuellesClient sections={[]} aktuellesItems={[]} />)
    expect(container.textContent).toContain('Events werden geladen…')
  })

  it('keeps the past-events card chrome including the Rückblick CTA', () => {
    mockFeed = {
      upcoming: [],
      past: [
        { id: 9, date: '01.05.2026', title: 'Fruehlingsfest', description: '', type: 'event', status: 'past' },
      ],
      isLoading: false,
    }
    const { container, getByRole } = render(<AktuellesClient sections={[]} aktuellesItems={[]} />)
    getByRole('heading', { name: 'Vergangene Events' })
    expect(container.textContent).toContain('Rückblick ansehen →')
  })

  it('AktuellesClient source keeps the chrome strings but drops the content fallbacks', () => {
    const src = componentSource('AktuellesClient.tsx')

    // Removed content fallbacks
    for (const signature of [
      'Möchtest du uns kennenlernen?',
      'Es können viele Fragen auftauchen',
      'Nimm Kontakt auf',
      'Zu uns finden',
    ]) {
      expect(src, `AktuellesClient.tsx must not hardcode "${signature}"`).not.toContain(signature)
    }
    expect(src, "AktuellesClient.tsx must not fall back to a hardcoded 'Aktuelles' h1").not.toMatch(
      /\|\|\s*'Aktuelles'/
    )

    // Code-owned chrome stays
    for (const chrome of [
      'Keine Beiträge verfügbar.',
      'Events werden geladen…',
      'Aktuell sind keine allgemeinen Events geplant.',
      'Aktuell sind keine Schnuppertage geplant.',
      'Rückblick ansehen →',
      'useEventsFeed(',
    ]) {
      expect(src, `AktuellesClient.tsx must keep chrome "${chrome}"`).toContain(chrome)
    }
  })

  it('app/aktuelles/page.tsx keeps metadata and sections fetch, drops the fallback shim', () => {
    const src = pageSource('aktuelles')
    expect(src).toContain("getPageSections('aktuelles')")
    expect(src).not.toContain('getAktuellesItems')
    expect(src).toContain('Aktuelles & Events | biocò Gemüsegenossenschaft Baden')
    expect(src).toContain('export const revalidate = 60')
  })

  it('AktuellesData source has no content fallback shims and keeps no-store live fetching', () => {
    const src = componentSource('AktuellesData.tsx')
    expect(src).not.toContain('getAktuellesItems')
    expect(src).not.toContain('getAllAktuellesItems')
    expect(src).toContain("cache: 'no-store'")
  })
})
