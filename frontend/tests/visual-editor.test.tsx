import React from 'react'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, act, fireEvent, waitFor } from '@testing-library/react'
import type { ContentSection } from '@/lib/processwire-types'

let mockSearch = ''

vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => <img data-testid="next-image" {...props} />,
}))
vi.mock('next/link', () => ({
  default: ({ href, children, ...props }: Record<string, unknown>) => <a href={String(href)} {...props}>{children}</a>,
}))
vi.mock('next/navigation', () => ({
  useSearchParams: () => ({
    get: (key: string) => new URLSearchParams(mockSearch).get(key),
  }),
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
vi.mock('@/components/UtilityNavigation', () => ({ UtilityNavigation: () => null }))
vi.mock('@/components/SecondaryNavigation', () => ({ PrimaryNavigation: () => null }))
vi.mock('@/components/MobileMenu', () => ({ MobileMenu: () => null }))
vi.mock('@/components/Footer', () => ({ Footer: () => null }))
vi.mock('@/components/AktuellesItem', () => ({ AktuellesItemComponent: () => null }))
vi.mock('@/components/ItemDetailModal', () => ({ ItemDetailModal: () => null }))
vi.mock('@/components/ScrollToTopLink', () => ({
  ScrollToTopLink: ({ children, ...props }: Record<string, unknown>) => <a {...props}>{children}</a>,
}))
vi.mock('@/hooks/useEventsFeed', () => ({
  useEventsFeed: () => ({ upcoming: [], isLoading: false }),
}))
vi.mock('@/components/AktuellesClient', () => ({
  filterSchnuppertage: () => [],
}))

const testSections: ContentSection[] = [
  { id: 'section-1', title: 'First', text: '<p>One</p>', layout: 'rich_text' },
  { id: 'section-2', title: 'Second', text: '<p>Two</p>', layout: 'split_media_text', image: '/img.jpg' },
  { id: 'section-3', title: 'Third', text: '<p>Three</p>', layout: 'full_width_banner' },
]

const homepageSections: ContentSection[] = [
  { id: 'willkommen', title: 'Willkommen', text: '<p>Hallo</p>', layout: 'rich_text' },
  { id: 'gemeinsam', title: 'Gemeinsam', text: '<p>Zusammen</p>', layout: 'split_media_text', image: '/img.jpg' },
  { id: 'kennenlernen', title: 'Kennenlernen', text: '<p>Besuch uns</p>', layout: 'full_width_banner' },
]

beforeEach(() => {
  mockSearch = ''
})

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

  it('adds field-level data-ve markers in visual editor mode', async () => {
    const { SectionRenderer } = await import('@/components/sections/SectionRenderer')
    const { container } = render(
      <SectionRenderer
        sections={[{
          id: 'section-markers',
          title: 'Marker Title',
          eyebrow: 'Marker Eyebrow',
          text: '<p>Marker Text</p>',
          layout: 'split_media_text',
          image: '/img.jpg',
          buttons: [{ text: 'CTA', href: '/foo', variant: 'primary' }],
        }]}
        visualEditor={true}
      />
    )

    expect(container.querySelector('[data-ve-field="eyebrow"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-field="title"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-field="text"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-field="button"][data-ve-button-index="0"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-field="media"][data-ve-target-field="section_image"]')).toBeTruthy()
  })
})

describe('VisualEditorPageSwitch', () => {
  it('renders cms visual editor page when _visual=1', async () => {
    mockSearch = '_visual=1'
    const { VisualEditorPageSwitch } = await import('@/components/VisualEditorPageSwitch')
    const { container } = render(
      <VisualEditorPageSwitch sections={testSections}>
        <div data-testid="fallback">fallback</div>
      </VisualEditorPageSwitch>
    )

    await waitFor(() => {
      expect(container.querySelector('[data-section-id="section-1"]')).toBeTruthy()
    })
    expect(screen.queryByTestId('fallback')).toBeNull()
  })

  it('renders fallback content when _visual is absent', async () => {
    mockSearch = ''
    const { VisualEditorPageSwitch } = await import('@/components/VisualEditorPageSwitch')
    render(
      <VisualEditorPageSwitch sections={testSections}>
        <div data-testid="fallback">fallback</div>
      </VisualEditorPageSwitch>
    )

    expect(screen.getByTestId('fallback')).toBeTruthy()
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

  it('does not intercept section clicks in browse mode', async () => {
    const { useVisualEditor } = await import('@/hooks/useVisualEditor')

    function TestComponent() {
      useVisualEditor({ enabled: true, sections: testSections })
      return <div data-section-id="section-1">click target</div>
    }

    render(<TestComponent />)
    postMessageSpy.mockClear()

    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: {
          type: 'bioco:visual-editor:save-state',
          mode: 'browse',
        },
      }))
    })

    fireEvent.click(screen.getByText('click target'))

    expect(postMessageSpy).not.toHaveBeenCalledWith(
      expect.objectContaining({ type: 'bioco:visual-editor:section-click' }),
      '*'
    )
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

describe('InlineVisualEditorRuntime', () => {
  let postMessageSpy: ReturnType<typeof vi.fn>

  beforeEach(() => {
    postMessageSpy = vi.fn()
    Object.defineProperty(window, 'parent', {
      value: { postMessage: postMessageSpy },
      writable: true,
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('opens the matching inline editor on first field click and focuses it', async () => {
    const { InlineVisualEditorRuntime } = await import('@/components/visual-editor/InlineVisualEditorRuntime')
    const { container } = render(
      <div>
        <div data-section-id="section-1" data-section-layout="rich_text">
          <button
            type="button"
            data-ve-section-id="section-1"
            data-ve-field="title"
            data-ve-kind="text"
            data-ve-inline="true"
          >
            Title target
          </button>
        </div>
        <InlineVisualEditorRuntime enabled={true} sections={testSections} />
      </div>
    )

    fireEvent.click(screen.getByText('Title target'))

    expect(postMessageSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'bioco:visual-editor:field-select',
        sectionId: 'section-1',
        field: 'title',
      }),
      '*'
    )

    const editor = container.querySelector('.ve-inline-text-editor') as HTMLDivElement | null
    expect(editor).toBeTruthy()
    await waitFor(() => {
      expect(document.activeElement).toBe(editor)
    })
  })

  it('opens section tools only when clicking section space outside fields', async () => {
    const { InlineVisualEditorRuntime } = await import('@/components/visual-editor/InlineVisualEditorRuntime')
    const { container } = render(
      <div>
        <div data-section-id="section-1" data-section-layout="rich_text">
          <div data-testid="section-space">Section space</div>
        </div>
        <InlineVisualEditorRuntime enabled={true} sections={testSections} />
      </div>
    )

    fireEvent.click(screen.getByTestId('section-space'))

    expect(postMessageSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'bioco:visual-editor:section-click',
        sectionId: 'section-1',
      }),
      '*'
    )
    expect(screen.getByRole('button', { name: 'Duplizieren' })).toBeInTheDocument()
    expect(container.querySelector('.ve-inline-text-editor')).toBeFalsy()
  })

  it('does not intercept field clicks in browse mode', async () => {
    const { InlineVisualEditorRuntime } = await import('@/components/visual-editor/InlineVisualEditorRuntime')
    render(
      <div>
        <div data-section-id="section-1" data-section-layout="rich_text">
          <button
            type="button"
            data-ve-section-id="section-1"
            data-ve-field="title"
            data-ve-kind="text"
            data-ve-inline="true"
          >
            Title target
          </button>
        </div>
        <InlineVisualEditorRuntime enabled={true} sections={testSections} />
      </div>
    )

    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: {
          type: 'bioco:visual-editor:save-state',
          mode: 'browse',
        },
      }))
    })

    fireEvent.click(screen.getByText('Title target'))

    expect(postMessageSpy).not.toHaveBeenCalledWith(
      expect.objectContaining({ type: 'bioco:visual-editor:field-select' }),
      '*'
    )
  })

  it('shows a busy blocker and suppresses editor interaction while locked', async () => {
    const { InlineVisualEditorRuntime } = await import('@/components/visual-editor/InlineVisualEditorRuntime')
    render(
      <div>
        <div data-section-id="section-1" data-section-layout="rich_text">
          <button
            type="button"
            data-ve-section-id="section-1"
            data-ve-field="title"
            data-ve-kind="text"
            data-ve-inline="true"
          >
            Title target
          </button>
        </div>
        <InlineVisualEditorRuntime enabled={true} sections={testSections} />
      </div>
    )

    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: {
          type: 'bioco:visual-editor:save-state',
          mode: 'edit',
          busy: true,
          busyLabel: 'Abschnitte laden…',
        },
      }))
    })

    postMessageSpy.mockClear()
    fireEvent.click(screen.getByText('Title target'))

    expect(postMessageSpy).not.toHaveBeenCalled()
    expect(screen.getByText('Abschnitte laden…')).toBeInTheDocument()
  })

  it('keeps the selected field open across section refreshes with the same id', async () => {
    const { InlineVisualEditorRuntime } = await import('@/components/visual-editor/InlineVisualEditorRuntime')
    const initialSections: ContentSection[] = [
      { id: 'section-1', title: 'Old title', text: '<p>One</p>', layout: 'rich_text' },
    ]
    const nextSections: ContentSection[] = [
      { id: 'section-1', title: 'New title', text: '<p>One</p>', layout: 'rich_text' },
    ]

    const { container, rerender } = render(
      <div>
        <div data-section-id="section-1" data-section-layout="rich_text">
          <button
            type="button"
            data-ve-section-id="section-1"
            data-ve-field="title"
            data-ve-kind="text"
            data-ve-inline="true"
          >
            Title target
          </button>
        </div>
        <InlineVisualEditorRuntime enabled={true} sections={initialSections} />
      </div>
    )

    fireEvent.click(screen.getByText('Title target'))
    rerender(
      <div>
        <div data-section-id="section-1" data-section-layout="rich_text">
          <button
            type="button"
            data-ve-section-id="section-1"
            data-ve-field="title"
            data-ve-kind="text"
            data-ve-inline="true"
          >
            Title target
          </button>
        </div>
        <InlineVisualEditorRuntime enabled={true} sections={nextSections} />
      </div>
    )

    expect(container.querySelector('.ve-inline-text-editor')?.textContent).toBe('New title')
  })
})

