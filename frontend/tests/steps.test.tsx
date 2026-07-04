import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
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

const stepsSection: ContentSection = {
  id: 'steps-1',
  title: 'So geht es weiter',
  text: '',
  layout: 'component',
  component: 'steps',
  config: {
    step1_title: 'Bestätigungs-E-Mail',
    step1_text: 'Du erhältst eine E-Mail mit Bestätigungslink.',
    step2_title: 'Rechnung',
    step2_text: 'Nach Bestätigung erhältst du eine Rechnung.',
    step3_title: 'Start',
    step3_text: 'Ab Januar startet die Gemüseverteilung!',
  },
}

describe('steps registry entry', () => {
  it('is registered with the German label "Nummerierte Schritte"', () => {
    const resolved = resolveComponentRegistryEntry('steps')
    expect(resolved).toBeTruthy()
    expect(resolved?.entry.label).toBe('Nummerierte Schritte')
    expect(resolved?.entry.kind).toBe('renderable')
    expect(resolved?.entry.cmsFields).toEqual(
      expect.arrayContaining(['section_component', 'section_title', 'section_config']),
    )
  })

  it('has a configSchema with German-labelled text fields for all 4 steps', () => {
    const schema = resolveComponentRegistryEntry('steps')?.entry.configSchema || []
    for (let n = 1; n <= 4; n++) {
      const title = schema.find((f) => f.key === `step${n}_title`)
      const text = schema.find((f) => f.key === `step${n}_text`)
      expect(title, `step${n}_title schema field`).toBeTruthy()
      expect(title?.type).toBe('text')
      expect(title?.label).toBe(`Schritt ${n} — Titel`)
      expect(text, `step${n}_text schema field`).toBeTruthy()
      expect(text?.type).toBe('text')
      expect(text?.label).toBe(`Schritt ${n} — Text`)
    }
  })

  it('has empty-string defaults for every step field', () => {
    const defaults = resolveComponentRegistryEntry('steps')?.entry.defaultConfig || {}
    for (let n = 1; n <= 4; n++) {
      expect(defaults[`step${n}_title`]).toBe('')
      expect(defaults[`step${n}_text`]).toBe('')
    }
  })

  it('has a renderer and owns its layout', () => {
    expect(componentRendererKeys).toContain('steps')
    const result = renderRegisteredComponent(stepsSection)
    expect(result.node).toBeTruthy()
    expect(result.ownsLayout).toBe(true)
  })
})

describe('steps rendering (numbered steps markup from /anmeldung/danke)', () => {
  it('renders numbered step-item circles matching the danke page markup', () => {
    const { container } = render(<SectionRenderer sections={[stepsSection]} />)

    const wrapper = container.querySelector('.next-steps')
    expect(wrapper).toBeTruthy()

    const items = container.querySelectorAll('.next-steps .step-item')
    expect(items).toHaveLength(3)

    const numbers = Array.from(container.querySelectorAll('.step-item .step-number')).map((el) => el.textContent)
    expect(numbers).toEqual(['1', '2', '3'])

    // step title as h3, step text as p (same structure as the danke page)
    const firstItem = items[0]
    expect(firstItem.querySelector('h3')?.textContent).toBe('Bestätigungs-E-Mail')
    expect(firstItem.querySelector('p')?.textContent).toBe('Du erhältst eine E-Mail mit Bestätigungslink.')
  })

  it('renders the optional section title as heading', () => {
    render(<SectionRenderer sections={[stepsSection]} />)
    expect(screen.getByText('So geht es weiter')).toBeTruthy()
  })

  it('skips empty steps (only non-empty config entries render)', () => {
    const sparse: ContentSection = {
      ...stepsSection,
      id: 'steps-sparse',
      config: {
        step1_title: 'Nur einer',
        step1_text: 'Einziger Schritt.',
        step2_title: '',
        step2_text: '',
      },
    }
    const { container } = render(<SectionRenderer sections={[sparse]} />)
    expect(container.querySelectorAll('.step-item')).toHaveLength(1)
    expect(container.querySelector('.step-number')?.textContent).toBe('1')
  })

  it('renders no step items at all when config is empty', () => {
    const empty: ContentSection = { ...stepsSection, id: 'steps-empty', config: {} }
    const { container } = render(<SectionRenderer sections={[empty]} />)
    expect(container.querySelectorAll('.step-item')).toHaveLength(0)
  })

  it('exposes VE markers: inline title and structured component container', () => {
    const { container } = render(<SectionRenderer sections={[stepsSection]} visualEditor={true} />)

    const title = container.querySelector('[data-ve-field="title"]')
    expect(title).toBeTruthy()
    expect(title?.getAttribute('data-ve-inline')).toBe('true')

    const structured = container.querySelector('[data-ve-field="component"]')
    expect(structured).toBeTruthy()
    expect(structured?.getAttribute('data-ve-kind')).toBe('structured')
    expect(structured?.getAttribute('data-ve-section-id')).toBe('steps-1')
  })
})
