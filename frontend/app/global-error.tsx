'use client'

import { useEffect, useMemo, useState } from 'react'
import { buildStaleRecoveryUrl, isStaleDeploymentError } from './staleDeploymentRecovery'

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
  const [isRefreshing, setIsRefreshing] = useState(false)
  const isStaleDeploy = useMemo(() => isStaleDeploymentError(error), [error])

  useEffect(() => {
    if (!isStaleDeploy || typeof window === 'undefined') return
    const reloadKey = `bioco:global-stale-reload:${window.location.pathname}`
    const now = Date.now()
    const previousReloadTs = Number(window.sessionStorage.getItem(reloadKey) || '0')
    if (Number.isFinite(previousReloadTs) && now - previousReloadTs < 15000) return
    window.sessionStorage.setItem(reloadKey, String(now))
    setIsRefreshing(true)
    const timer = window.setTimeout(() => {
      window.location.replace(buildStaleRecoveryUrl(window.location.href, now))
    }, 150)
    return () => window.clearTimeout(timer)
  }, [isStaleDeploy])

  return (
    <html lang="de">
      <body>
        <div style={{
          minHeight: '100vh',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '2rem',
          background: 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)',
          fontFamily: "'DM Sans', sans-serif",
          textAlign: 'center',
        }}>
          <h1 style={{ fontSize: '4rem', color: 'var(--color-carrot-logo, #F29200)', margin: 0 }}>Fehler</h1>
          <p style={{ fontSize: '1.25rem', color: '#333', margin: '1rem 0 2rem' }}>
            {isRefreshing ? 'Website wird aktualisiert…' : 'Etwas ist schiefgelaufen.'}
          </p>
          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', justifyContent: 'center' }}>
            <button
              onClick={reset}
              disabled={isRefreshing}
              style={{
                padding: '0.75rem 1.5rem',
                background: 'var(--color-carrot-logo, #F29200)',
                color: 'white',
                border: 'none',
                borderRadius: '25px',
                fontSize: '1rem',
                fontWeight: 700,
                cursor: isRefreshing ? 'wait' : 'pointer',
                opacity: isRefreshing ? 0.7 : 1,
              }}
            >
              Erneut versuchen
            </button>
            <a
              href="/"
              style={{
                padding: '0.75rem 1.5rem',
                background: 'white',
                color: 'var(--color-carrot-logo, #F29200)',
                border: '2px solid var(--color-carrot-logo, #F29200)',
                borderRadius: '25px',
                fontSize: '1rem',
                fontWeight: 700,
                textDecoration: 'none',
              }}
            >
              Zur Startseite
            </a>
          </div>
        </div>
      </body>
    </html>
  )
}
