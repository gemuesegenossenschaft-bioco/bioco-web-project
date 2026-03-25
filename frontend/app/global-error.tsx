'use client'

import { useEffect, useMemo, useState } from 'react'

function isStaleDeploymentError(error: Error & { digest?: string }) {
  const haystack = `${error?.message || ''} ${error?.digest || ''}`.toLowerCase()
  return (
    haystack.includes('failed to find server action') ||
    haystack.includes('chunkloaderror') ||
    haystack.includes('loading chunk') ||
    haystack.includes('failed to fetch dynamically imported module') ||
    haystack.includes("reading 'workers'")
  )
}

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
    if (window.sessionStorage.getItem(reloadKey)) return
    window.sessionStorage.setItem(reloadKey, '1')
    setIsRefreshing(true)
    const timer = window.setTimeout(() => {
      window.location.reload()
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
          <h1 style={{ fontSize: '4rem', color: '#F29200', margin: 0 }}>Fehler</h1>
          <p style={{ fontSize: '1.25rem', color: '#333', margin: '1rem 0 2rem' }}>
            {isRefreshing ? 'Website wird aktualisiert…' : 'Etwas ist schiefgelaufen.'}
          </p>
          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', justifyContent: 'center' }}>
            <button
              onClick={reset}
              disabled={isRefreshing}
              style={{
                padding: '0.75rem 1.5rem',
                background: '#F29200',
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
                color: '#F29200',
                border: '2px solid #F29200',
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
