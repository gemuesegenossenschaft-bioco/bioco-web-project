'use client'

import { useState } from 'react'
import { trackEvent } from '../MatomoScript'
import { CaptchaField } from './CaptchaField'
import { Button } from '@/components/ui/Button'
import { FormField, TextInput, TextArea, SelectField, Checkbox } from '@/components/ui/FormField'

export function WaitingListForm() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    interest: '',
    notes: '',
    privacy_accept: false,
  })
  const [submitted, setSubmitted] = useState(false)
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [captchaToken, setCaptchaToken] = useState('')
  const [captchaResetKey, setCaptchaResetKey] = useState(0)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    if (!captchaToken) {
      setError('Bitte bestätigen Sie, dass Sie kein Roboter sind.')
      return
    }

    setIsSubmitting(true)
    trackEvent('Form', 'WaitingList', 'Submit')

    try {
      const response = await fetch('/api/forms/waiting-list', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ...formData, captchaToken }),
      })

      const data = await response.json()

      if (data.success) {
        setSubmitted(true)
      } else {
        setError(data.error || 'Es ist ein Fehler aufgetreten.')
        setCaptchaToken('')
        setCaptchaResetKey((prev) => prev + 1)
      }
    } catch (err) {
      setError('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.')
      setCaptchaToken('')
      setCaptchaResetKey((prev) => prev + 1)
    } finally {
      setIsSubmitting(false)
    }
  }

  if (submitted) {
    return (
      <div className="form-success bento-card">
        <p>Vielen Dank für Ihre Anmeldung! Bitte bestätigen Sie Ihre Anmeldung über den Link in der E-Mail, die wir Ihnen gesendet haben.</p>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="waiting-list-form">
      {error && (
        <div className="form-error bento-card">
          <p>{error}</p>
        </div>
      )}

      <FormField label="Name *" htmlFor="waiting_name">
        <TextInput
          type="text"
          id="waiting_name"
          name="name"
          required
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
        />
      </FormField>

      <FormField label="E-Mail *" htmlFor="waiting_email">
        <TextInput
          type="email"
          id="waiting_email"
          name="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        />
      </FormField>

      <FormField label="Telefon *" htmlFor="waiting_phone">
        <TextInput
          type="tel"
          id="waiting_phone"
          name="phone"
          required
          value={formData.phone}
          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        />
      </FormField>

      <FormField label="Interesse an *" htmlFor="waiting_interest">
        <SelectField
          id="waiting_interest"
          name="interest"
          required
          value={formData.interest}
          onChange={(e) => setFormData({ ...formData, interest: e.target.value })}
        >
          <option value="">Bitte wählen...</option>
          <option value="program1">Programm 1</option>
          <option value="program2">Programm 2</option>
          <option value="program3">Programm 3</option>
        </SelectField>
      </FormField>

      <FormField label="Anmerkungen" htmlFor="waiting_notes">
        <TextArea
          id="waiting_notes"
          name="notes"
          rows={4}
          value={formData.notes}
          onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
        />
      </FormField>

      <Checkbox
        name="privacy_accept"
        required
        checked={formData.privacy_accept}
        onChange={(e) => setFormData({ ...formData, privacy_accept: e.target.checked })}
        label="Ich akzeptiere die Datenschutzbestimmungen *"
      />

      <CaptchaField
        token={captchaToken}
        onTokenChange={setCaptchaToken}
        resetKey={captchaResetKey}
      />

      <Button
        as="input"
        variant="primary"
        value={isSubmitting ? 'Wird gesendet...' : 'Anmelden'}
        disabled={isSubmitting || !captchaToken}
      />
    </form>
  )
}
