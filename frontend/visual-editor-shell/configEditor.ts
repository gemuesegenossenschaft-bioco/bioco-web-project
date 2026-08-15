/**
 * Sidebar component-config editor. Renders the registry configSchema of the
 * selected section's component and emits typed per-key changes
 * (field-change {field: 'config', configKey}). Supported field types:
 * select, range, text, number.
 */

export interface ConfigSchemaOption {
  label: string
  value: string | number
}

export interface ConfigSchemaField {
  key: string
  label: string
  type: 'select' | 'range' | 'text' | 'number'
  options?: ConfigSchemaOption[]
  min?: number
  max?: number
  step?: number
  placeholder?: string
}

export interface RenderConfigEditorOptions {
  doc: Document
  schema: readonly ConfigSchemaField[]
  config: Record<string, unknown>
  onChange: (key: string, value: unknown) => void
}

function coerceNumber(raw: string, fallback: unknown): unknown {
  const value = Number(raw)
  return Number.isFinite(value) ? value : fallback
}

export function sanitizeConfigSchema(raw: unknown): ConfigSchemaField[] {
  if (!Array.isArray(raw)) return []
  const fields: ConfigSchemaField[] = []
  for (const entry of raw) {
    if (typeof entry !== 'object' || entry === null) continue
    const field = entry as Partial<ConfigSchemaField> & Record<string, unknown>
    if (typeof field.key !== 'string' || !field.key) continue
    if (field.type !== 'select' && field.type !== 'range' && field.type !== 'text' && field.type !== 'number') continue
    fields.push({
      key: field.key,
      label: typeof field.label === 'string' && field.label ? field.label : field.key,
      type: field.type,
      options: Array.isArray(field.options)
        ? field.options.filter(
            (option): option is ConfigSchemaOption =>
              typeof option === 'object' && option !== null && 'value' in option
          )
        : undefined,
      min: typeof field.min === 'number' ? field.min : undefined,
      max: typeof field.max === 'number' ? field.max : undefined,
      step: typeof field.step === 'number' ? field.step : undefined,
      placeholder: typeof field.placeholder === 'string' ? field.placeholder : undefined,
    })
  }
  return fields
}

export function renderComponentConfigEditor(options: RenderConfigEditorOptions): HTMLElement {
  const { doc, schema, config, onChange } = options
  const root = doc.createElement('div')
  root.className = 've-config-editor'

  for (const field of schema) {
    const group = doc.createElement('div')
    group.className = 've-field-group'

    const label = doc.createElement('label')
    label.textContent = field.label
    group.appendChild(label)

    const current = config[field.key]

    if (field.type === 'select') {
      const select = doc.createElement('select')
      const optionValues: Array<string | number> = []
      for (const option of field.options || []) {
        const el = doc.createElement('option')
        el.value = String(option.value)
        el.textContent = option.label != null ? String(option.label) : String(option.value)
        select.appendChild(el)
        optionValues.push(option.value)
      }
      if (current != null) select.value = String(current)
      select.addEventListener('change', () => {
        const match = optionValues.find((value) => String(value) === select.value)
        onChange(field.key, match !== undefined ? match : select.value)
      })
      group.appendChild(select)
    } else if (field.type === 'range' || field.type === 'number') {
      const input = doc.createElement('input')
      input.type = field.type === 'range' ? 'range' : 'number'
      if (field.min != null) input.min = String(field.min)
      if (field.max != null) input.max = String(field.max)
      if (field.step != null) input.step = String(field.step)
      if (current != null) input.value = String(current)
      const emit = () => onChange(field.key, coerceNumber(input.value, current))
      // ranges feel live; numbers commit on change
      input.addEventListener(field.type === 'range' ? 'input' : 'change', emit)
      group.appendChild(input)
    } else {
      const input = doc.createElement('input')
      input.type = 'text'
      if (field.placeholder) input.placeholder = field.placeholder
      if (current != null) input.value = String(current)
      input.addEventListener('change', () => onChange(field.key, input.value))
      group.appendChild(input)
    }

    root.appendChild(group)
  }

  return root
}
