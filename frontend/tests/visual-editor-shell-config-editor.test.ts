import { describe, expect, it, vi } from 'vitest'
import { renderComponentConfigEditor } from '../visual-editor-shell/configEditor'

// G.2 — sidebar component-config editor. Supports the four registry
// configSchema field types (select, range, text, number) per CLAUDE.md.

const schema = [
  {
    key: 'variant',
    label: 'Darstellung',
    type: 'select' as const,
    options: [
      { label: 'Standard', value: 'standard' },
      { label: 'Banner', value: 'banner' },
    ],
  },
  { key: 'limit', label: 'Anzahl Einträge', type: 'number' as const, min: 1, max: 12 },
  { key: 'ratio', label: 'Verhältnis', type: 'range' as const, min: 0, max: 1, step: 0.1 },
  { key: 'note', label: 'Hinweis', type: 'text' as const, placeholder: 'Optional' },
]

function render(config: Record<string, unknown> = {}) {
  const onChange = vi.fn()
  const el = renderComponentConfigEditor({
    doc: document,
    schema,
    config,
    onChange,
  })
  document.body.innerHTML = ''
  document.body.appendChild(el)
  return { el, onChange }
}

describe('renderComponentConfigEditor', () => {
  it('renders one labeled input per schema field with current values', () => {
    const { el } = render({ variant: 'banner', limit: 6, ratio: 0.5, note: 'Hi' })
    const select = el.querySelector('select') as HTMLSelectElement
    expect(select.value).toBe('banner')
    expect(Array.from(select.options).map((o) => o.textContent)).toEqual(['Standard', 'Banner'])

    const number = el.querySelector('input[type="number"]') as HTMLInputElement
    expect(number.value).toBe('6')
    expect(number.min).toBe('1')
    expect(number.max).toBe('12')

    const range = el.querySelector('input[type="range"]') as HTMLInputElement
    expect(range.value).toBe('0.5')
    expect(range.step).toBe('0.1')

    const text = el.querySelector('input[type="text"]') as HTMLInputElement
    expect(text.value).toBe('Hi')
    expect(text.placeholder).toBe('Optional')

    expect(Array.from(el.querySelectorAll('label')).map((l) => l.textContent)).toEqual([
      'Darstellung', 'Anzahl Einträge', 'Verhältnis', 'Hinweis',
    ])
  })

  it('emits typed change events per field', () => {
    const { el, onChange } = render({ variant: 'standard' })

    const select = el.querySelector('select') as HTMLSelectElement
    select.value = 'banner'
    select.dispatchEvent(new Event('change', { bubbles: true }))
    expect(onChange).toHaveBeenLastCalledWith('variant', 'banner')

    const number = el.querySelector('input[type="number"]') as HTMLInputElement
    number.value = '7'
    number.dispatchEvent(new Event('change', { bubbles: true }))
    expect(onChange).toHaveBeenLastCalledWith('limit', 7)

    const range = el.querySelector('input[type="range"]') as HTMLInputElement
    range.value = '0.3'
    range.dispatchEvent(new Event('input', { bubbles: true }))
    expect(onChange).toHaveBeenLastCalledWith('ratio', 0.3)

    const text = el.querySelector('input[type="text"]') as HTMLInputElement
    text.value = 'Neu'
    text.dispatchEvent(new Event('change', { bubbles: true }))
    expect(onChange).toHaveBeenLastCalledWith('note', 'Neu')
  })

  it('selects select values numerically when option values are numbers', () => {
    const numericSchema = [
      {
        key: 'columns',
        label: 'Spalten',
        type: 'select' as const,
        options: [
          { label: 'Zwei', value: 2 },
          { label: 'Drei', value: 3 },
        ],
      },
    ]
    const onChange = vi.fn()
    const el = renderComponentConfigEditor({ doc: document, schema: numericSchema, config: { columns: 3 }, onChange })
    const select = el.querySelector('select') as HTMLSelectElement
    expect(select.value).toBe('3')
    select.value = '2'
    select.dispatchEvent(new Event('change', { bubbles: true }))
    expect(onChange).toHaveBeenLastCalledWith('columns', 2)
  })
})
