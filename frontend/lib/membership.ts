export interface MembershipInput {
  firstName?: string
  lastName?: string
  email?: string
  address?: string
  zip?: string
  city?: string
  phone?: string
  membershipType?: string
  aboType?: string
  additionalShares?: number
  sharesOnly?: number
  depot?: string
  paymentType?: string
  preferredDays?: string[]
  preferredTimes?: string[]
  activityAreas?: string[]
  otherActivity?: string
  zusatzabos?: string[]
  weitereProdukte?: string
  commitmentAccepted?: boolean[]
  privacyAccept?: boolean
  [k: string]: any
}

export interface MembershipValidation {
  ok: boolean
  errors: Record<string, string>
}

// Number of items in the commitment checklist (Statuten-/Betriebsreglement
// acknowledgement) rendered by components/forms/MembershipForm.tsx. Single
// source of truth — both the form and validateMembership/buildIntranetSignupPayload
// derive from this constant.
export const COMMITMENT_ITEM_COUNT = 4

function isCommitmentAcknowledgementComplete(commitmentAccepted?: boolean[]): boolean {
  return (
    Array.isArray(commitmentAccepted) &&
    commitmentAccepted.length === COMMITMENT_ITEM_COUNT &&
    commitmentAccepted.every(Boolean)
  )
}

export function validateMembership(data: MembershipInput): MembershipValidation {
  const errors: Record<string, string> = {}
  const requiredFields = ['firstName', 'lastName', 'email', 'address', 'zip', 'city'] as const

  for (const field of requiredFields) {
    if (typeof data[field] !== 'string' || !data[field].trim()) {
      errors[field] = 'Dieses Feld ist erforderlich.'
    }
  }

  if (!isCommitmentAcknowledgementComplete(data.commitmentAccepted)) {
    errors.commitment = 'Bitte bestätige alle Punkte, bevor du fortfährst.'
  }

  if (data.privacyAccept !== true) {
    errors.privacyAccept = 'Bitte akzeptieren Sie die Datenschutzerklärung.'
  }

  if (typeof data.email === 'string' && data.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
    errors.email = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'
  }

  return { ok: Object.keys(errors).length === 0, errors }
}

// D.2a — bioco.ch MembershipForm -> intranet.bioco.ch/my/signup/ (Django) field mapping.
//
// PROVISIONAL: the intranet's actual field names are unconfirmed. The signup page
// 403s for unauthenticated requests, so these names are inferred from the
// migration mapping and not yet verified against a logged-in enumeration of
// the real Django form. Centralized here so that once
// the real names are confirmed, only this const needs to change.
export const INTRANET_FIELD_NAMES = {
  firstName: 'first_name',
  lastName: 'last_name',
  email: 'email',
  phone: 'phone',
  street: 'street',
  postalCode: 'postal_code',
  city: 'city',
  membershipType: 'membership_type',
  abo: 'abo',
  shares: 'shares',
  depot: 'depot',
  paymentInterval: 'payment_interval',
  terms: 'terms',
  notes: 'notes',
} as const

// Required share count per abo tier, mirrored from ABO_CONFIG in
// components/forms/MembershipForm.tsx (kept minimal here — only the share
// counts, not prices — since that's all the intranet payload needs).
const REQUIRED_SHARES_BY_ABO_TYPE: Record<string, number> = {
  halb: 1,
  standard: 2,
  doppel: 4,
  none: 0,
}

function totalShares(data: MembershipInput): number {
  if (data.membershipType === 'shares-only') {
    return data.sharesOnly ?? 0
  }
  const required = REQUIRED_SHARES_BY_ABO_TYPE[data.aboType ?? ''] ?? 0
  return required + (data.additionalShares ?? 0)
}

function buildNotes(data: MembershipInput): string {
  const lines: string[] = []

  if (data.preferredDays?.length) {
    lines.push(`Bevorzugte Tage: ${data.preferredDays.join(', ')}`)
  }
  if (data.preferredTimes?.length) {
    lines.push(`Bevorzugte Zeiten: ${data.preferredTimes.join(', ')}`)
  }
  if (data.activityAreas?.length) {
    lines.push(`Tätigkeitsbereiche: ${data.activityAreas.join(', ')}`)
  }
  if (data.otherActivity?.trim()) {
    lines.push(`Andere Tätigkeit: ${data.otherActivity.trim()}`)
  }
  if (data.zusatzabos?.length) {
    lines.push(`Zusatzabos: ${data.zusatzabos.join(', ')}`)
  }
  if (data.weitereProdukte?.trim()) {
    lines.push(`Weitere Produkte: ${data.weitereProdukte.trim()}`)
  }

  return lines.join('\n')
}

// Pure mapping only — see lib/intranetSignup.ts for the adapter that sends this.
export function buildIntranetSignupPayload(data: MembershipInput): Record<string, string> {
  const terms = isCommitmentAcknowledgementComplete(data.commitmentAccepted) && data.privacyAccept === true

  return {
    [INTRANET_FIELD_NAMES.firstName]: data.firstName ?? '',
    [INTRANET_FIELD_NAMES.lastName]: data.lastName ?? '',
    [INTRANET_FIELD_NAMES.email]: data.email ?? '',
    [INTRANET_FIELD_NAMES.phone]: data.phone ?? '',
    [INTRANET_FIELD_NAMES.street]: data.address ?? '',
    [INTRANET_FIELD_NAMES.postalCode]: data.zip ?? '',
    [INTRANET_FIELD_NAMES.city]: data.city ?? '',
    [INTRANET_FIELD_NAMES.membershipType]: data.membershipType ?? '',
    [INTRANET_FIELD_NAMES.abo]: data.aboType ?? '',
    [INTRANET_FIELD_NAMES.shares]: String(totalShares(data)),
    [INTRANET_FIELD_NAMES.depot]: data.depot ?? '',
    [INTRANET_FIELD_NAMES.paymentInterval]: data.paymentType ?? '',
    [INTRANET_FIELD_NAMES.terms]: terms ? 'on' : '',
    [INTRANET_FIELD_NAMES.notes]: buildNotes(data),
  }
}
