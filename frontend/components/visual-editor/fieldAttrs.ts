'use client'

export interface VeFieldOptions {
  buttonIndex?: number
  targetField?: string
}

export function getVeFieldAttrs(
  enabled: boolean,
  sectionId: string,
  field: string,
  kind: string,
  inline: boolean,
  options: VeFieldOptions = {},
): Record<string, string> {
  if (!enabled || !sectionId) return {}

  const attrs: Record<string, string> = {
    'data-ve-section-id': sectionId,
    'data-ve-field': field,
    'data-ve-kind': kind,
    'data-ve-inline': inline ? 'true' : 'false',
  }

  if (options.buttonIndex != null) {
    attrs['data-ve-button-index'] = String(options.buttonIndex)
  }
  if (options.targetField) {
    attrs['data-ve-target-field'] = options.targetField
  }

  return attrs
}
