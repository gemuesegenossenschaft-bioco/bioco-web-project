import React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'

vi.mock('next/link', () => ({
  default: ({ href, children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => <a href={String(href)} {...props}>{children}</a>,
}))

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('@/components/forms/CaptchaField', () => ({
  CaptchaField: () => <div data-testid="captcha-field" />,
}))

describe('captcha-free subscription forms', () => {
  it('does not render or require captcha for newsletter subscription', async () => {
    const { SubscribeForm } = await import('@/components/forms/SubscribeForm')
    render(<SubscribeForm />)

    expect(screen.queryByTestId('captcha-field')).not.toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('E-Mail-Adresse *'), {
      target: { value: 'jane@example.com' },
    })
    fireEvent.click(screen.getByLabelText(/Datenschutzbestimmungen/))

    expect(screen.getByRole('button', { name: 'Abonnieren' })).not.toBeDisabled()
  })

  it('does not render or require captcha on the bioco-werden final step', async () => {
    const { MembershipForm } = await import('@/components/forms/MembershipForm')
    const { container } = render(<MembershipForm initialData={{ aboType: 'standard', additionalShares: 0, membershipType: 'abo' }} />)

    for (const checkbox of Array.from(
      container.querySelectorAll<HTMLInputElement>('.commitment-checklist input[type="checkbox"]')
    )) {
      fireEvent.click(checkbox)
    }
    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }))

    fireEvent.change(screen.getByLabelText('Vorname *'), { target: { value: 'Jane' } })
    fireEvent.change(screen.getByLabelText('Name *'), { target: { value: 'Doe' } })
    fireEvent.change(screen.getByLabelText('Adresse *'), { target: { value: 'Street 1' } })
    fireEvent.change(screen.getByLabelText('PLZ *'), { target: { value: '8000' } })
    fireEvent.change(screen.getByLabelText('Ort *'), { target: { value: 'Zurich' } })
    fireEvent.change(screen.getByLabelText('E-Mail *'), { target: { value: 'jane@example.com' } })
    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }))

    fireEvent.change(screen.getByLabelText('Depot-Auswahl *'), { target: { value: 'Depot Chrättli' } })
    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }))

    fireEvent.click(screen.getByLabelText('Montag'))
    fireEvent.click(screen.getByLabelText('morgens'))
    fireEvent.click(screen.getByLabelText('Feld/Anbau'))
    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }))

    fireEvent.click(screen.getByRole('button', { name: 'Weiter' }))
    fireEvent.click(within(screen.getByText('Zusammenfassung & Bestätigung').closest('.form-step') as HTMLElement).getByLabelText(/Datenschutzbestimmungen/))

    expect(screen.queryByTestId('captcha-field')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Anmeldung einreichen' })).not.toBeDisabled()
  })
})
