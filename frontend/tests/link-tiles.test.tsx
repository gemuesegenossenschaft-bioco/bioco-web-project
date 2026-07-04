import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render } from '@testing-library/react'
import { SectionRenderer } from '@/components/sections/SectionRenderer'
import { componentRendererKeys, renderRegisteredComponent } from '@/lib/componentRenderers'
import { resolveComponentRegistryEntry } from '@/lib/componentRegistry'
import type { ContentSection } from '@/lib/processwire-types'

// Mock next/image
vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => <img data-testid="next-image" {...props} />,
}))

// Mock all component imports
vi.mock('@/components/forms/ContactForm', () => ({ ContactForm: () => null }))
vi.mock('@/components/forms/MembershipForm', () => ({ MembershipForm: () => null }))
vi.mock('@/components/forms/SubscribeForm', () => ({ SubscribeForm: () => null }))
vi.mock('@/components/forms/VisitDayForm', () => ({ VisitDayForm: () => null }))
vi.mock('@/components/forms/WaitingListForm', () => ({ WaitingListForm: () => null }))
vi.mock('@/components/PricingCalculator', () => ({ PricingCalculator: () => null }))
vi.mock('@/components/EventsSection', () => ({ EventsSection: () => null }))
vi.mock('@/components/SchnuppertageSection', () => ({ SchnuppertageSection: () => null }))
vi.mock('@/components/DepotMap', () => ({ DepotMap: () => null }))
vi.mock('@/components/GeisshofMap', () => ({ GeisshofMap: () => null }))
vi.mock('@/components/Saisonkalender', () => ({ Saisonkalender: () => null }))
vi.mock('@/components/Gallery', () => ({ Gallery: () => null }))
vi.mock('@/components/CTA', () => ({ CTA: () => null }))

const tilesSection: ContentSection = {
  id: 'tiles-1',
  title: 'Gateway',
  text: '',
  layout: 'component',
  component: 'link_tiles',
  config: {
    tile1_title: 'Mitglieder-Portal',
    tile1_text: 'Extern',
    tile1_href: 'https://portal.example.ch',
    tile1_icon: '🦆',
    tile2_title: 'Einsatzplanung',
    tile2_text: 'Extern',
    tile2_icon: '🦆',
  },
}

describe('link_tiles registry entry', () => {
  it('is registered with the German label "Portal-Kacheln"', () => {
    const resolved = resolveComponentRegistryEntry('link_tiles')
    expect(resolved).toBeTruthy()
    expect(resolved?.entry.label).toBe('Portal-Kacheln')
    expect(resolved?.entry.kind).toBe('renderable')
    expect(resolved?.entry.cmsFields).toEqual(
      expect.arrayContaining(['section_component', 'section_title', 'section_config']),
    )
  })

  it('has a configSchema with German-labelled text fields per tile', () => {
    const schema = resolveComponentRegistryEntry('link_tiles')?.entry.configSchema || []
    for (let n = 1; n <= 4; n++) {
      const title = schema.find((f) => f.key === `tile${n}_title`)
      const text = schema.find((f) => f.key === `tile${n}_text`)
      const href = schema.find((f) => f.key === `tile${n}_href`)
      const icon = schema.find((f) => f.key === `tile${n}_icon`)
      expect(title?.type, `tile${n}_title`).toBe('text')
      expect(title?.label).toBe(`Kachel ${n} — Titel`)
      expect(text?.type, `tile${n}_text`).toBe('text')
      expect(text?.label).toBe(`Kachel ${n} — Text`)
      expect(href?.type, `tile${n}_href`).toBe('text')
      expect(href?.label).toBe(`Kachel ${n} — Link`)
      expect(icon?.type, `tile${n}_icon`).toBe('text')
      expect(icon?.label).toBe(`Kachel ${n} — Symbol (Emoji)`)
    }
  })

  it('has a renderer and owns its layout', () => {
    expect(componentRendererKeys).toContain('link_tiles')
    const result = renderRegisteredComponent(tilesSection)
    expect(result.node).toBeTruthy()
    expect(result.ownsLayout).toBe(true)
  })
})

describe('link_tiles rendering (portal gateway markup from /kundenportal)', () => {
  it('renders portal-gateway grid with portal-tile entries', () => {
    const { container } = render(<SectionRenderer sections={[tilesSection]} />)

    const gateway = container.querySelector('.portal-gateway')
    expect(gateway).toBeTruthy()

    const tiles = container.querySelectorAll('.portal-gateway .portal-tile')
    expect(tiles).toHaveLength(2)

    // tile structure: .portal-icon + h3 + p (same as kundenportal page)
    const first = tiles[0]
    expect(first.querySelector('.portal-icon')?.textContent).toBe('🦆')
    expect(first.querySelector('h3')?.textContent).toBe('Mitglieder-Portal')
    expect(first.querySelector('p')?.textContent).toBe('Extern')
  })

  it('renders a tile with href as an anchor and one without href as non-anchor', () => {
    const { container } = render(<SectionRenderer sections={[tilesSection]} />)
    const tiles = Array.from(container.querySelectorAll('.portal-tile'))

    expect(tiles[0].tagName).toBe('A')
    expect(tiles[0].getAttribute('href')).toBe('https://portal.example.ch')

    expect(tiles[1].tagName).not.toBe('A')
    expect(tiles[1].querySelector('h3')?.textContent).toBe('Einsatzplanung')
  })

  it('renders only tiles that have a title', () => {
    const sparse: ContentSection = {
      ...tilesSection,
      id: 'tiles-sparse',
      config: {
        tile1_title: 'Einzige Kachel',
        tile2_title: '',
        tile2_text: 'Text ohne Titel wird nicht angezeigt',
        tile3_href: '/nur-link',
      },
    }
    const { container } = render(<SectionRenderer sections={[sparse]} />)
    expect(container.querySelectorAll('.portal-tile')).toHaveLength(1)
    expect(container.querySelector('.portal-tile h3')?.textContent).toBe('Einzige Kachel')
  })

  it('exposes VE markers: inline title and structured component container', () => {
    const { container } = render(<SectionRenderer sections={[tilesSection]} visualEditor={true} />)

    const title = container.querySelector('[data-ve-field="title"]')
    expect(title).toBeTruthy()
    expect(title?.getAttribute('data-ve-inline')).toBe('true')

    const structured = container.querySelector('[data-ve-field="component"]')
    expect(structured).toBeTruthy()
    expect(structured?.getAttribute('data-ve-kind')).toBe('structured')
    expect(structured?.getAttribute('data-ve-section-id')).toBe('tiles-1')
  })
})
