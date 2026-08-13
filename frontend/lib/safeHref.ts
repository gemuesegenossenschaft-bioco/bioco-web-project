export function safeSitePath(value: unknown): string {
  if (typeof value !== 'string') return ''

  const href = value.trim()
  if (!href.startsWith('/') || href.startsWith('//') || /[\u0000-\u0020\\]/.test(href)) {
    return ''
  }

  return href
}

export function safeDocumentHref(value: unknown): string {
  if (typeof value !== 'string') return ''

  const href = value.trim()
  const compact = href.replace(/[\u0000-\u0020]+/g, '')
  const scheme = compact.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase()
  if (scheme === 'http' || scheme === 'https') {
    try {
      const parsed = new URL(href)
      return href === compact && !href.includes('\\') && parsed.hostname ? href : ''
    } catch {
      return ''
    }
  }
  if (scheme === 'mailto' || scheme === 'tel') {
    return href === compact && !href.includes('\\') ? href : ''
  }

  return safeSitePath(href)
}
