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

const accordionSection: ContentSection = {
  id: 'acc-1',
  title: 'Biodiversität',
  text: '<p>Wir fördern die Artenvielfalt durch Hecken und Blumenstreifen.</p>',
  layout: 'component',
  component: 'accordion_item',
}

describe('accordion_item registry entry', () => {
  it('is registered with the German label "Akkordeon-Eintrag"', () => {
    const resolved = resolveComponentRegistryEntry('accordion_item')
    expect(resolved).toBeTruthy()
    expect(resolved?.entry.label).toBe('Akkordeon-Eintrag')
    expect(resolved?.entry.kind).toBe('renderable')
    expect(resolved?.entry.cmsFields).toEqual(
      expect.arrayContaining(['section_component', 'section_title', 'section_text']),
    )
  })

  it('has a renderer and owns its layout', () => {
    expect(componentRendererKeys).toContain('accordion_item')
    const result = renderRegisteredComponent(accordionSection)
    expect(result.node).toBeTruthy()
    expect(result.ownsLayout).toBe(true)
  })
})

describe('accordion_item rendering (Demeter accordion markup)', () => {
  it('renders details/summary inside a .demeter-accordion wrapper', () => {
    const { container } = render(<SectionRenderer sections={[accordionSection]} />)

    const details = container.querySelector('.demeter-accordion details')
    expect(details).toBeTruthy()

    const summary = container.querySelector('.demeter-accordion details summary')
    expect(summary).toBeTruthy()
    expect(summary?.textContent).toBe('Biodiversität')

    // section_text rendered as HTML inside the details element
    const body = details?.querySelector('p')
    expect(body).toBeTruthy()
    expect(body?.textContent).toContain('Wir fördern die Artenvielfalt')
  })

  it('does not render the generic cms-component wrapper (layout-owned)', () => {
    const { container } = render(<SectionRenderer sections={[accordionSection]} />)
    expect(container.querySelector('.cms-component')).toBeNull()
  })

  it('exposes VE markers for title (inline text) and text (inline richtext)', () => {
    const { container } = render(<SectionRenderer sections={[accordionSection]} visualEditor={true} />)

    const summary = container.querySelector('summary')
    expect(summary?.getAttribute('data-ve-field')).toBe('title')
    expect(summary?.getAttribute('data-ve-kind')).toBe('text')
    expect(summary?.getAttribute('data-ve-inline')).toBe('true')
    expect(summary?.getAttribute('data-ve-section-id')).toBe('acc-1')

    const text = container.querySelector('[data-ve-field="text"]')
    expect(text).toBeTruthy()
    expect(text?.getAttribute('data-ve-kind')).toBe('richtext')
    expect(text?.getAttribute('data-ve-inline')).toBe('true')
  })
})
