'use client'

import { useState } from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { TriColorHamburgerIcon } from './TriColorHamburgerIcon'

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
  const pathname = usePathname()

  const handleLinkClick = () => {
    setIsOpen(false)
  }

  return (
    <>
      <div className="mobile-header-actions">
        <button
          className="mobile-menu-toggle"
          onClick={() => setIsOpen(!isOpen)}
          aria-label="Toggle menu"
          aria-expanded={isOpen}
        >
          <TriColorHamburgerIcon width={30} height={20} />
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
              <h3 className="mobile-menu-title">Menü</h3>
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
                    href="/mitmachen"
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
