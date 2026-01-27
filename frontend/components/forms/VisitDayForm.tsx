'use client'

import { useState } from 'react'
import { trackEvent } from '../MatomoScript'

export function VisitDayForm() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    visit_date: '',
    participants: 1,
    notes: '',
    privacy_accept: false,
  })
  const [submitted, setSubmitted] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    trackEvent('Form', 'VisitDay', 'Submit')

    try {
      const response = await fetch('/api/forms/visit', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      })

      const data = await response.json()

      if (data.success) {
        setSubmitted(true)
      } else {
        setError(data.error || 'Es ist ein Fehler aufgetreten.')
      }
    } catch (err) {
      setError('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.')
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
    <form onSubmit={handleSubmit} className="visit-form">
      {error && (
        <div className="form-error bento-card">
          <p>{error}</p>
        </div>
      )}

      <div className="form-group">
        <label htmlFor="visit_name">Name *</label>
        <input
          type="text"
          id="visit_name"
          name="name"
          required
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="visit_email">E-Mail *</label>
        <input
          type="email"
          id="visit_email"
          name="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="visit_phone">Telefon *</label>
        <input
          type="tel"
          id="visit_phone"
          name="phone"
          required
          value={formData.phone}
          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="visit_date">Gewünschtes Datum *</label>
        <input
          type="date"
          id="visit_date"
          name="visit_date"
          required
          value={formData.visit_date}
          onChange={(e) => setFormData({ ...formData, visit_date: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="visit_participants">Anzahl Personen *</label>
        <input
          type="number"
          id="visit_participants"
          name="participants"
          min="1"
          required
          value={formData.participants}
          onChange={(e) => setFormData({ ...formData, participants: parseInt(e.target.value) })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="visit_notes">Anmerkungen</label>
        <textarea
          id="visit_notes"
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

      <input type="submit" value="Anmelden" className="cta-button" />
    </form>
  )
}
