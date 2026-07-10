'use client'

import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { trackEvent } from '../MatomoScript'
import { InfoTooltip } from '../InfoTooltip'
import Link from 'next/link'
import { Button } from '@/components/ui/Button'
import { FormField, TextInput, SelectField } from '@/components/ui/FormField'
import { Card } from '@/components/ui/Card'

type AboType = 'halb' | 'standard' | 'doppel' | 'none'
type PaymentType = 'quarterly' | 'yearly'

interface FormData {
  // Step 1: Personal
  firstName: string
  lastName: string
  address: string
  zip: string
  city: string
  phone: string
  email: string
  
  // Step 2: Membership & Abo
  membershipType: 'abo' | 'shares-only'
  aboType: AboType
  additionalShares: number
  sharesOnly: number
  
  // Step 3: Depot
  depot: string
  
  // Step 4: Payment
  paymentType: PaymentType
  
  // Step 3: Mitarbeit
  preferredDays: string[]
  preferredTimes: string[]
  activityAreas: string[]
  otherActivity: string
  
  // Step 4: Zusatzabos
  zusatzabos: string[]
  weitereProdukte: string
  
  // Step 0: Commitment Check
  commitmentAccepted: boolean[]
  
  // General
  privacyAccept: boolean
}

const ABO_CONFIG = {
  halb: { price: 750, shares: 1, people: '1 Person', hours: 20 },
  standard: { price: 1280, shares: 2, people: '2-3 Personen', hours: 40 },
  doppel: { price: 2350, shares: 4, people: '4-6 Personen', hours: 40 },
}

const DEPOTS = [
  'Depot Chrättli',
  'Depot Ohne',
  'Depot Anixis',
  'Casa Flora',
  'Depot Geisshof',
  'Depot Kupperhaus',
  'Depot Ennetbaden',
  'Depot Lemonia',
  'Depot Lägernstrasse'
]
const DAYS = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
const TIMES = ['morgens', 'nachmittags', 'abends']
const ACTIVITY_AREAS = [
  'Feld/Anbau',
  'Logistik/Verteilung',
  'Administration',
  'Events/Organisation',
  'Andere'
]

interface MembershipFormProps {
  initialData?: {
    aboType?: string
    additionalShares?: number
    membershipType?: string
  }
}

