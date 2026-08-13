export function safeSitePath(value: unknown): string {
  if (typeof value !== 'string') return ''

  const href = value.trim()
  if (!href.startsWith('/') || href.startsWith('//') || /[\u0000-\u0020\\]/.test(href)) {
    return ''
  }

  return href
}
