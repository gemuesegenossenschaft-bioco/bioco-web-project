'use client'

import { useState } from 'react'
import { CTA } from './CTA'

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
    notes: ''
  })
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitStatus, setSubmitStatus] = useState<'idle' | 'success' | 'error'>('idle')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    setSubmitStatus('idle')

    try {
      const response = await fetch('/api/forms/event-signup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...formData, eventId, eventTitle }),
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
      } else {
        setSubmitStatus('success')
        if (onSuccess) {
          setTimeout(() => onSuccess(), 2000)
        }
      }
    } catch (error) {
      console.error('Event signup error:', error)
      setSubmitStatus('error')
    } finally {
      setIsSubmitting(false)
    }
  }

  if (submitStatus === 'success') {
    return (
      <div style={{ padding: '24px', textAlign: 'center' }}>
        <h3 style={{ color: 'var(--bioco-green)', marginBottom: '16px' }}>Anmeldung erfolgreich!</h3>
        <p>Vielen Dank für deine Anmeldung. Wir melden uns bei dir.</p>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} style={{ padding: '24px' }}>
      <h3 style={{ marginBottom: '16px' }}>Anmeldung für: {eventTitle}</h3>
      
      <div style={{ marginBottom: '16px' }}>
        <label htmlFor="name" style={{ display: 'block', marginBottom: '8px', fontWeight: 600 }}>
          Name *
        </label>
        <input
          type="text"
          id="name"
          required
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
          style={{
            width: '100%',
            padding: '12px',
            border: '1px solid var(--border-color)',
            borderRadius: '8px',
            fontSize: '1rem'
          }}
        />
      </div>

      <div style={{ marginBottom: '16px' }}>
        <label htmlFor="email" style={{ display: 'block', marginBottom: '8px', fontWeight: 600 }}>
          E-Mail *
        </label>
        <input
          type="email"
          id="email"
          required
          value={formData.email}
          onChange={(e) => setFormData({ ...formData, email: e.target.value })}
          style={{
            width: '100%',
            padding: '12px',
            border: '1px solid var(--border-color)',
            borderRadius: '8px',
            fontSize: '1rem'
          }}
        />
      </div>

      <div style={{ marginBottom: '16px' }}>
        <label htmlFor="phone" style={{ display: 'block', marginBottom: '8px', fontWeight: 600 }}>
          Telefon (optional)
        </label>
        <input
          type="tel"
          id="phone"
          value={formData.phone}
          onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
          style={{
            width: '100%',
            padding: '12px',
            border: '1px solid var(--border-color)',
            borderRadius: '8px',
            fontSize: '1rem'
          }}
        />
      </div>

      <div style={{ marginBottom: '24px' }}>
        <label htmlFor="notes" style={{ display: 'block', marginBottom: '8px', fontWeight: 600 }}>
          Bemerkungen (optional)
        </label>
        <textarea
          id="notes"
          rows={4}
          value={formData.notes}
          onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
          style={{
            width: '100%',
            padding: '12px',
            border: '1px solid var(--border-color)',
            borderRadius: '8px',
            fontSize: '1rem',
            fontFamily: 'inherit',
            resize: 'vertical'
          }}
        />
      </div>

      {submitStatus === 'error' && (
        <div style={{ 
          padding: '12px', 
          marginBottom: '16px', 
          background: 'var(--bioco-beet-50)', 
          color: 'var(--bioco-beet)',
          borderRadius: '8px'
        }}>
          Es ist ein Fehler aufgetreten. Bitte versuche es erneut.
        </div>
      )}

      <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="btn btn-secondary"
            disabled={isSubmitting}
          >
            Abbrechen
          </button>
        )}
        <button
          type="submit"
          className="btn btn-primary"
          disabled={isSubmitting}
        >
          {isSubmitting ? 'Wird gesendet...' : 'Anmelden'}
        </button>
      </div>
    </form>
  )
}



















