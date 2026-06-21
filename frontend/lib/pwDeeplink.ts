import type { AuditEntry } from './editabilityAudit'

export type Affordance = 'inline' | 'pw-link' | 'ticket'

export function pwEditUrl(pageId: number): string {
  if (!Number.isFinite(pageId) || pageId <= 0) {
    throw new Error('pageId must be a positive finite number')
  }

  const base = (process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL || 'https://cms.bioco.ch').replace(/\/+$/, '')
  return `${base}/processwire/page/edit/?id=${pageId}`
}

export function regionAffordance(entry: AuditEntry): Affordance {
  switch (entry.changePath) {
    case 'inline':
      return 'inline'
    case 'pw':
      return 'pw-link'
    case 'ticket':
    default:
      return 'ticket'
  }
}
