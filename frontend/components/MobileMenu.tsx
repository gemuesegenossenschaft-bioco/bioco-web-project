'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Menu, X } from 'lucide-react'

// All navigation items for mobile - combines primary and utility nav
const allNavItems = [
  // Primary navigation items
  { title: 'Wir', href: '/wir', section: 'primary' },
  { title: 'Gemüse', href: '/gemuese', section: 'primary' },
  { title: 'Mitmachen', href: '/mitmachen', section: 'primary' },
  { title: 'Abos', href: '/abos', section: 'primary' },
  { title: 'Aktuelles', href: '/aktuelles', section: 'primary' },
  // Utility navigation items
  { title: 'Standorte', href: '/standorte-depots', section: 'utility' },
  { title: 'Kontakt', href: '/kontakt', section: 'utility' },
  { title: 'Intranet', href: '/intranet', section: 'utility' },
]

export function MobileMenu() {
  const [isOpen, setIsOpen] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const pathname = usePathname()

  useEffect(() => {
    const onScroll = () => {
      // Trigger when scrolling out of hero (approximately 65vh hero height)
      const heroHeight = window.innerHeight * 0.65
      setScrolled(window.scrollY > heroHeight - 80)
    }
    onScroll()
    window.addEventListener('scroll', onScroll)
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const handleLinkClick = () => {
    setIsOpen(false)
  }

  return (
    <>
      <div className="mobile-header-actions">
        <button
          className={`mobile-menu-toggle ${scrolled ? 'scrolled' : ''}`}
          onClick={() => setIsOpen(!isOpen)}
          aria-label="Toggle menu"
          aria-expanded={isOpen}
        >
          {isOpen ? <X size={24} /> : <Menu size={24} />}
        </button>
      </div>
      {isOpen && (
        <div 
          className="mobile-menu-overlay" 
          onClick={() => setIsOpen(false)}
          role="dialog"
          aria-modal="true"
          aria-label="Navigation menu"
        >
          <nav className="mobile-menu" onClick={(e) => e.stopPropagation()}>
            <div className="mobile-menu-header">
              <button
                className="mobile-menu-close"
                onClick={() => setIsOpen(false)}
                aria-label="Close menu"
              >
                ×
              </button>
            </div>
            <div className="mobile-menu-primary-section">
              <ul className="mobile-nav-list">
                {allNavItems.map((item) => {
                  const isActive = pathname === item.href
                  return (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        className={isActive ? 'active' : ''}
                        onClick={handleLinkClick}
                      >
                        {item.title}
                      </Link>
                    </li>
                  )
                })}
                <li>
                  <Link
                    href="/anmeldung"
                    className="btn btn-orange"
                    onClick={handleLinkClick}
                  >
                    biocò werden
                  </Link>
                </li>
              </ul>
            </div>
          </nav>
        </div>
      )}
    </>
  )
}
