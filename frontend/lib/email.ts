import { Resend } from 'resend'

// Email recipients for different form types
const FORM_RECIPIENTS: Record<string, string[]> = {
  contact: ['info@bioco.ch', 'medien@bioco.ch', 'intranet@bioco.ch'],
  subscribe: ['info@bioco.ch', 'medien@bioco.ch', 'intranet@bioco.ch'],
  visit: ['medien@bioco.ch'],
  'event-signup': ['medien@bioco.ch'], // Same as visit
  'waiting-list': ['info@bioco.ch', 'medien@bioco.ch', 'intranet@bioco.ch'],
  membership: ['info@bioco.ch', 'medien@bioco.ch', 'intranet@bioco.ch'],
}

const FROM_EMAIL = process.env.RESEND_FROM_EMAIL || 'noreply@bioco.ch'
const FROM_NAME = process.env.RESEND_FROM_NAME || 'biocò'

function getResendClient() {
  const apiKey = process.env.RESEND_API_KEY
  if (!apiKey) throw new Error('RESEND_API_KEY is not configured')
  return new Resend(apiKey)
}

interface FormSubmission {
  formType: keyof typeof FORM_RECIPIENTS
  data: Record<string, any>
  subject?: string
}

export async function sendFormEmail({ formType, data, subject }: FormSubmission) {
  const resend = getResendClient()

  const recipients = FORM_RECIPIENTS[formType]
  if (!recipients || recipients.length === 0) {
    throw new Error(`No recipient configured for form type: ${formType}`)
  }

  const emailSubject = subject || getDefaultSubject(formType)
  const htmlContent = formatFormEmail(formType, data)

  try {
    const result = await resend.emails.send({
      from: `${FROM_NAME} <${FROM_EMAIL}>`,
      to: recipients,
      subject: emailSubject,
      html: htmlContent,
      reply_to: data.email || FROM_EMAIL,
    })

    if (result.error) {
      console.error('Resend API error:', result.error)
      throw new Error(result.error.message || 'Failed to send email via Resend')
    }

    return { success: true, id: result.data?.id }
  } catch (error: any) {
    console.error('Resend email error:', error)
    // Re-throw with more context
    if (error?.message) {
      throw error
    }
    throw new Error(error?.message || 'Failed to send email')
  }
}

function getDefaultSubject(formType: string): string {
  const subjects: Record<string, string> = {
    contact: 'Neue Kontaktanfrage',
    subscribe: 'Neue Newsletter-Anmeldung',
    visit: 'Neue Anmeldung Schnuppertag/Tag der offenen Tür',
    'event-signup': 'Neue Event-Anmeldung',
    'waiting-list': 'Neue Anmeldung Warteliste',
    membership: 'Neue Mitgliedschaftsanmeldung',
  }
  return subjects[formType] || 'Neue Formularanfrage'
}

function formatFormEmail(formType: string, data: Record<string, any>): string {
  // For membership form, include all fields in detail
  if (formType === 'membership') {
    return formatMembershipEmail(data)
  }

  const fields = Object.entries(data)
    .filter(([key]) => key !== 'privacy_accept' && key !== 'commitmentAccepted')
    .map(([key, value]) => {
      const label = formatLabel(key)
      return `<tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee; width: 200px;">${label}:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${formatValue(value)}</td></tr>`
    })
    .join('')

  return `
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        </style>
      </head>
      <body>
        <h2>Neue Formularanfrage: ${getDefaultSubject(formType)}</h2>
        <table>
          ${fields}
        </table>
        <p style="margin-top: 20px; font-size: 12px; color: #666;">
          Diese E-Mail wurde automatisch über das Kontaktformular auf bioco.ch gesendet.
        </p>
      </body>
    </html>
  `
}

