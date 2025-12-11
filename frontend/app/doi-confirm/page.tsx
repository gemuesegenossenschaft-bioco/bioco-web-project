'use client'

import { useSearchParams } from 'next/navigation'
import { Suspense } from 'react'
import { useEffect, useState } from 'react'
import { trackEvent } from '@/components/MatomoScript'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import Link from 'next/link'

function DOIConfirmContent() {
  const searchParams = useSearchParams()
  const token = searchParams.get('token')
  const [confirmed, setConfirmed] = useState(false)
  const [error, setError] = useState('')
  const [formType, setFormType] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!token) {
      setError('Kein Bestätigungstoken angegeben.')
      setLoading(false)
      return
    }

    const confirmDOI = async () => {
      try {
        const response = await fetch(`/api/doi/confirm?token=${token}`)
        const data = await response.json()

        if (data.success) {
          setConfirmed(true)
          setFormType(data.form_type)
          trackEvent('DOI', 'Confirm', data.form_type)
        } else {
          setError(data.error || 'Ungültiger oder abgelaufener Bestätigungslink.')
          trackEvent('DOI', 'Error', data.form_type || 'unknown')
        }
      } catch (err) {
        setError('Es ist ein Fehler aufgetreten.')
        trackEvent('DOI', 'Error', 'unknown')
      } finally {
        setLoading(false)
      }
    }

    confirmDOI()
  }, [token])

  if (loading) {
    return (
      <>
        <Header />
        <main className="main-content">
          <div className="bento-grid">
            <section className="bento-card">
              <div className="plant-pattern"></div>
              <div className="card-body">
                <p className="card-text">Bitte warten...</p>
              </div>
            </section>
          </div>
        </main>
        <Footer />
      </>
    )
  }

  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          {confirmed ? (
            <section className="bento-card bento-card-large">
              <div className="plant-pattern"></div>
              <div className="card-header">
                <h3>Anmeldung bestätigt</h3>
              </div>
              <div className="card-body">
                <p className="card-text">Vielen Dank! Deine Anmeldung wurde erfolgreich bestätigt.</p>
                
                {formType === 'subscribe' && (
                  <p className="card-text">Du erhältst ab sofort unseren Newsletter.</p>
                )}
                {formType === 'visit' && (
                  <p className="card-text">Wir haben deine Anmeldung für den Tag der offenen Tür erhalten und melden uns bald bei dir.</p>
                )}
                {formType === 'waiting_list' && (
                  <p className="card-text">Wir haben dich auf die Warteliste gesetzt und melden uns bei dir, sobald ein Platz frei wird.</p>
                )}
                {formType === 'contact' && (
                  <p className="card-text">Wir haben deine Nachricht erhalten und melden uns bald bei dir.</p>
                )}
              </div>
            </section>
          ) : (
            <section className="bento-card bento-card-large">
              <div className="plant-pattern"></div>
              <div className="card-header">
                <h3>Bestätigung fehlgeschlagen</h3>
              </div>
              <div className="card-body">
                <p className="card-text">{error}</p>
                <p className="card-text"><Link href="/">Zurück zur Startseite</Link></p>
              </div>
            </section>
          )}
        </div>
      </main>
      <Footer />
    </>
  )
}

export default function DOIConfirmPage() {
  return (
    <Suspense fallback={
      <>
        <Header />
        <main className="main-content">
          <div className="bento-grid">
            <section className="bento-card">
              <div className="plant-pattern"></div>
              <div className="card-body">
                <p className="card-text">Bitte warten...</p>
              </div>
            </section>
          </div>
        </main>
        <Footer />
      </>
    }>
      <DOIConfirmContent />
    </Suspense>
  )
}