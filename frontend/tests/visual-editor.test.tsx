import React from 'react'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, act } from '@testing-library/react'
import type { ContentSection } from '@/lib/processwire-types'

vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => <img data-testid="next-image" {...props} />,
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

const testSections: ContentSection[] = [
  { id: 'section-1', title: 'First', text: '<p>One</p>', layout: 'rich_text' },
  { id: 'section-2', title: 'Second', text: '<p>Two</p>', layout: 'split_media_text', image: '/img.jpg' },
  { id: 'section-3', title: 'Third', text: '<p>Three</p>', layout: 'full_width_banner' },
]

describe('SectionRenderer data-section-id attributes', () => {
  it('adds data-section-id to each section wrapper in visual editor mode', async () => {
    const { SectionRenderer } = await import('@/components/sections/SectionRenderer')
    const { container } = render(
      <SectionRenderer sections={testSections} visualEditor={true} />
    )
    const annotated = container.querySelectorAll('[data-section-id]')
    expect(annotated).toHaveLength(3)
    expect(annotated[0].getAttribute('data-section-id')).toBe('section-1')
    expect(annotated[1].getAttribute('data-section-id')).toBe('section-2')
    expect(annotated[2].getAttribute('data-section-id')).toBe('section-3')
  })

  it('does NOT add data-section-id when visualEditor is false', async () => {
    const { SectionRenderer } = await import('@/components/sections/SectionRenderer')
    const { container } = render(
      <SectionRenderer sections={testSections} visualEditor={false} />
    )
    const annotated = container.querySelectorAll('[data-section-id]')
    expect(annotated).toHaveLength(0)
  })

  it('includes data-section-layout on each section', async () => {
    const { SectionRenderer } = await import('@/components/sections/SectionRenderer')
    const { container } = render(
      <SectionRenderer sections={testSections} visualEditor={true} />
    )
    const annotated = container.querySelectorAll('[data-section-layout]')
    expect(annotated).toHaveLength(3)
    expect(annotated[0].getAttribute('data-section-layout')).toBe('rich_text')
    expect(annotated[1].getAttribute('data-section-layout')).toBe('split_media_text')
    expect(annotated[2].getAttribute('data-section-layout')).toBe('full_width_banner')
  })
})

describe('useVisualEditor hook', () => {
  let postMessageSpy: ReturnType<typeof vi.fn>

  beforeEach(() => {
    postMessageSpy = vi.fn()
    // Simulate being inside an iframe
    Object.defineProperty(window, 'parent', {
      value: { postMessage: postMessageSpy },
      writable: true,
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('sends ready message on mount when inside iframe', async () => {
    const { useVisualEditor } = await import('@/hooks/useVisualEditor')

    function TestComponent() {
      useVisualEditor({ enabled: true, sections: testSections })
      return <div>test</div>
    }

    render(<TestComponent />)

    expect(postMessageSpy).toHaveBeenCalledWith(
      expect.objectContaining({ type: 'bioco:visual-editor:ready' }),
      '*'
    )
  })

  it('sends section-click message when handleSectionClick called', async () => {
    const { useVisualEditor } = await import('@/hooks/useVisualEditor')
    let clickHandler: (sectionId: string) => void

    function TestComponent() {
      const { handleSectionClick } = useVisualEditor({ enabled: true, sections: testSections })
      clickHandler = handleSectionClick
      return <div>test</div>
    }

    render(<TestComponent />)
    act(() => clickHandler!('section-1'))

    expect(postMessageSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'bioco:visual-editor:section-click',
        sectionId: 'section-1',
        section: testSections[0],
      }),
      '*'
    )
  })

  it('updates section data when receiving section-update message', async () => {
    const { useVisualEditor } = await import('@/hooks/useVisualEditor')
    let currentSections: ContentSection[] = []

    function TestComponent() {
      const { sections: liveSections } = useVisualEditor({ enabled: true, sections: testSections })
      currentSections = liveSections
      return (
        <div>
          {liveSections.map(s => <span key={s.id} data-testid={s.id}>{s.title}</span>)}
        </div>
      )
    }

    render(<TestComponent />)

    // Simulate incoming postMessage from PW admin
    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: {
          type: 'bioco:visual-editor:section-update',
          sectionId: 'section-1',
          field: 'title',
          value: 'Updated First',
        },
      }))
    })

    expect(screen.getByTestId('section-1').textContent).toBe('Updated First')
  })

  it('does nothing when enabled is false', async () => {
    const { useVisualEditor } = await import('@/hooks/useVisualEditor')

    function TestComponent() {
      useVisualEditor({ enabled: false, sections: testSections })
      return <div>test</div>
    }

    render(<TestComponent />)

    expect(postMessageSpy).not.toHaveBeenCalled()
  })
})

describe('Visual editor postMessage protocol', () => {
  it('ignores messages with wrong type prefix', async () => {
    const { useVisualEditor } = await import('@/hooks/useVisualEditor')

    function TestComponent() {
      const { sections: liveSections } = useVisualEditor({ enabled: true, sections: testSections })
      return <span data-testid="title">{liveSections[0].title}</span>
    }

    render(<TestComponent />)

    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: { type: 'unrelated:message', sectionId: 'section-1', field: 'title', value: 'HACKED' },
      }))
    })

    expect(screen.getByTestId('title').textContent).toBe('First')
  })

  it('handles section-highlight message by adding highlight class', async () => {
    const { useVisualEditor } = await import('@/hooks/useVisualEditor')

    function TestComponent() {
      const { highlightedSectionId } = useVisualEditor({ enabled: true, sections: testSections })
      return <span data-testid="highlighted">{highlightedSectionId || 'none'}</span>
    }

    render(<TestComponent />)

    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: { type: 'bioco:visual-editor:section-highlight', sectionId: 'section-2' },
      }))
    })

    expect(screen.getByTestId('highlighted').textContent).toBe('section-2')
  })
})
