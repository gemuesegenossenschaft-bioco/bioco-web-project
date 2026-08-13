export function safeSitePath(value: unknown): string {
  if (typeof value !== 'string') return ''

  const href = value
  if (!href.startsWith('/') || href.startsWith('//') || /[\s\u0000-\u001f\u007f\\]/u.test(href)) {
    return ''
  }

  return href
}

export function safeDocumentHref(value: unknown): string {
  if (typeof value !== 'string') return ''

  const href = value
  if (/[\s\u0000-\u001f\u007f\\]/u.test(href)) return ''

  const scheme = href.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase()
  if (scheme === 'http' || scheme === 'https') {
    try {
      const parsed = new URL(href)
      return parsed.hostname ? href : ''
    } catch {
      return ''
    }
  }
  if (scheme === 'mailto' || scheme === 'tel') {
    return href
  }

  return safeSitePath(href)
}
