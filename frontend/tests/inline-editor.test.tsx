import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import type { ContentSection } from '@/lib/processwire-types'

vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => <img {...props} />,
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

const mockSection = {
  id: 'test-section',
  title: 'Test Section',
  text: '<p>Content</p>',
  layout: 'rich_text' as const,
}

describe('EditableSection', () => {
  it('shows "Bearbeiten" button when isEditing is true', async () => {
    const { EditableSection } = await import('@/components/sections/EditableSection')
    render(
      <EditableSection section={mockSection} isEditing={true}>
        <div>Section content</div>
      </EditableSection>
    )
    expect(screen.getByRole('button', { name: /bearbeiten/i })).toBeInTheDocument()
  })

  it('hides edit button when isEditing is false', async () => {
    const { EditableSection } = await import('@/components/sections/EditableSection')
    render(
      <EditableSection section={mockSection} isEditing={false}>
        <div>Section content</div>
      </EditableSection>
    )
    expect(screen.queryByRole('button', { name: /bearbeiten/i })).not.toBeInTheDocument()
  })

  it('always renders children content', async () => {
    const { EditableSection } = await import('@/components/sections/EditableSection')
    render(
      <EditableSection section={mockSection} isEditing={true}>
        <div data-testid="child">Section content</div>
      </EditableSection>
    )
    expect(screen.getByTestId('child')).toBeInTheDocument()
  })
})

describe('EditPanel', () => {
  it('renders title input and text editor for section', async () => {
    const { EditPanel } = await import('@/components/sections/EditPanel')
    const onSave = vi.fn()
    const onClose = vi.fn()
    render(
      <EditPanel section={mockSection} onSave={onSave} onClose={onClose} />
    )
    expect(screen.getByLabelText(/titel/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/text/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /speichern/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /abbrechen/i })).toBeInTheDocument()
  })

  it('calls onSave with updated field values', async () => {
    const { EditPanel } = await import('@/components/sections/EditPanel')
    const onSave = vi.fn()
    const onClose = vi.fn()
    render(
      <EditPanel section={mockSection} onSave={onSave} onClose={onClose} />
    )

    const titleInput = screen.getByLabelText(/titel/i) as HTMLInputElement
    fireEvent.change(titleInput, { target: { value: 'Updated Title' } })
    fireEvent.click(screen.getByRole('button', { name: /speichern/i }))

    expect(onSave).toHaveBeenCalledWith(
      expect.objectContaining({ section_title: 'Updated Title' })
    )
  })
})

describe('SectionRenderer with editing', () => {
  it('wraps sections in EditableSection when isEditing=true', async () => {
    const { SectionRenderer } = await import('@/components/sections/SectionRenderer')
    const sections: ContentSection[] = [{
      id: 'edit-test', title: 'Editable', text: '<p>Hi</p>', layout: 'rich_text',
    }]
    render(<SectionRenderer sections={sections} isEditing={true} />)
    expect(screen.getByRole('button', { name: /bearbeiten/i })).toBeInTheDocument()
  })

  it('does not wrap sections when isEditing=false', async () => {
    const { SectionRenderer } = await import('@/components/sections/SectionRenderer')
    const sections: ContentSection[] = [{
      id: 'view-test', title: 'View Only', text: '<p>Hi</p>', layout: 'rich_text',
    }]
    render(<SectionRenderer sections={sections} isEditing={false} />)
    expect(screen.queryByRole('button', { name: /bearbeiten/i })).not.toBeInTheDocument()
  })
})
