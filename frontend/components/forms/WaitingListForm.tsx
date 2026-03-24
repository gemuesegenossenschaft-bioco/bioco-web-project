'use client'

import { useState } from 'react'
import { trackEvent } from '../MatomoScript'
import { CaptchaField } from './CaptchaField'

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

      <div className="form-group">
        <label htmlFor="waiting_name">Name *</label>
        <input
          type="text"
          id="waiting_name"
          name="name"
          required
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="waiting_email">E-Mail *</label>
        <input
          type="email"
          id="waiting_email"
          name="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="waiting_phone">Telefon *</label>
        <input
          type="tel"
          id="waiting_phone"
          name="phone"
          required
          value={formData.phone}
          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="waiting_interest">Interesse an *</label>
        <select
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
        </select>
      </div>

      <div className="form-group">
        <label htmlFor="waiting_notes">Anmerkungen</label>
        <textarea
          id="waiting_notes"
          name="notes"
          rows={4}
          value={formData.notes}
          onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label>
          <input
            type="checkbox"
            name="privacy_accept"
            required
            checked={formData.privacy_accept}
            onChange={(e) => setFormData({ ...formData, privacy_accept: e.target.checked })}
          />
          Ich akzeptiere die Datenschutzbestimmungen *
        </label>
      </div>

      <CaptchaField
        token={captchaToken}
        onTokenChange={setCaptchaToken}
        resetKey={captchaResetKey}
      />

      <input
        type="submit"
        value={isSubmitting ? 'Wird gesendet...' : 'Anmelden'}
        className="cta-button"
        disabled={isSubmitting || !captchaToken}
      />
    </form>
  )
}
