// CMS-editability audit (B.1): route ownership and expected change path.
// Flipped by F.7 (2026-07-05): every content route is CMS-driven via
// content_sections + SectionRenderer with NO hardcoded fallback. Editors
// change any of these pages inline in the Visual Editor or in ProcessWire.
// The single exception is /doi-confirm: a functional token-confirmation
// flow whose strings are UI states, not content (documented, code-owned).
export type RouteStatus = 'cms' | 'hardcoded'

export interface AuditEntry {
  status: RouteStatus
  changePath: 'inline' | 'pw' | 'ticket'
  pwPageId?: number
  notes?: string
}

export const AUDIT: Record<string, AuditEntry> = {
  '/': { status: 'cms', changePath: 'inline' },
  '/(cms)/[...slug]': { status: 'cms', changePath: 'inline' },
  '/abos': { status: 'cms', changePath: 'inline' },
  '/aktuelles': { status: 'cms', changePath: 'inline', notes: 'Feeds live; Intro/CTA aus CMS-Sections' },
  '/anmeldung': { status: 'cms', changePath: 'inline', notes: 'MinimalHeader-Chrome code-owned' },
  '/anmeldung/danke': { status: 'cms', changePath: 'inline', notes: 'PW-Seite /anmeldung-danke/' },
  '/bioco-werden': { status: 'cms', changePath: 'inline' },
  '/datenschutz': { status: 'cms', changePath: 'inline' },
  '/doi-confirm': {
    status: 'hardcoded',
    changePath: 'ticket',
    notes: 'Funktionsroute (Double-Opt-In-Bestätigung); Strings sind UI-Zustände, kein Inhalt',
  },
  '/gemuese': { status: 'cms', changePath: 'inline' },
  '/impressum': { status: 'cms', changePath: 'inline' },
  '/kontakt': { status: 'cms', changePath: 'inline' },
  '/kundenportal': { status: 'cms', changePath: 'inline' },
  '/mitmachen': { status: 'cms', changePath: 'inline' },
  '/newsletter': { status: 'cms', changePath: 'inline' },
  '/solawi': { status: 'cms', changePath: 'inline' },
  '/standorte-depots': { status: 'cms', changePath: 'inline' },
  '/statuten': { status: 'cms', changePath: 'inline' },
  '/tag-der-offenen-tuer': { status: 'cms', changePath: 'inline' },
  '/warteliste': { status: 'cms', changePath: 'inline' },
  '/wir': { status: 'cms', changePath: 'inline' },
}

export function classifyRoute(route: string): RouteStatus {
  const entry = AUDIT[route]
  if (!entry) {
    throw new Error(`Route is not in editability audit: ${route}`)
  }
  return entry.status
}
