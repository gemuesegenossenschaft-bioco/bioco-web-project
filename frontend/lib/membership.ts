export interface MembershipInput {
  firstName?: string
  lastName?: string
  email?: string
  address?: string
  zip?: string
  city?: string
  privacyAccept?: boolean
  [k: string]: any
}

export interface MembershipValidation {
  ok: boolean
  errors: Record<string, string>
}

export function validateMembership(data: MembershipInput): MembershipValidation {
  const errors: Record<string, string> = {}
  const requiredFields = ['firstName', 'lastName', 'email', 'address', 'zip', 'city'] as const

  for (const field of requiredFields) {
    if (typeof data[field] !== 'string' || !data[field].trim()) {
      errors[field] = 'Dieses Feld ist erforderlich.'
    }
  }

  if (data.privacyAccept !== true) {
    errors.privacyAccept = 'Bitte akzeptieren Sie die Datenschutzerklärung.'
  }

  if (typeof data.email === 'string' && data.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
    errors.email = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'
  }

  return { ok: Object.keys(errors).length === 0, errors }
}
