import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { SectionRenderer } from '@/components/sections/SectionRenderer'
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

describe('SectionRenderer image filters', () => {
  it('applies CSS filter style for brightness, contrast, saturate on images', () => {
    const sections: ContentSection[] = [
      {
        id: 'test-section',
        title: 'Test',
        text: '<p>Hello</p>',
        layout: 'split_media_text',
        image: '/test.jpg',
        imageAlt: 'Test image',
        imageBrightness: 0.8,
        imageContrast: 1.2,
        imageSaturate: 0.5,
      },
    ]

    const { container } = render(<SectionRenderer sections={sections} />)
    const mediaFrame = container.querySelector('.cms-media-frame')
    expect(mediaFrame).toBeTruthy()
    const style = (mediaFrame as HTMLElement).style.filter
    expect(style).toContain('brightness(0.8)')
    expect(style).toContain('contrast(1.2)')
    expect(style).toContain('saturate(0.5)')
  })

  it('does not add filter style when no image filter values set', () => {
    const sections: ContentSection[] = [
      {
        id: 'no-filter',
        title: 'No filter',
        text: '<p>Plain</p>',
        layout: 'split_media_text',
        image: '/plain.jpg',
      },
    ]

    const { container } = render(<SectionRenderer sections={sections} />)
    const mediaFrame = container.querySelector('.cms-media-frame')
    expect(mediaFrame).toBeTruthy()
    const style = (mediaFrame as HTMLElement).style.filter
    expect(style).toBeFalsy()
  })

  it('does not render duplicate heading when section text already contains heading tags', () => {
    const sections: ContentSection[] = [
      {
        id: 'rich-text-heading',
        title: 'Duplicate Heading',
        text: '<h2>Duplicate Heading</h2><p>Body</p>',
        layout: 'rich_text',
      },
    ]

    const { container } = render(<SectionRenderer sections={sections} />)
    const headings = container.querySelectorAll('h2')
    expect(headings).toHaveLength(1)
    expect(screen.getByText('Duplicate Heading')).toBeTruthy()
  })

  it('renders layout-owned media_text component without generic cms-component wrapper', () => {
    const sections: ContentSection[] = [
      {
        id: 'media-text-component',
        title: 'Wir',
        text: '<p>Body</p>',
        layout: 'component',
        component: 'media_text',
        image: '/wir.jpg',
        config: {
          mediaSide: 'right',
          mediaWidth: '40',
          mediaRatio: '1:1',
        },
      },
    ]

    const { container } = render(<SectionRenderer sections={sections} visualEditor={true} />)
    expect(container.querySelector('.cms-component')).toBeNull()
    expect(container.querySelector('[data-section-id="media-text-component"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-field="media"]')).toBeTruthy()
    expect(screen.getByText('Wir')).toBeTruthy()
  })

  it('renders timeline_item component with eyebrow badge and rich text body', () => {
    const sections: ContentSection[] = [
      {
        id: 'timeline-item',
        title: 'Neuer Standort',
        eyebrow: '2024',
        text: '<p>Beschreibung</p>',
        layout: 'component',
        component: 'timeline_item',
        config: {
          emphasis: 'highlight',
        },
      },
    ]

    render(<SectionRenderer sections={sections} visualEditor={true} />)
    expect(screen.getByText('2024')).toBeTruthy()
    expect(screen.getByText('Neuer Standort')).toBeTruthy()
    expect(screen.getByText('Beschreibung')).toBeTruthy()
  })
})
