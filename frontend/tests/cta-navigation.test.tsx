import React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'

const push = vi.fn()
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }))

import { CTA } from '@/components/CTA'

describe('CTA navigation safety', () => {
  it('renders mailto and document navigation as real links', () => {
    const { rerender } = render(<CTA text="E-Mail" href="mailto:info@bioco.ch" />)
    expect(screen.getByRole('link', { name: 'E-Mail' })).toHaveAttribute('href', 'mailto:info@bioco.ch')

    rerender(<CTA text="Intranet" href="/intranet" navigation="document" />)
    expect(screen.getByRole('link', { name: 'Intranet' })).toHaveAttribute('href', '/intranet')
    expect(push).not.toHaveBeenCalled()
  })

  it('renders external HTTPS navigation as a safe native link', () => {
    render(<CTA text="Demeter" href="HTTPS://www.demeter.ch/" />)
    expect(screen.getByRole('link', { name: 'Demeter' })).toHaveAttribute('target', '_blank')
    expect(screen.getByRole('link', { name: 'Demeter' })).toHaveAttribute('rel', 'noopener noreferrer')
  })

  it.each(['//evil.example', '/unsafe\\path', ' /kontakt', '/kontakt ', '/kontakt\u00a0'])(
    'rejects unsafe document target %s',
    (href) => {
      render(<CTA text="Dokument" href={href} navigation="document" />)
      expect(screen.getByRole('button', { name: 'Dokument' })).toBeDisabled()
    },
  )

  it.each([' #kontakt', '#kontakt ', ' mailto:info@bioco.ch'])(
    'rejects whitespace around non-path target %s',
    (href) => {
      render(<CTA text="Unsicher" href={href} />)
      expect(screen.getByRole('button', { name: 'Unsicher' })).toBeDisabled()
    },
  )

  it.each(['javascript:alert(1)', 'data:text/html,unsafe', '\njava\tscript:alert(1)', '//evil.example', '/bad\\path'])(
    'rejects unsafe explicit scheme %s',
    (href) => {
      const open = vi.spyOn(window, 'open')
      render(<CTA text="Unsicher" href={href} />)
      const control = screen.getByRole('button', { name: 'Unsicher' })
      expect(control).toBeDisabled()
      fireEvent.click(control)
      expect(push).not.toHaveBeenCalled()
      expect(open).not.toHaveBeenCalled()
      open.mockRestore()
    },
  )

  it('keeps internal navigation in the Next router', () => {
    render(<CTA text="Kontakt" href="/kontakt" />)
    fireEvent.click(screen.getByRole('button', { name: 'Kontakt' }))
    expect(push).toHaveBeenCalledWith('/kontakt')
  })
})
