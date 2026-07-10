'use client'

import { useState } from 'react'
import { trackEvent } from '../MatomoScript'
import { Button } from '@/components/ui/Button'
import { FormField, TextInput, Checkbox } from '@/components/ui/FormField'

export function SubscribeForm() {
  const [formData, setFormData] = useState({
    email: '',
    name: '',
    privacy_accept: false,
  })
  const [submitted, setSubmitted] = useState(false)
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    setIsSubmitting(true)
    trackEvent('Form', 'Subscribe', 'Submit')

    try {
      const response = await fetch('/api/forms/subscribe', {
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
    } finally {
      setIsSubmitting(false)
    }
  }

  if (submitted) {
    return (
      <div className="form-success bento-card">
        <p>Vielen Dank! Bitte bestätigen Sie Ihre Anmeldung über den Link in der E-Mail, die wir Ihnen gesendet haben.</p>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="subscribe-form">
      {error && (
        <div className="form-error bento-card">
          <p>{error}</p>
        </div>
      )}

      <FormField label="E-Mail-Adresse *" htmlFor="subscribe_email">
        <TextInput
          type="email"
          id="subscribe_email"
          name="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        />
      </FormField>

      <FormField label="Name (optional)" htmlFor="subscribe_name">
        <TextInput
          type="text"
          id="subscribe_name"
          name="name"
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
        />
      </FormField>

      <Checkbox
        name="privacy_accept"
        required
        checked={formData.privacy_accept}
        onChange={(e) => setFormData({ ...formData, privacy_accept: e.target.checked })}
        label="Ich akzeptiere die Datenschutzbestimmungen *"
      />

      <Button
        as="input"
        variant="primary"
        value={isSubmitting ? 'Wird gesendet...' : 'Abonnieren'}
        disabled={isSubmitting}
      />
    </form>
  )
}
