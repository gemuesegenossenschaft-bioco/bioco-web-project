'use client'

import { useEffect, useRef, useState } from 'react'
import Script from 'next/script'

declare global {
  interface Window {
    turnstile?: {
      render: (container: string | HTMLElement, options: Record<string, unknown>) => string
      remove: (widgetId: string) => void
    }
  }
}

type CaptchaFieldProps = {
  token: string
  onTokenChange: (token: string) => void
  resetKey: number
  error?: string
}

export function CaptchaField({ token, onTokenChange, resetKey, error }: CaptchaFieldProps) {
  const siteKey = process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY
  const [scriptReady, setScriptReady] = useState(false)
  const containerRef = useRef<HTMLDivElement | null>(null)
  const widgetIdRef = useRef<string | null>(null)

  useEffect(() => {
    if (window.turnstile) {
      setScriptReady(true)
    }
  }, [])

  useEffect(() => {
    if (!scriptReady || !siteKey || !containerRef.current || !window.turnstile) {
      return
    }

    if (widgetIdRef.current) {
      window.turnstile.remove(widgetIdRef.current)
      widgetIdRef.current = null
    }

    widgetIdRef.current = window.turnstile.render(containerRef.current, {
      sitekey: siteKey,
      callback: (nextToken: string) => onTokenChange(nextToken),
      'expired-callback': () => onTokenChange(''),
      'error-callback': () => onTokenChange(''),
    })

    return () => {
      if (widgetIdRef.current && window.turnstile) {
        window.turnstile.remove(widgetIdRef.current)
        widgetIdRef.current = null
      }
    }
  }, [onTokenChange, resetKey, scriptReady, siteKey])

  if (!siteKey) {
    return (
      <div className="form-error bento-card">
        <p>Captcha ist aktuell nicht verfügbar. Bitte später erneut versuchen.</p>
      </div>
    )
  }

  return (
    <div className="form-group">
      <Script
        id="cloudflare-turnstile"
        src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"
        strategy="afterInteractive"
        onLoad={() => setScriptReady(true)}
      />
      <div ref={containerRef} />
      {error && <div className="invalid-feedback">{error}</div>}
      {!token && !error && <small>Bitte Captcha bestätigen, um das Formular zu senden.</small>}
    </div>
  )
}
