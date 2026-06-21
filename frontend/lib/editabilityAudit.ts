// CMS-editability audit (B.1): route ownership and expected change path.
export type RouteStatus = 'cms' | 'hardcoded'

export interface AuditEntry {
  status: RouteStatus
  changePath: 'inline' | 'pw' | 'ticket'
  pwPageId?: number
  notes?: string
}

export const AUDIT: Record<string, AuditEntry> = {
  '/': { status: 'hardcoded', changePath: 'pw' },
  '/(cms)/[...slug]': { status: 'cms', changePath: 'inline' },
  '/abos': { status: 'cms', changePath: 'inline' },
  '/aktuelles': { status: 'hardcoded', changePath: 'pw' },
  '/anmeldung': { status: 'hardcoded', changePath: 'ticket' },
  '/anmeldung/danke': { status: 'hardcoded', changePath: 'ticket' },
  '/bioco-werden': { status: 'hardcoded', changePath: 'ticket' },
  '/datenschutz': { status: 'hardcoded', changePath: 'ticket' },
  '/doi-confirm': { status: 'hardcoded', changePath: 'ticket' },
  '/gemuese': { status: 'hardcoded', changePath: 'pw' },
  '/impressum': { status: 'hardcoded', changePath: 'ticket' },
  '/kontakt': { status: 'hardcoded', changePath: 'pw' },
  '/kundenportal': { status: 'hardcoded', changePath: 'ticket' },
  '/mitmachen': { status: 'hardcoded', changePath: 'pw' },
  '/newsletter': { status: 'hardcoded', changePath: 'ticket' },
  '/solawi': { status: 'hardcoded', changePath: 'pw' },
  '/standorte-depots': { status: 'hardcoded', changePath: 'ticket' },
  '/statuten': { status: 'hardcoded', changePath: 'ticket' },
  '/tag-der-offenen-tuer': { status: 'hardcoded', changePath: 'ticket' },
  '/warteliste': { status: 'hardcoded', changePath: 'ticket' },
  '/wir': { status: 'hardcoded', changePath: 'pw' },
}

export function classifyRoute(route: string): RouteStatus {
  const entry = AUDIT[route]
  if (!entry) {
    throw new Error(`Route is not in editability audit: ${route}`)
  }
  return entry.status
}
