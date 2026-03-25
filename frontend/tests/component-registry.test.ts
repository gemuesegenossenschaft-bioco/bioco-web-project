import { describe, expect, it } from 'vitest'
import { vi } from 'vitest'
import { componentRendererKeys, renderRegisteredComponent } from '@/lib/componentRenderers'
import {
  getRenderableComponentRegistryEntries,
  resolveComponentRegistryEntry,
} from '@/lib/componentRegistry'
import type { ContentSection } from '@/lib/processwire-types'

vi.mock('next/image', () => ({
  default: () => null,
}))
vi.mock('@/components/CTA', () => ({ CTA: () => null }))
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

const sampleSection: ContentSection = {
  id: 'sample',
  title: 'Sample',
  text: '<p>Sample</p>',
  layout: 'component',
  component: 'depot_map',
}

describe('component registry', () => {
  it('keeps renderable registry keys aligned with renderer keys', () => {
    const registryKeys = getRenderableComponentRegistryEntries()
      .map((entry) => entry.key)
      .sort()

    expect(componentRendererKeys.slice().sort()).toEqual(registryKeys)
  })

  it('resolves aliases to canonical semantic keys', () => {
    expect(resolveComponentRegistryEntry('timeline')?.canonicalKey).toBe('timeline_header')
    expect(resolveComponentRegistryEntry('timeline-item')?.canonicalKey).toBe('timeline_item')
  })

  it('renders known renderable keys and ignores unmapped keys safely', () => {
    expect(renderRegisteredComponent(sampleSection).node).toBeTruthy()
    expect(renderRegisteredComponent({ ...sampleSection, component: 'timeline-item' }).node).toBeTruthy()
    expect(renderRegisteredComponent({ ...sampleSection, component: 'timeline-item' }).ownsLayout).toBe(true)
    expect(renderRegisteredComponent({ ...sampleSection, component: 'totally_unknown_component' }).node).toBeNull()
  })
})
