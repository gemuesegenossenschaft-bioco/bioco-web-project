export type RecoveryError = {
  message?: string | null
  digest?: string | null
} | null | undefined

const STALE_ERROR_PATTERNS = [
  'failed to find server action',
  'chunkloaderror',
  'loading chunk',
  'failed to fetch dynamically imported module',
  "reading 'workers'",
  "reading 'digest'",
  'unexpected end of form',
]

export function isStaleDeploymentError(error: RecoveryError): boolean {
  const message = typeof error?.message === 'string' ? error.message : ''
  const digest = typeof error?.digest === 'string' ? error.digest : ''
  const haystack = `${message} ${digest}`.toLowerCase()
  return STALE_ERROR_PATTERNS.some((pattern) => haystack.includes(pattern))
}

export function buildStaleRecoveryUrl(href: string, now = Date.now()): string {
  const parsed = new URL(href)
  parsed.searchParams.set('__fresh', String(now))
  return `${parsed.pathname}${parsed.search}${parsed.hash}`
}

export function shouldReloadForBuildChange(currentBuildId?: string | null, nextBuildId?: string | null): boolean {
  const current = String(currentBuildId || '').trim()
  const next = String(nextBuildId || '').trim()
  return current !== '' && next !== '' && current !== next
}
