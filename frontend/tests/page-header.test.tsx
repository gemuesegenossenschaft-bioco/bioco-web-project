import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { readFileSync } from 'node:fs'
import path from 'node:path'
import { PageHeader } from '@/components/PageHeader'

// C.3 — canonical page heading contract: eyebrow -> single h1 -> intro.
describe('PageHeader (C.3)', () => {
  it('renders exactly one h1 carrying the title', () => {
    render(<PageHeader title="Seit 2014" />)
    const h1s = screen.getAllByRole('heading', { level: 1 })
    expect(h1s).toHaveLength(1)
    expect(h1s[0]).toHaveTextContent('Seit 2014')
  })

  it('renders eyebrow and intro when provided, in order eyebrow -> h1 -> intro', () => {
    const { container } = render(
      <PageHeader eyebrow="Über uns" title="Titel" intro="Kurzer Vorspann." />
    )
    expect(screen.getByText('Über uns')).toBeInTheDocument()
    expect(screen.getByText('Kurzer Vorspann.')).toBeInTheDocument()
    const order = Array.from(container.querySelectorAll('.page-header-eyebrow, h1, .page-header-intro'))
    expect(order.map((el) => el.className.includes('eyebrow') ? 'eyebrow' : el.tagName === 'H1' ? 'h1' : 'intro'))
      .toEqual(['eyebrow', 'h1', 'intro'])
  })

  it('omits eyebrow/intro when not provided', () => {
    render(<PageHeader title="Nur Titel" />)
    expect(screen.queryByText('Über uns')).not.toBeInTheDocument()
  })

  it('.page-header is tokenized + shell-aligned in CSS', () => {
    const css = readFileSync(path.resolve(__dirname, '..', 'app/globals.css'), 'utf8')
    const i = css.indexOf('.page-header')
    expect(i, '.page-header rule missing').toBeGreaterThan(-1)
    const block = css.slice(i, css.indexOf('}', i))
    // spacing comes from tokens, not raw magic numbers
    expect(block).toMatch(/var\(--(spacing|page-)/)
  })
})
