'use client'

import { useState } from 'react'
import { trackEvent } from '../MatomoScript'
import { CaptchaField } from './CaptchaField'
import { Button } from '@/components/ui/Button'
import { FormField, TextInput, TextArea } from '@/components/ui/FormField'

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

      <FormField label="Name *" htmlFor="contact_name">
        <TextInput
          type="text"
          id="contact_name"
          name="name"
          required
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
        />
      </FormField>

      <FormField label="E-Mail *" htmlFor="contact_email">
        <TextInput
          type="email"
          id="contact_email"
          name="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        />
      </FormField>

      <FormField label="Telefon" htmlFor="contact_phone">
        <TextInput
          type="tel"
          id="contact_phone"
          name="phone"
          value={formData.phone}
          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        />
      </FormField>

      <FormField label="Betreff *" htmlFor="contact_subject">
        <TextInput
          type="text"
          id="contact_subject"
          name="subject"
          required
          value={formData.subject}
          onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
        />
      </FormField>

      <FormField label="Nachricht *" htmlFor="contact_message">
        <TextArea
          id="contact_message"
          name="message"
          required
          rows={6}
          value={formData.message}
          onChange={(e) => setFormData({ ...formData, message: e.target.value })}
        />
      </FormField>

      <CaptchaField
        token={captchaToken}
        onTokenChange={setCaptchaToken}
        resetKey={captchaResetKey}
      />

      <Button
        as="input"
        variant="primary"
        organic
        value={isSubmitting ? 'Wird gesendet...' : 'Absenden'}
        disabled={isSubmitting || !captchaToken}
      />
    </form>
  )
}
