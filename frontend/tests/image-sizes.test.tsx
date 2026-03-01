import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render } from '@testing-library/react'
import { SectionRenderer } from '@/components/sections/SectionRenderer'
import type { ContentSection } from '@/lib/processwire-types'

const imageProps: Record<string, unknown>[] = []
vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => {
    imageProps.push(props)
    return <img data-testid="next-image" {...props} />
  },
}))
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

describe('Image sizes props', () => {
  beforeEach(() => {
    imageProps.length = 0
  })

  it('SplitSection images have sizes with 50vw for desktop', () => {
    const sections: ContentSection[] = [{
      id: 'split-test',
      title: 'Test',
      text: '<p>Hello</p>',
      layout: 'split_media_text',
      image: '/test.jpg',
    }]
    render(<SectionRenderer sections={sections} />)
    const imgCall = imageProps.find(p => p.src === '/test.jpg')
    expect(imgCall).toBeDefined()
    expect(imgCall!.sizes).toBeDefined()
    expect(imgCall!.sizes).toContain('50vw')
  })

  it('MediaGridSection images have sizes with 33vw for desktop', () => {
    const sections: ContentSection[] = [{
      id: 'grid-test',
      title: 'Grid',
      text: '',
      layout: 'media_grid',
      media: [
        { url: '/grid1.jpg', alt: 'Grid 1', type: 'image' },
        { url: '/grid2.jpg', alt: 'Grid 2', type: 'image' },
      ],
    }]
    render(<SectionRenderer sections={sections} />)
    const gridImgs = imageProps.filter(p =>
      ['/grid1.jpg', '/grid2.jpg'].includes(p.src as string)
    )
    expect(gridImgs.length).toBe(2)
    gridImgs.forEach(img => {
      expect(img.sizes).toContain('33vw')
    })
  })
})
