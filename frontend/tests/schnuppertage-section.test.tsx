import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render } from '@testing-library/react'
import { readFileSync } from 'node:fs'
import path from 'node:path'
import { SchnuppertageSection } from '@/components/SchnuppertageSection'
import { loadSeed, seedToSections } from './helpers/seedToSections'
import type { ContentSection } from '@/lib/processwire-types'

// The /mitmachen Schnuppertage editorial copy used to be hardcoded inside
// SchnuppertageSection.tsx, which broke this branch's invariant ("every content
// field CMS-editable, no hardcoded content"). These tests pin the copy to the
// CMS section (title/text/config); only the live date list + signup modal stay
// code-owned.

vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => <img data-testid="next-image" {...props} />,
}))
vi.mock('@/components/forms/CaptchaField', () => ({
  CaptchaField: () => <div data-testid="captcha-field" />,
}))
// The live Schnuppertage date list is functional (code-owned): keep it empty so
// these tests only exercise the CMS-driven editorial copy.
vi.mock('@/hooks/useEventsFeed', () => ({
  useEventsFeed: () => ({ upcoming: [], past: [], isLoading: false }),
}))

// Byte-exact copy as it renders in today's SchnuppertageSection.tsx (JSX
// whitespace collapsed to single spaces). This is the content-freeze contract:
// the seed must reproduce these strings exactly.
const HEADING = 'Schnuppertage'
const SUBHEADING = 'Komm schnuppern: So geht solidarischer Gemüseanbau.'
const INTRO =
  'Möchtest du dein Gemüse in Gemeinschaft anbauen und erfahren, wie es sich anfühlt, Teil einer Solawi zu sein? Dann komm an einen unserer Schnuppertage vorbei. Geniesse einen Nachmittag auf dem Geisshof in Gebenstorf AG, auf dem Feld umgeben von Natur und Tieren, Wildpflanzen, Bäumen, Beerensträuchern und Kräuterspirale.'
const LIST_LABEL = 'Was dich erwartet:'
const LIST_ITEMS = [
  'Gemeinschaft auf dem Feld, umgeben von Natur',
  'Unser Hof liegt auf einem Hügel über Gebenstorf AG',
  'Deine Hilfe auf dem Feld',
  'Danke: du bekommst eine Tasche frisch geerntetes Demeter-Gemüse',
  'Kleines zVieri von uns spendiert',
  'Hof und Demeteranbau kennenlernen',
  'Möglichkeit anschliessend auf dem Gemeinschaftsplatz zu bräteln',
]
const CLOSING =
  'Uns ist ein achtsamer Umgang mit der Natur wichtig. Wir lassen viel Platz für Wildpflanzen, haben eine Kräuterspirale, eine Naschecke mit Beeren, Sandkasten und Enten auf dem Hof. Auf dem Gemeinschaftsplatz hat es einen Sandkasten für Kinder und eine Feuerstelle. Nach dem Schnuppernachmittag darfst du gerne noch bleiben und etwas grillieren.'

function seededSchnuppertageSection(): ContentSection {
  const sections = seedToSections(loadSeed('mitmachen'))
  const section = sections.find((s) => s.id === 'schnuppertage')
  if (!section) throw new Error("mitmachen seed is missing the 'schnuppertage' section")
  return section
}

describe('SchnuppertageSection (CMS-driven editorial copy)', () => {
  it('renders heading, subheading, intro, list items and closing from the passed section (nothing hardcoded)', () => {
    const section: ContentSection = {
      id: 'schnup-x',
      title: 'PROBE HEADING',
      text: '<h3>PROBE SUBHEADING</h3><p>PROBE INTRO</p>',
      layout: 'component',
      component: 'schnuppertage',
      config: {
        list_label: 'PROBE LABEL',
        item1: 'PROBE ITEM ONE',
        item2: 'PROBE ITEM TWO',
        closing: 'PROBE CLOSING',
      },
    }

    const { container } = render(<SchnuppertageSection section={section} />)
    const text = container.textContent || ''

    // Data from the section renders...
    expect(container.querySelector('h2')?.textContent).toBe('PROBE HEADING')
    expect(container.querySelector('h3')?.textContent).toBe('PROBE SUBHEADING')
    expect(text).toContain('PROBE INTRO')
    expect(text).toContain('PROBE LABEL')
    const items = Array.from(container.querySelectorAll('li')).map((li) => li.textContent)
    expect(items).toEqual(['PROBE ITEM ONE', 'PROBE ITEM TWO'])
    expect(text).toContain('PROBE CLOSING')

    // ...and today's frozen copy must NOT appear when a different section is
    // passed (proving the strings come from data, not from the component).
    expect(text).not.toContain(SUBHEADING)
    expect(text).not.toContain(LIST_ITEMS[0])
    expect(text).not.toContain(CLOSING)
  })

  it('reproduces the exact frozen German copy from the mitmachen seed', () => {
    const section = seededSchnuppertageSection()
    const { container } = render(<SchnuppertageSection section={section} />)
    const text = container.textContent || ''

    expect(container.querySelector('h2')?.textContent).toBe(HEADING)
    expect(container.querySelector('h3')?.textContent).toBe(SUBHEADING)
    expect(text).toContain(INTRO)
    expect(text).toContain(LIST_LABEL)

    const items = Array.from(container.querySelectorAll('li')).map((li) => li.textContent)
    expect(items).toEqual(LIST_ITEMS)

    expect(text).toContain(CLOSING)
  })

  it('keeps the compact date-list header code-owned (functional widget label)', () => {
    const section = seededSchnuppertageSection()
    const { container } = render(<SchnuppertageSection section={section} />)
    // "Nächste Termine" labels the live useEventsFeed list, which stays code-owned.
    expect(container.querySelector('h4')?.textContent).toBe('Nächste Termine')
  })

  it('source is copy-pure: the editorial strings are not string literals in the component', () => {
    const src = readFileSync(
      path.resolve(__dirname, '..', 'components', 'SchnuppertageSection.tsx'),
      'utf8',
    )
    // The seven "Was dich erwartet" list items must not live in the source.
    for (const item of LIST_ITEMS) {
      expect(src, `SchnuppertageSection.tsx must not hardcode list item "${item}"`).not.toContain(item)
    }
    // Neither the subheading nor the two prose paragraphs may be hardcoded.
    expect(src).not.toContain(SUBHEADING)
    expect(src).not.toContain(INTRO)
    expect(src).not.toContain(CLOSING)
    expect(src).not.toContain(LIST_LABEL)
  })
})
