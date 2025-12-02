'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'

const popularPages = [
  { title: 'Gemüse', href: '/gemuese' },
  { title: 'Anpacken', href: '/anpacken' },
  { title: 'Abos', href: '/abos' },
  { title: 'Wir', href: '/wir' },
  { title: 'Aktuelles', href: '/aktuelles' },
  { title: 'Standorte', href: '/standorte-depots' },
  { title: 'Kontakt', href: '/kontakt' },
]

export default function NotFound() {
  const router = useRouter()
  const [searchQuery, setSearchQuery] = useState('')
  const [duckState, setDuckState] = useState<'confused' | 'pecking' | 'idle'>('confused')
  const [peckCount, setPeckCount] = useState(0)

  useEffect(() => {
    // Start with confused look
    const confusedTimer = setTimeout(() => {
      setDuckState('pecking')
    }, 2000)

    return () => clearTimeout(confusedTimer)
  }, [])

  useEffect(() => {
    if (duckState === 'pecking') {
      const peckInterval = setInterval(() => {
        setPeckCount(prev => {
          if (prev >= 3) {
            setDuckState('idle')
            return prev
          }
          return prev + 1
        })
      }, 600)

      return () => clearInterval(peckInterval)
    }
  }, [duckState])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    if (searchQuery.trim()) {
      // Simple search - redirect to homepage with query
      router.push(`/?search=${encodeURIComponent(searchQuery)}`)
    }
  }

  return (
    <div className="not-found-container">
      <style jsx>{`
        .not-found-container {
          min-height: 100vh;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 2rem;
          background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
          font-family: 'Patrick Hand', sans-serif;
        }

        .not-found-content {
          text-align: center;
          max-width: 600px;
          width: 100%;
        }

        .error-code {
          font-size: 8rem;
          font-weight: 700;
          color: #F29200;
          margin: 0;
          line-height: 1;
          text-shadow: 3px 3px 0px rgba(0,0,0,0.1);
        }

        .error-message {
          font-size: 1.5rem;
          color: #333;
          margin: 1rem 0 2rem;
        }

        .duck-container {
          position: relative;
          width: 200px;
          height: 200px;
          margin: 2rem auto;
        }

        .duck {
          position: absolute;
          width: 120px;
          height: 120px;
          left: 50%;
          top: 50%;
          transform: translate(-50%, -50%);
          transition: all 0.3s ease;
        }

        .duck.confused {
          animation: confusedLook 1s ease-in-out infinite;
        }

        .duck.pecking {
          animation: pecking 0.6s ease-in-out infinite;
        }

        .duck.idle {
          animation: idleBob 2s ease-in-out infinite;
        }

        @keyframes confusedLook {
          0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
          25% { transform: translate(-50%, -50%) rotate(-10deg) translateX(-5px); }
          75% { transform: translate(-50%, -50%) rotate(10deg) translateX(5px); }
        }

        @keyframes pecking {
          0%, 100% { transform: translate(-50%, -50%) rotate(0deg) translateY(0); }
          50% { transform: translate(-50%, -50%) rotate(-5deg) translateY(15px); }
        }

        @keyframes idleBob {
          0%, 100% { transform: translate(-50%, -50%) translateY(0); }
          50% { transform: translate(-50%, -50%) translateY(-5px); }
        }

        .duck-body {
          fill: #F29200;
          stroke: #E88200;
          stroke-width: 2;
        }

        .duck-head {
          fill: #F29200;
          stroke: #E88200;
          stroke-width: 2;
          transition: transform 0.3s ease;
        }

        .duck.pecking .duck-head {
          transform-origin: center bottom;
        }

        .duck-bill {
          fill: #E88200;
          stroke: #D67A00;
          stroke-width: 1.5;
        }

        .duck-eye {
          fill: #1F2A1B;
        }

        .duck-eye.confused {
          animation: blink 0.3s ease-in-out;
        }

        @keyframes blink {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.3; }
        }

        .search-container {
          margin: 3rem auto 2rem;
          max-width: 400px;
          position: relative;
        }

        .search-form {
          display: flex;
          gap: 0.5rem;
          background: white;
          border-radius: 25px;
          padding: 0.5rem;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
          border: 3px solid #F29200;
        }

        .search-input {
          flex: 1;
          border: none;
          outline: none;
          padding: 0.75rem 1rem;
          font-size: 1rem;
          font-family: 'Patrick Hand', sans-serif;
          background: transparent;
        }

        .search-button {
          background: #F29200;
          color: white;
          border: none;
          border-radius: 20px;
          padding: 0.75rem 1.5rem;
          font-size: 1rem;
          font-family: 'Patrick Hand', sans-serif;
          font-weight: 700;
          cursor: pointer;
          transition: background 0.2s;
        }

        .search-button:hover {
          background: #E88200;
        }

        .popular-pages {
          margin-top: 3rem;
        }

        .popular-pages-title {
          font-size: 1.25rem;
          color: #333;
          margin-bottom: 1.5rem;
          font-weight: 700;
        }

        .popular-pages-list {
          display: flex;
          flex-wrap: wrap;
          gap: 1rem;
          justify-content: center;
        }

        .popular-page-link {
          display: inline-block;
          padding: 0.75rem 1.5rem;
          background: white;
          color: #F29200;
          text-decoration: none;
          border-radius: 20px;
          border: 2px solid #F29200;
          font-weight: 700;
          transition: all 0.2s;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .popular-page-link:hover {
          background: #F29200;
          color: white;
          transform: translateY(-2px);
          box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .home-link {
          margin-top: 2rem;
          display: inline-block;
          padding: 1rem 2rem;
          background: #F29200;
          color: white;
          text-decoration: none;
          border-radius: 25px;
          font-weight: 700;
          font-size: 1.1rem;
          transition: all 0.2s;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .home-link:hover {
          background: #E88200;
          transform: translateY(-2px);
          box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
      `}</style>

      <div className="not-found-content">
        <h1 className="error-code">404</h1>
        <p className="error-message">Oops! Diese Seite existiert nicht.</p>

        <div className="duck-container">
          <svg 
            className={`duck ${duckState}`}
            viewBox="0 0 24 24" 
            xmlns="http://www.w3.org/2000/svg"
          >
            {/* Duck body - oval shape */}
            <ellipse 
              cx="14" 
              cy="16" 
              rx="7" 
              ry="5" 
              className="duck-body"
            />
            {/* Duck head - round */}
            <circle 
              cx="7" 
              cy="11" 
              r="6" 
              className="duck-head"
            />
            {/* Duck bill - orange triangle pointing left */}
            <path 
              d="M1 11 L1 9 Q1 8 2 9 L4 10 Q5 10.5 6 10 L6 12 Q5 12.5 4 12 L2 11 Q1 11.5 1 11 Z" 
              className="duck-bill"
            />
            {/* Duck eye */}
            <circle 
              cx="6" 
              cy="10" 
              r="1.5" 
              className={`duck-eye ${duckState === 'confused' ? 'confused' : ''}`}
            />
            {/* Duck wing - visible on body */}
            <ellipse 
              cx="15" 
              cy="15" 
              rx="4" 
              ry="3" 
              fill="#E88200" 
              stroke="#D67A00" 
              strokeWidth="1.5"
            />
            {/* Duck tail feathers */}
            <path 
              d="M21 16 Q23 14 23 18 Q23 20 21 18" 
              fill="#E88200" 
              stroke="#D67A00" 
              strokeWidth="1.5" 
              strokeLinecap="round"
            />
          </svg>
        </div>

        <div className="search-container">
          <form className="search-form" onSubmit={handleSearch}>
            <input
              type="text"
              className="search-input"
              placeholder="Was suchst du?"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
            <button type="submit" className="search-button">
              Suchen
            </button>
          </form>
        </div>

        <div className="popular-pages">
          <h2 className="popular-pages-title">Beliebte Seiten:</h2>
          <div className="popular-pages-list">
            {popularPages.map((page) => (
              <Link 
                key={page.href} 
                href={page.href}
                className="popular-page-link"
              >
                {page.title}
              </Link>
            ))}
          </div>
        </div>

        <Link href="/" className="home-link">
          Zur Startseite
        </Link>
      </div>
    </div>
  )
}