export function MembershipForm({ initialData }: MembershipFormProps) {
  const router = useRouter()
  const [currentStep, setCurrentStep] = useState(0)
  const [submitted, setSubmitted] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  
  // Read URL parameters
  const getInitialDataFromURL = () => {
    if (typeof window === 'undefined') {
      // Server-side: return default values
      return {
        aboType: 'standard' as AboType,
        additionalShares: 0,
        membershipType: 'abo' as 'abo' | 'shares-only',
      }
    }
    const params = new URLSearchParams(window.location.search)
    const abo = params.get('abo')
    const shares = params.get('shares')
    const additional = params.get('additional')
    
    // Always return default values (standard is pre-selected)
    return {
      aboType: (abo || 'standard') as AboType,
      additionalShares: additional ? parseInt(additional) : 0,
      membershipType: (abo === 'kein' ? 'shares-only' : 'abo') as 'abo' | 'shares-only',
    }
  }

  // Read the URL params exactly once (lazy init): a fresh object on every
  // render would retrigger the initialData effect below indefinitely
  // (setFormData -> re-render -> new reference -> effect -> ...).
  const [urlData] = useState(getInitialDataFromURL)
  const effectiveInitialData = initialData || urlData

  const [formData, setFormData] = useState<FormData>({
    firstName: '',
    lastName: '',
    address: '',
    zip: '',
    city: '',
    phone: '',
    email: '',
    membershipType: 'abo', // Always abo, no choice
    aboType: 'standard', // Always standard, pre-selected
    additionalShares: 0, // Always 0, no choice
    sharesOnly: 1,
    depot: '',
    paymentType: 'yearly',
    preferredDays: [],
    preferredTimes: [],
    activityAreas: [],
    otherActivity: '',
    zusatzabos: [],
    weitereProdukte: '',
    commitmentAccepted: [false, false, false, false], // 4 commitment items
    privacyAccept: false,
  })

  // Update form data when initialData changes or URL params are present
  useEffect(() => {
    if (effectiveInitialData) {
      setFormData(prev => ({
        ...prev,
        membershipType: (effectiveInitialData.membershipType as 'abo' | 'shares-only') || 'abo',
        aboType: (effectiveInitialData.aboType as AboType) || 'standard',
        additionalShares: effectiveInitialData.additionalShares ?? 0,
      }))
    }
  }, [effectiveInitialData])

  // Calculate totals
  const calculateTotals = () => {
    let aboPrice = 0
    let requiredShares = 0
    let totalShares = 0
    let sharesPrice = 0

    if (formData.membershipType === 'abo' && formData.aboType !== 'none') {
      const config = ABO_CONFIG[formData.aboType]
      aboPrice = config.price
      requiredShares = config.shares
      totalShares = requiredShares + formData.additionalShares
    } else if (formData.membershipType === 'shares-only') {
      totalShares = formData.sharesOnly
    }

    sharesPrice = totalShares * 250
    const totalPrice = aboPrice + sharesPrice

    return {
      aboPrice,
      requiredShares,
      totalShares,
      sharesPrice,
      totalPrice,
    }
  }

  const totals = calculateTotals()

  const validateStep = (step: number): boolean => {
    const errors: Record<string, string> = {}
    let isValid = true

    switch (step) {
      case 0:
        // Commitment check - all items must be checked
        if (formData.commitmentAccepted.some(accepted => !accepted)) {
          errors.commitment = 'Bitte bestätige alle Punkte, bevor du fortfährst.'
          isValid = false
        }
        break
      case 1:
        if (!formData.firstName) {
          errors.firstName = 'Bitte geben Sie Ihren Vornamen ein.'
          isValid = false
        }
        if (!formData.lastName) {
          errors.lastName = 'Bitte geben Sie Ihren Nachnamen ein.'
          isValid = false
        }
        if (!formData.address) {
          errors.address = 'Bitte geben Sie Ihre Adresse ein.'
          isValid = false
        }
        if (!formData.zip) {
          errors.zip = 'Bitte geben Sie Ihre PLZ ein.'
          isValid = false
        }
        if (!formData.city) {
          errors.city = 'Bitte geben Sie Ihren Ort ein.'
          isValid = false
        }
        if (!formData.email) {
          errors.email = 'Bitte geben Sie Ihre E-Mail-Adresse ein.'
          isValid = false
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
          errors.email = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'
          isValid = false
        }
        break
      case 2:
        if (!formData.depot) {
          errors.depot = 'Bitte wählen Sie ein Depot für die Abholung.'
          isValid = false
        }
        if (!formData.paymentType) {
          errors.paymentType = 'Bitte wählen Sie eine Zahlungsweise.'
          isValid = false
        }
        break
      case 3:
        if (formData.preferredDays.length === 0) {
          errors.preferredDays = 'Bitte wählen Sie mindestens einen bevorzugten Tag.'
          isValid = false
        }
        if (formData.preferredTimes.length === 0) {
          errors.preferredTimes = 'Bitte wählen Sie mindestens eine bevorzugte Zeit.'
          isValid = false
        }
        if (formData.activityAreas.length === 0) {
          errors.activityAreas = 'Bitte wählen Sie mindestens einen Tätigkeitsbereich.'
          isValid = false
        }
        break
      case 4:
        // Zusatzabos - no validation needed, optional
        break
      case 5:
        if (!formData.privacyAccept) {
          errors.privacyAccept = 'Bitte akzeptieren Sie die Datenschutzbestimmungen.'
          isValid = false
        }
        break
      default:
        break
    }

    setFieldErrors(errors)
    return isValid
  }

  const handleNext = () => {
    if (validateStep(currentStep)) {
      setFieldErrors({})
      setCurrentStep(prev => Math.min(prev + 1, 6))
    }
  }

  const handlePrevious = () => {
    setFieldErrors({})
    setCurrentStep(prev => Math.max(prev - 1, 0))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!validateStep(5)) return

    setIsSubmitting(true)
    setFieldErrors({}) // Clear previous errors

    trackEvent('Form', 'Membership', 'Submit')

    try {
      const response = await fetch('/api/forms/membership', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      })

      let data
      try {
        data = await response.json()
      } catch (parseError) {
        // If JSON parsing fails, create a basic error response
        data = { success: false, error: `Server error: ${response.status} ${response.statusText}` }
      }

      if (!response.ok || !data.success) {
        // Extract error message from response
        const errorMessage = data.error || `HTTP error! status: ${response.status}`
        setFieldErrors({ submit: errorMessage })
        setIsSubmitting(false)
        return
      }

      // Success - redirect to thank you page
      console.log('Submission successful, redirecting...')
      setSubmitted(true)
      setIsSubmitting(false) // Reset submitting state before redirect
      // Use window.location for more reliable redirect
      window.location.href = '/anmeldung/danke'
    } catch (err: any) {
      console.error('Form submission error:', err)
      const errorMessage = err?.message || 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.'
      setFieldErrors({ submit: errorMessage })
      setIsSubmitting(false)
    }
  }

  return (
    <div className="membership-form-container">
      {/* Progress Bar */}
      <div className="form-progress">
        <div className="progress-bar">
          <div 
            className="progress-fill" 
            style={{ width: `${(currentStep / 6) * 100}%` }}
          ></div>
        </div>
        <p className="progress-text">Schritt {currentStep + 1} von 6</p>
      </div>

      <div className="form-layout">
        {/* Main Form */}
        <div className="form-main">
          <form onSubmit={handleSubmit} className="membership-form">
            {fieldErrors.submit && (
              <div className="form-error bento-card" style={{ marginBottom: '16px' }}>
                <p>{fieldErrors.submit}</p>
              </div>
            )}

            {/* Step 0: Commitment Check */}
            {currentStep === 0 && (
              <div className="form-step">
                <h3>Lies das und bestätige bevor du weiterklickst</h3>
                <p>Bevor du dich anmeldest, überprüfe bitte diese Punkte:</p>
                {fieldErrors.commitment && (
                  <div className="invalid-feedback" style={{ marginBottom: '16px', fontSize: '1rem' }}>{fieldErrors.commitment}</div>
                )}
                <div className={`commitment-checklist ${fieldErrors.commitment ? 'is-invalid-group' : ''}`}>
                  <label className="commitment-item">
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                      <input
                        type="checkbox"
                        checked={formData.commitmentAccepted[0]}
                        onChange={(e) => {
                          const newAccepted = [...formData.commitmentAccepted]
                          newAccepted[0] = e.target.checked
                          setFormData({ ...formData, commitmentAccepted: newAccepted })
                        }}
                        style={{ marginTop: '4px', flexShrink: 0 }}
                      />
                      <div style={{ flex: 1 }}>
                        <h4>Anteile & Beitrag</h4>
                        <p>Jedes Mitglied erwirbt <strong>Anteilsscheine zu je CHF 250.- (einmalige Zahlung)</strong>. Die Anzahl der Anteile hängt von deinem gewählten Abo ab. Der <strong>Jahresbeitrag für dein Gemüse-Abo (jährlicher Beitrag) wird per 31. Januar fällig</strong> und kann quartalsweise oder jährlich bezahlt werden.</p>
                      </div>
                    </div>
                  </label>
                  <label className="commitment-item">
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                      <input
                        type="checkbox"
                        checked={formData.commitmentAccepted[1]}
                        onChange={(e) => {
                          const newAccepted = [...formData.commitmentAccepted]
                          newAccepted[1] = e.target.checked
                          setFormData({ ...formData, commitmentAccepted: newAccepted })
                        }}
                        style={{ marginTop: '4px', flexShrink: 0 }}
                      />
                      <div style={{ flex: 1 }}>
                        <h4>Bindung & Kündigung</h4>
                        <p>Das Gemüseabo läuft <strong>vom 1. Januar bis zum 31. Dezember</strong>. Ohne Kündigung verlängert es sich jeweils um ein Kalenderjahr. Die <strong>Kündigungsfrist beträgt zwei Monate auf Ende eines Kalenderjahres</strong>.</p>
                      </div>
                    </div>
                  </label>
                  <label className="commitment-item">
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                      <input
                        type="checkbox"
                        checked={formData.commitmentAccepted[2]}
                        onChange={(e) => {
                          const newAccepted = [...formData.commitmentAccepted]
                          newAccepted[2] = e.target.checked
                          setFormData({ ...formData, commitmentAccepted: newAccepted })
                        }}
                        style={{ marginTop: '4px', flexShrink: 0 }}
                      />
                      <div style={{ flex: 1 }}>
                        <h4>Mitarbeit</h4>
                        <p>Wir sind eine Mitmach-Genossenschaft! Jedes Mitglied leistet pro Jahr <strong>10 Arbeitseinsätze à 2 Stunden (bei halbem Korb) bzw. 20 Arbeitseinsätze à 2 Stunden (bei ganzem Korb) Mitarbeit</strong>. Dies kann auf dem Feld, in der Logistik oder bei Events sein.</p>
                      </div>
                    </div>
                  </label>
                  <label className="commitment-item">
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                      <input
                        type="checkbox"
                        checked={formData.commitmentAccepted[3]}
                        onChange={(e) => {
                          const newAccepted = [...formData.commitmentAccepted]
                          newAccepted[3] = e.target.checked
                          setFormData({ ...formData, commitmentAccepted: newAccepted })
                        }}
                        style={{ marginTop: '4px', flexShrink: 0 }}
                      />
                      <div style={{ flex: 1 }}>
                        <h4>Wetterbedingte Ertragsschwankungen</h4>
                        <p>Mir ist bewusst, dass es zu Ernteausfällen kommen kann und mein wöchentlicher Gemüsekorb nicht immer gleich voll sein kann.</p>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
            )}

            {/* Step 1: Personal Data */}
            {currentStep === 1 && (
              <div className="form-step">
                <h3>Persönliche Daten</h3>
                <div className="form-row">
                  <FormField label="Vorname *" htmlFor="firstName" error={fieldErrors.firstName}>
                    <TextInput
                      variant="bootstrap"
                      invalid={!!fieldErrors.firstName}
                      type="text"
                      id="firstName"
                      required
                      value={formData.firstName}
                      onChange={(e) => {
                        setFormData({ ...formData, firstName: e.target.value })
                        if (fieldErrors.firstName) {
                          const newErrors = { ...fieldErrors }
                          delete newErrors.firstName
                          setFieldErrors(newErrors)
                        }
                      }}
                    />
                  </FormField>
                  <FormField label="Name *" htmlFor="lastName" error={fieldErrors.lastName}>
                    <TextInput
                      variant="bootstrap"
                      invalid={!!fieldErrors.lastName}
                      type="text"
                      id="lastName"
                      required
                      value={formData.lastName}
                      onChange={(e) => {
                        setFormData({ ...formData, lastName: e.target.value })
                        if (fieldErrors.lastName) {
                          const newErrors = { ...fieldErrors }
                          delete newErrors.lastName
                          setFieldErrors(newErrors)
                        }
                      }}
                    />
                  </FormField>
                </div>
                <FormField label="Adresse *" htmlFor="address" error={fieldErrors.address}>
                  <TextInput
                    variant="bootstrap"
                    invalid={!!fieldErrors.address}
                    type="text"
                    id="address"
                    required
                    value={formData.address}
                    onChange={(e) => {
                      setFormData({ ...formData, address: e.target.value })
                      if (fieldErrors.address) {
                        const newErrors = { ...fieldErrors }
                        delete newErrors.address
                        setFieldErrors(newErrors)
                      }
                    }}
                  />
                </FormField>
                <div className="form-row">
                  <FormField label="PLZ *" htmlFor="zip" error={fieldErrors.zip}>
                    <TextInput
                      variant="bootstrap"
                      invalid={!!fieldErrors.zip}
                      type="text"
                      id="zip"
                      required
                      value={formData.zip}
                      onChange={(e) => {
                        setFormData({ ...formData, zip: e.target.value })
                        if (fieldErrors.zip) {
                          const newErrors = { ...fieldErrors }
                          delete newErrors.zip
                          setFieldErrors(newErrors)
                        }
                      }}
                    />
                  </FormField>
                  <FormField label="Ort *" htmlFor="city" error={fieldErrors.city}>
                    <TextInput
                      variant="bootstrap"
                      invalid={!!fieldErrors.city}
                      type="text"
                      id="city"
                      required
                      value={formData.city}
                      onChange={(e) => {
                        setFormData({ ...formData, city: e.target.value })
                        if (fieldErrors.city) {
                          const newErrors = { ...fieldErrors }
                          delete newErrors.city
                          setFieldErrors(newErrors)
                        }
                      }}
                    />
                  </FormField>
                </div>
                <div className="form-row">
                  <FormField label="Telefon" htmlFor="phone">
                    <TextInput
                      variant="bootstrap"
                      type="tel"
                      id="phone"
                      value={formData.phone}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    />
                  </FormField>
                  <FormField label="E-Mail *" htmlFor="email" error={fieldErrors.email}>
                    <TextInput
                      variant="bootstrap"
                      invalid={!!fieldErrors.email}
                      type="email"
                      id="email"
                      required
                      value={formData.email}
                      onChange={(e) => {
                        setFormData({ ...formData, email: e.target.value })
                        if (fieldErrors.email) {
                          const newErrors = { ...fieldErrors }
                          delete newErrors.email
                          setFieldErrors(newErrors)
                        }
                      }}
                    />
                  </FormField>
                </div>
              </div>
            )}

            {/* Step 2: Membership & Abo */}
            {currentStep === 2 && (
              <div className="form-step">
                <h3>Mitgliedschaft & Gemüsekorb</h3>
                
                <div className="form-group" style={{ padding: '16px', background: 'var(--bg-secondary)', borderRadius: '12px', marginBottom: '24px' }}>
                  <p style={{ margin: 0, fontWeight: 600 }}>
                    <strong>Ausgewählter Gemüsekorb:</strong> {
                      formData.aboType === 'halb' ? 'Halb (1 Person, CHF 750.-, 1 Anteil)' :
                      formData.aboType === 'standard' ? 'Standard (2-3 Personen, CHF 1\'280.-, 2 Anteile)' :
                      'Doppel (4-6 Personen, CHF 2\'350.-, 4 Anteile)'
                    }
                  </p>
                  <p style={{ marginTop: '8px', marginBottom: 0, fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
                    Du kannst diese Auswahl auf der{' '}
                    <Link 
                      href="/mitmachen" 
                      style={{ 
                        color: 'var(--bioco-green)', 
                        textDecoration: 'underline',
                        fontWeight: 600,
                        cursor: 'pointer'
                      }}
                      className="form-link"
                    >
                      Mitmachen-Seite
                    </Link>{' '}
                    ändern.
                  </p>
                </div>

                <div className="form-group">
                  <label htmlFor="depot">Depot-Auswahl *</label>
                  <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                    Wo möchtest du deinen Gemüsekorb abholen?{' '}
                    <Link 
                      href="/standorte-depots" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      style={{ color: 'var(--bioco-green)', textDecoration: 'underline' }}
                    >
                      Standorte ansehen →
                    </Link>
                  </p>
                  <SelectField
                    variant="bootstrap"
                    invalid={!!fieldErrors.depot}
                    id="depot"
                    required
                    value={formData.depot}
                    onChange={(e) => {
                      setFormData({ ...formData, depot: e.target.value })
                      if (fieldErrors.depot) {
                        const newErrors = { ...fieldErrors }
                        delete newErrors.depot
                        setFieldErrors(newErrors)
                      }
                    }}
                  >
                    <option value="">Bitte wählen...</option>
                    {DEPOTS.map(depot => (
                      <option key={depot} value={depot}>{depot}</option>
                    ))}
                  </SelectField>
                  {fieldErrors.depot && (
                    <div className="invalid-feedback">{fieldErrors.depot}</div>
                  )}
                  <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginTop: '8px' }}>
                    ab 16:00 uhr abholbereit
                  </p>
                </div>

                <div className="form-group">
                  <label>Zahlungsweise *</label>
                  <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                    Die erste Rechnung wird per 31. Januar fällig.
                  </p>
                  <div className={`radio-group ${fieldErrors.paymentType ? 'is-invalid-group' : ''}`}>
                    <label className="radio-option">
                      <input
                        type="radio"
                        name="paymentType"
                        value="quarterly"
                        checked={formData.paymentType === 'quarterly'}
                        onChange={(e) => {
                          setFormData({ ...formData, paymentType: 'quarterly' })
                          if (fieldErrors.paymentType) {
                            const newErrors = { ...fieldErrors }
                            delete newErrors.paymentType
                            setFieldErrors(newErrors)
                          }
                        }}
                      />
                      <span>Quartalsweise (vierteljährlich)</span>
                    </label>
                    <label className="radio-option">
                      <input
                        type="radio"
                        name="paymentType"
                        value="yearly"
                        checked={formData.paymentType === 'yearly'}
                        onChange={(e) => {
                          setFormData({ ...formData, paymentType: 'yearly' })
                          if (fieldErrors.paymentType) {
                            const newErrors = { ...fieldErrors }
                            delete newErrors.paymentType
                            setFieldErrors(newErrors)
                          }
                        }}
                      />
                      <span>Ganzes Jahr (einmalig)</span>
                    </label>
                  </div>
                  {fieldErrors.paymentType && (
                    <div className="invalid-feedback">{fieldErrors.paymentType}</div>
                  )}
                </div>
              </div>
            )}

            {/* Step 3: Mitarbeit */}
            {currentStep === 3 && (
              <div className="form-step">
                <h3>Mitarbeit</h3>
                <p>Jede(r) Mitglied bringt sich ein. Bitte teile uns deine Präferenzen mit:</p>
                
                <div className="form-group">
                  <label>Bevorzugte Tage *</label>
                  <div className={`checkbox-group ${fieldErrors.preferredDays ? 'is-invalid-group' : ''}`}>
                    {DAYS.map(day => (
                      <label key={day} className="checkbox-option">
                        <input
                          type="checkbox"
                          checked={formData.preferredDays.includes(day)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setFormData({ ...formData, preferredDays: [...formData.preferredDays, day] })
                            } else {
                              setFormData({ ...formData, preferredDays: formData.preferredDays.filter(d => d !== day) })
                            }
                            if (fieldErrors.preferredDays) {
                              const newErrors = { ...fieldErrors }
                              delete newErrors.preferredDays
                              setFieldErrors(newErrors)
                            }
                          }}
                        />
                        <span>{day}</span>
                      </label>
                    ))}
                  </div>
                  {fieldErrors.preferredDays && (
                    <div className="invalid-feedback">{fieldErrors.preferredDays}</div>
                  )}
                </div>

                <div className="form-group">
                  <label>Bevorzugte Zeiten *</label>
                  <div className={`checkbox-group ${fieldErrors.preferredTimes ? 'is-invalid-group' : ''}`}>
                    {TIMES.map(time => (
                      <label key={time} className="checkbox-option">
                        <input
                          type="checkbox"
                          checked={formData.preferredTimes.includes(time)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setFormData({ ...formData, preferredTimes: [...formData.preferredTimes, time] })
                            } else {
                              setFormData({ ...formData, preferredTimes: formData.preferredTimes.filter(t => t !== time) })
                            }
                            if (fieldErrors.preferredTimes) {
                              const newErrors = { ...fieldErrors }
                              delete newErrors.preferredTimes
                              setFieldErrors(newErrors)
                            }
                          }}
                        />
                        <span>{time}</span>
                      </label>
                    ))}
                  </div>
                  {fieldErrors.preferredTimes && (
                    <div className="invalid-feedback">{fieldErrors.preferredTimes}</div>
                  )}
                </div>

                <div className="form-group">
                  <label>Tätigkeitsbereiche * (Mehrfachauswahl möglich)</label>
                  <div className={`checkbox-group ${fieldErrors.activityAreas ? 'is-invalid-group' : ''}`}>
                    {ACTIVITY_AREAS.map(area => (
                      <label key={area} className="checkbox-option">
                        <input
                          type="checkbox"
                          checked={formData.activityAreas.includes(area)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setFormData({ ...formData, activityAreas: [...formData.activityAreas, area] })
                            } else {
                              setFormData({ ...formData, activityAreas: formData.activityAreas.filter(a => a !== area) })
                            }
                            if (fieldErrors.activityAreas) {
                              const newErrors = { ...fieldErrors }
                              delete newErrors.activityAreas
                              setFieldErrors(newErrors)
                            }
                          }}
                        />
                        <span>{area}</span>
                      </label>
                    ))}
                  </div>
                  {fieldErrors.activityAreas && (
                    <div className="invalid-feedback">{fieldErrors.activityAreas}</div>
                  )}
                </div>

                {formData.activityAreas.includes('Andere') && (
                  <div className="form-group">
                    <label htmlFor="otherActivity">Andere Tätigkeitsbereiche (bitte beschreiben)</label>
                    <textarea
                      id="otherActivity"
                      rows={3}
                      value={formData.otherActivity}
                      onChange={(e) => setFormData({ ...formData, otherActivity: e.target.value })}
                    />
                  </div>
                )}
              </div>
            )}

            {/* Step 4: Zusatzabos */}
            {currentStep === 4 && (
              <div className="form-step">
                <h3>Zusatzabos</h3>
                <p>Ich bin interessiert, zusätzlich:</p>
                
                <div className="form-group">
                  <div className="checkbox-group">
                    {['Milch', 'Fleisch', 'Käse', 'Kräutersalz'].map(product => (
                      <label key={product} className="checkbox-option">
                        <input
                          type="checkbox"
                          checked={formData.zusatzabos.includes(product)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setFormData({ ...formData, zusatzabos: [...formData.zusatzabos, product] })
                            } else {
                              setFormData({ ...formData, zusatzabos: formData.zusatzabos.filter(p => p !== product) })
                            }
                          }}
                        />
                        <span>{product}</span>
                      </label>
                    ))}
                  </div>
                </div>

                <div className="form-group">
                  <label htmlFor="weitereProdukte">Weitere Produkte von Partnern aus der Region</label>
                  <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                    Hast du Wünsche für weitere Produkte? Teile uns deine Ideen mit:
                  </p>
                  <textarea
                    id="weitereProdukte"
                    rows={4}
                    value={formData.weitereProdukte}
                    onChange={(e) => setFormData({ ...formData, weitereProdukte: e.target.value })}
                    placeholder="z.B. Eier, Brot, Tofu, Honig..."
                  />
                </div>
              </div>
            )}

            {/* Step 5: Summary & Confirmation */}
            {currentStep === 5 && (
              <div className="form-step">
                <h3>Zusammenfassung & Bestätigung</h3>
                <div className="summary-content">
                  <div className="summary-section">
                    <h4>Persönliche Daten</h4>
                    <p><strong>Name:</strong> {formData.firstName} {formData.lastName}</p>
                    <p><strong>Adresse:</strong> {formData.address}, {formData.zip} {formData.city}</p>
                    <p><strong>E-Mail:</strong> {formData.email}</p>
                    {formData.phone && <p><strong>Telefon:</strong> {formData.phone}</p>}
                  </div>

                  <div className="summary-section">
                    <h4>Mitgliedschaft</h4>
                    {formData.membershipType === 'abo' ? (
                      <>
                        <p><strong>Gemüsekorb:</strong> {formData.aboType === 'halb' ? 'Halb' : formData.aboType === 'standard' ? 'Standard' : 'Doppel'}</p>
                        <p><strong>Anteilsscheine:</strong> {totals.totalShares} ({totals.requiredShares} erforderlich)</p>
                        {formData.depot && <p><strong>Depot:</strong> {formData.depot}</p>}
                        <p><strong>Zahlungsweise:</strong> {formData.paymentType === 'quarterly' ? 'Quartalsweise' : 'Ganzes Jahr'}</p>
                      </>
                    ) : (
                      <p><strong>Anteilsscheine:</strong> {formData.sharesOnly} (ohne Gemüsekorb)</p>
                    )}
                  </div>

                  <div className="summary-section">
                    <h4>Mitarbeit</h4>
                    <p><strong>Tage:</strong> {formData.preferredDays.join(', ')}</p>
                    <p><strong>Zeiten:</strong> {formData.preferredTimes.join(', ')}</p>
                    <p><strong>Bereiche:</strong> {formData.activityAreas.join(', ')}</p>
                  </div>

                  {(formData.zusatzabos.length > 0 || formData.weitereProdukte) && (
                    <div className="summary-section">
                      <h4>Zusatzabos</h4>
                      {formData.zusatzabos.length > 0 && (
                        <p><strong>Interesse an:</strong> {formData.zusatzabos.join(', ')}</p>
                      )}
                      {formData.weitereProdukte && (
                        <p><strong>Weitere Wünsche:</strong> {formData.weitereProdukte}</p>
                      )}
                    </div>
                  )}

                  <div className="summary-section summary-total">
                    <h4>Kostenübersicht</h4>
                    {formData.membershipType === 'abo' && (
                      <p><strong>Gemüsekorb:</strong> CHF {totals.aboPrice.toLocaleString('de-CH')}.-</p>
                    )}
                    <p><strong>Anteilsscheine ({totals.totalShares} × CHF 250):</strong> CHF {totals.sharesPrice.toLocaleString('de-CH')}.-</p>
                    <p className="total-price"><strong>Gesamt:</strong> CHF {totals.totalPrice.toLocaleString('de-CH')}.-</p>
                  </div>
                </div>

                <div className="form-group" style={{ marginTop: '24px' }}>
                  <div className={`checkbox-group ${fieldErrors.privacyAccept ? 'is-invalid-group' : ''}`}>
                    <label className="checkbox-option">
                      <input
                        type="checkbox"
                        required
                        checked={formData.privacyAccept}
                        onChange={(e) => {
                          setFormData({ ...formData, privacyAccept: e.target.checked })
                          if (fieldErrors.privacyAccept) {
                            const newErrors = { ...fieldErrors }
                            delete newErrors.privacyAccept
                            setFieldErrors(newErrors)
                          }
                        }}
                      />
                      Ich akzeptiere die <Link href="/datenschutz">Datenschutzbestimmungen</Link> *
                    </label>
                  </div>
                  {fieldErrors.privacyAccept && (
                    <div className="invalid-feedback">{fieldErrors.privacyAccept}</div>
                  )}
                </div>

              </div>
            )}

            {/* Navigation Buttons */}
            <div className="form-navigation">
              {currentStep > 0 && (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={handlePrevious}
                >
                  Zurück
                </Button>
              )}
              {currentStep < 5 ? (
                <Button
                  type="button"
                  variant="primary"
                  onClick={handleNext}
                >
                  Weiter
                </Button>
              ) : (
                <Button
                  type="submit"
                  variant="primary"
                  disabled={isSubmitting}
                >
                  {isSubmitting ? 'Wird gesendet...' : 'Anmeldung einreichen'}
                </Button>
              )}
            </div>
          </form>
        </div>

        {/* Sticky Summary */}
        <div className="form-summary">
          <Card as="div" variant="plain" className="summary-card">
            <h4>Zusammenfassung</h4>
            <div className="summary-content-compact">
              {formData.membershipType === 'abo' && formData.aboType !== 'none' ? (
                <>
                  <p><strong>Gemüsekorb:</strong> {formData.aboType === 'halb' ? 'Halb' : formData.aboType === 'standard' ? 'Standard' : 'Doppel'}</p>
                  <p><strong>Anteilsscheine:</strong> {totals.totalShares}</p>
                  {formData.depot && <p><strong>Depot:</strong> {formData.depot}</p>}
                </>
              ) : (
                <p><strong>Anteilsscheine:</strong> {formData.membershipType === 'shares-only' ? formData.sharesOnly : totals.totalShares}</p>
              )}
              <p className="summary-total-text"><strong>Gesamt:</strong> CHF {totals.totalPrice.toLocaleString('de-CH')}.-</p>
            </div>
          </Card>
        </div>
      </div>
    </div>
  )
}
