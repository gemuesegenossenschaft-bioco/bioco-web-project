'use client'

export default function GlobalError({
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
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
            Etwas ist schiefgelaufen.
          </p>
          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', justifyContent: 'center' }}>
            <button
              onClick={reset}
              style={{
                padding: '0.75rem 1.5rem',
                background: '#F29200',
                color: 'white',
                border: 'none',
                borderRadius: '25px',
                fontSize: '1rem',
                fontWeight: 700,
                cursor: 'pointer',
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