function formatMembershipEmail(data: Record<string, any>): string {
  const sections: string[] = []

  // Personal Information
  sections.push(`
    <h3 style="margin-top: 20px; color: #2e7d32;">Persönliche Daten</h3>
    <table>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee; width: 200px;">Vorname:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.firstName || '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Nachname:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.lastName || '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Adresse:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.address || '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">PLZ:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.zip || '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Ort:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.city || '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Telefon:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.phone || '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">E-Mail:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.email || '-'}</td></tr>
    </table>
  `)

  // Membership & Abo
  sections.push(`
    <h3 style="margin-top: 20px; color: #2e7d32;">Mitgliedschaft & Gemüsekorb</h3>
    <table>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee; width: 200px;">Mitgliedschaftstyp:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.membershipType === 'abo' ? 'Mit Gemüsekorb' : 'Nur Anteilsscheine'}</td></tr>
      ${data.membershipType === 'abo' ? `
        <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Gemüsekorb:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${formatAboType(data.aboType)}</td></tr>
        <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Zusätzliche Anteilsscheine:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.additionalShares || 0}</td></tr>
      ` : `
        <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Anteilsscheine (ohne Gemüsekorb):</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.sharesOnly || 0}</td></tr>
      `}
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Depot:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.depot || '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Zahlungsweise:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.paymentType === 'quarterly' ? 'Quartalsweise' : 'Ganzes Jahr'}</td></tr>
    </table>
  `)

  // Mitarbeit
  sections.push(`
    <h3 style="margin-top: 20px; color: #2e7d32;">Mitarbeit</h3>
    <table>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee; width: 200px;">Bevorzugte Tage:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${Array.isArray(data.preferredDays) ? data.preferredDays.join(', ') : '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Bevorzugte Zeiten:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${Array.isArray(data.preferredTimes) ? data.preferredTimes.join(', ') : '-'}</td></tr>
      <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Tätigkeitsbereiche:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${Array.isArray(data.activityAreas) ? data.activityAreas.join(', ') : '-'}</td></tr>
      ${data.otherActivity ? `<tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Weitere Tätigkeiten:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.otherActivity}</td></tr>` : ''}
    </table>
  `)

  // Zusatzabos
  if (Array.isArray(data.zusatzabos) && data.zusatzabos.length > 0 || data.weitereProdukte) {
    sections.push(`
      <h3 style="margin-top: 20px; color: #2e7d32;">Zusatzabos & Weitere Produkte</h3>
      <table>
        ${Array.isArray(data.zusatzabos) && data.zusatzabos.length > 0 ? `<tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee; width: 200px;">Interesse an:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.zusatzabos.join(', ')}</td></tr>` : ''}
        ${data.weitereProdukte ? `<tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">Weitere Produkte:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${data.weitereProdukte}</td></tr>` : ''}
      </table>
    `)
  }

  // Commitment
  if (Array.isArray(data.commitmentAccepted)) {
    const commitments = [
      'Ich verstehe, dass ich regelmässig auf dem Hof mitarbeiten muss.',
      'Ich bin bereit, die finanziellen Verpflichtungen zu übernehmen.',
      'Ich akzeptiere die Genossenschaftsstatuten.',
      'Ich bin bereit, Teil der Gemeinschaft zu sein.',
    ]
    sections.push(`
      <h3 style="margin-top: 20px; color: #2e7d32;">Verpflichtungen</h3>
      <table>
        ${data.commitmentAccepted.map((accepted: boolean, index: number) => `
          <tr><td style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee; width: 200px;">${commitments[index] || `Verpflichtung ${index + 1}`}:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">${accepted ? '✓ Akzeptiert' : '✗ Nicht akzeptiert'}</td></tr>
        `).join('')}
      </table>
    `)
  }

  return `
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          body { font family: Arial, sans-serif; line-height: 1.6; color: #333; }
          table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        </style>
      </head>
      <body>
        <h2>Neue Mitgliedschaftsanmeldung</h2>
        ${sections.join('')}
        <p style="margin-top: 20px; font-size: 12px; color: #666;">
          Diese E-Mail wurde automatisch über das Anmeldeformular auf bioco.ch gesendet.
        </p>
      </body>
    </html>
  `
}

function formatAboType(aboType: string): string {
  const types: Record<string, string> = {
    halb: 'Halb (1 Person, CHF 750.-, 1 Anteil)',
    standard: 'Standard (2-3 Personen, CHF 1\'280.-, 2 Anteile)',
    doppel: 'Doppel (4-6 Personen, CHF 2\'350.-, 4 Anteile)',
    none: 'Kein Gemüsekorb',
  }
  return types[aboType] || aboType
}

function formatLabel(key: string): string {
  const labels: Record<string, string> = {
    name: 'Name',
    email: 'E-Mail',
    phone: 'Telefon',
    subject: 'Betreff',
    message: 'Nachricht',
    visit_date: 'Gewünschtes Datum',
    participants: 'Anzahl Personen',
    notes: 'Anmerkungen',
    interest: 'Interesse',
    eventTitle: 'Event',
    eventId: 'Event ID',
  }
  return labels[key] || key.charAt(0).toUpperCase() + key.slice(1)
}

function formatValue(value: any): string {
  if (value === null || value === undefined) return '-'
  if (typeof value === 'boolean') return value ? 'Ja' : 'Nein'
  if (Array.isArray(value)) return value.join(', ')
  if (typeof value === 'object') return JSON.stringify(value, null, 2)
  return String(value)
}