describe('HomeClient visual editor integration', () => {
  it('adds data-section attributes to CMS-backed homepage sections in visual mode', async () => {
    mockSearch = '_visual=1'
    const { HomeClient } = await import('@/components/HomeClient')
    const { container } = render(
      <HomeClient
        hero={{ headline: 'Hero', subtitle: '', image: null, imageAlt: '' }}
        sections={homepageSections}
        aktuellesItems={[]}
      />
    )

    expect(container.querySelector('[data-section-id="willkommen"]')).toBeTruthy()
    expect(container.querySelector('[data-section-id="gemeinsam"]')).toBeTruthy()
    expect(container.querySelector('[data-section-id="kennenlernen"]')).toBeTruthy()
    expect(container.querySelector('[data-section-id="__hero__"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-section-id="__hero__"][data-ve-field="title"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-section-id="__hero__"][data-ve-field="eyebrow"]')).toBeTruthy()
    expect(container.querySelector('[data-ve-section-id="__hero__"][data-ve-field="media"][data-ve-target-field="hero_image"]')).toBeTruthy()
  })

  it('updates homepage content on section-update messages', async () => {
    mockSearch = '_visual=1'
    const { HomeClient } = await import('@/components/HomeClient')

    render(
      <HomeClient
        hero={{ headline: 'Hero', subtitle: '', image: null, imageAlt: '' }}
        sections={homepageSections}
        aktuellesItems={[]}
      />
    )

    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: {
          type: 'bioco:visual-editor:section-update',
          sectionId: 'willkommen',
          field: 'title',
          value: 'Aktualisiert',
        },
      }))
    })

    expect(screen.getByRole('heading', { name: 'Aktualisiert' })).toBeInTheDocument()
  })

  it('updates homepage hero content on section-update messages', async () => {
    mockSearch = '_visual=1'
    const { HomeClient } = await import('@/components/HomeClient')

    render(
      <HomeClient
        hero={{ headline: 'Hero', subtitle: 'Sub', image: null, imageAlt: '' }}
        sections={homepageSections}
        aktuellesItems={[]}
      />
    )

    act(() => {
      window.dispatchEvent(new MessageEvent('message', {
        data: {
          type: 'bioco:visual-editor:section-update',
          sectionId: '__hero__',
          field: 'title',
          value: 'Neuer Hero Titel',
        },
      }))
    })

    expect(screen.getByRole('heading', { name: /Neuer Hero Titel/i })).toBeInTheDocument()
  })
})
