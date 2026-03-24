'use client'

import { useState } from 'react'
import { trackEvent } from '../MatomoScript'
import { CaptchaField } from './CaptchaField'

export function ContactForm() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
  })
  const [submitted, setSubmitted] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
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
    trackEvent('Form', 'Contact', 'Submit')

    try {
      const response = await fetch('/api/forms/contact', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ...formData, captchaToken }),
      })

      let data
      try {
        data = await response.json()
      } catch (parseError) {
        data = { success: false, error: `Server error: ${response.status} ${response.statusText}` }
      }

      if (!response.ok || !data.success) {
        const errorMessage = data.error || 'Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es erneut oder senden Sie uns eine E-Mail direkt an info@bioco.ch'
        setError(errorMessage)
        setCaptchaToken('')
        setCaptchaResetKey((prev) => prev + 1)
      } else {
        setSubmitted(true)
      }
    } catch (err: any) {
      console.error('Contact form error:', err)
      setError('Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es erneut oder senden Sie uns eine E-Mail direkt an info@bioco.ch')
      setCaptchaToken('')
      setCaptchaResetKey((prev) => prev + 1)
    } finally {
      setIsSubmitting(false)
    }
  }

  if (submitted) {
    return (
      <div className="form-success bento-card">
        <p>Vielen Dank für Ihre Nachricht! Wir melden uns so schnell wie möglich bei Ihnen.</p>
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

      <CaptchaField
        token={captchaToken}
        onTokenChange={setCaptchaToken}
        resetKey={captchaResetKey}
      />

      <input
        type="submit"
        value={isSubmitting ? 'Wird gesendet...' : 'Absenden'}
        className="btn btn-primary btn-organic"
        disabled={isSubmitting || !captchaToken}
      />
    </form>
  )
}
