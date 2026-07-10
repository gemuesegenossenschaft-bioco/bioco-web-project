'use client'

import { useState } from 'react'
import { CaptchaField } from './forms/CaptchaField'
import { Button } from '@/components/ui/Button'
import { FormField, TextInput, TextArea } from '@/components/ui/FormField'

interface EventSignupFormProps {
  eventTitle: string
  eventId?: string | number
  onSuccess?: () => void
  onCancel?: () => void
}

export function EventSignupForm({ eventTitle, eventId, onSuccess, onCancel }: EventSignupFormProps) {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    notes: '',
  })
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitStatus, setSubmitStatus] = useState<'idle' | 'success' | 'error'>('idle')
  const [captchaToken, setCaptchaToken] = useState('')
  const [captchaResetKey, setCaptchaResetKey] = useState(0)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!captchaToken) {
      setSubmitStatus('error')
      return
    }

    setIsSubmitting(true)
    setSubmitStatus('idle')

    try {
      const response = await fetch('/api/forms/event-signup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...formData, eventId, eventTitle, captchaToken }),
      })

      let data
      try {
        data = await response.json()
      } catch (parseError) {
        data = { success: false, error: `Server error: ${response.status} ${response.statusText}` }
      }

      if (!response.ok || !data.success) {
        setSubmitStatus('error')
        console.error('Event signup error:', data.error || `HTTP error! status: ${response.status}`)
        setCaptchaToken('')
        setCaptchaResetKey((prev) => prev + 1)
      } else {
        setSubmitStatus('success')
        if (onSuccess) {
          setTimeout(() => onSuccess(), 2000)
        }
      }
    } catch (error) {
      console.error('Event signup error:', error)
      setSubmitStatus('error')
      setCaptchaToken('')
      setCaptchaResetKey((prev) => prev + 1)
    } finally {
      setIsSubmitting(false)
    }
  }

  if (submitStatus === 'success') {
    return (
      <div style={{ padding: 'var(--space-5)', textAlign: 'center' }}>
        <h3 style={{ color: 'var(--bioco-green)', marginBottom: 'var(--space-4)' }}>Anmeldung erfolgreich!</h3>
        <p>Vielen Dank für deine Anmeldung. Wir melden uns bei dir.</p>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} style={{ padding: 'var(--space-5)' }}>
      <h3 style={{ marginBottom: 'var(--space-4)' }}>Anmeldung für: {eventTitle}</h3>

      <FormField variant="inline" label="Name *" htmlFor="name">
        <TextInput
          variant="compact"
          type="text"
          id="name"
          required
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
        />
      </FormField>

      <FormField variant="inline" label="E-Mail *" htmlFor="email">
        <TextInput
          variant="compact"
          type="email"
          id="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        />
      </FormField>

      <FormField variant="inline" label="Telefon (optional)" htmlFor="phone">
        <TextInput
          variant="compact"
          type="tel"
          id="phone"
          value={formData.phone}
          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        />
      </FormField>

      <FormField variant="inline" spacing="var(--space-5)" label="Bemerkungen (optional)" htmlFor="notes">
        <TextArea
          variant="compact"
          id="notes"
          rows={4}
          value={formData.notes}
          onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
        />
      </FormField>

      <CaptchaField token={captchaToken} onTokenChange={setCaptchaToken} resetKey={captchaResetKey} />

      {submitStatus === 'error' && (
        <div
          style={{
            padding: 'var(--space-3)',
            marginBottom: 'var(--space-4)',
            background: 'var(--bioco-beet-50)',
            color: 'var(--bioco-beet)',
            borderRadius: '8px',
          }}
        >
          Die Anmeldung konnte nicht gesendet werden. Bitte versuche es erneut oder kontaktiere uns direkt.
        </div>
      )}

      <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
        {onCancel && (
          <Button type="button" onClick={onCancel} variant="secondary" disabled={isSubmitting}>
            Abbrechen
          </Button>
        )}
        <Button type="submit" variant="primary" disabled={isSubmitting || !captchaToken}>
          {isSubmitting ? 'Wird gesendet...' : 'Anmelden'}
        </Button>
      </div>
    </form>
  )
}
