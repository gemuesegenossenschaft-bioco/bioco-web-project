'use client'

import { useState } from 'react'
import { trackEvent } from '../MatomoScript'

export function ContactForm() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
  })
  const [submitted, setSubmitted] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    trackEvent('Form', 'Contact', 'Submit')

    try {
      const response = await fetch('/api/forms/contact', {
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
        <p>Vielen Dank! Sie erhalten in Kürze eine Bestätigungs-E-Mail.</p>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="contact-form">
      {error && (
        <div className="form-error bento-card">
          <p>{error}</p>
        </div>
      )}

      <div className="form-group">
        <label htmlFor="contact_name">Name *</label>
        <input
          type="text"
          id="contact_name"
          name="name"
          required
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="contact_email">E-Mail *</label>
        <input
          type="email"
          id="contact_email"
          name="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="contact_phone">Telefon</label>
        <input
          type="tel"
          id="contact_phone"
          name="phone"
          value={formData.phone}
          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="contact_subject">Betreff *</label>
        <input
          type="text"
          id="contact_subject"
          name="subject"
          required
          value={formData.subject}
          onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
        />
      </div>

      <div className="form-group">
        <label htmlFor="contact_message">Nachricht *</label>
        <textarea
          id="contact_message"
          name="message"
          required
          rows={6}
          value={formData.message}
          onChange={(e) => setFormData({ ...formData, message: e.target.value })}
        />
      </div>

      <input type="submit" value="Absenden" className="btn btn-primary btn-organic" />
    </form>
  )
}
