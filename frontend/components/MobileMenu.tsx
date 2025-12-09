'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Menu, X } from 'lucide-react'
import { Logo } from './Logo'

// Primary navigation items
const primaryNavItems = [
  { title: 'Wir', href: '/wir' },
  { title: 'Gemüse', href: '/gemuese' },
  { title: 'Mitmachen', href: '/mitmachen' },
  { title: 'Abos', href: '/abos' },
  { title: 'Aktuelles', href: '/aktuelles' },
]

// Utility navigation items (secondary nav)
const utilityNavItems = [
  { title: 'Standorte', href: '/standorte-depots' },
  { title: 'Kontakt', href: '/kontakt' },
  { title: 'Intranet', href: '/intranet' },
]

export function MobileMenu() {
  const [isOpen, setIsOpen] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const pathname = usePathname()

  useEffect(() => {
    const onScroll = () => {
      // Find the h1 title element to detect when scrolling past it
      const heroTitle = document.querySelector('.hero-content h1')
      if (heroTitle) {
        const titleRect = heroTitle.getBoundingClientRect()
        // When h1 title is scrolled past (above viewport)
        setScrolled(titleRect.bottom < 0)
      } else {
        // Fallback: trigger when scrolling out of hero (approximately 65vh hero height)
        const heroHeight = window.innerHeight * 0.65
        setScrolled(window.scrollY > heroHeight - 80)
      }
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
              <div className="mobile-menu-logo">
                <Logo />
              </div>
              <button
                className="mobile-menu-close"
                onClick={(e) => {
                  e.preventDefault()
                  e.stopPropagation()
                  setIsOpen(false)
                }}
                aria-label="Close menu"
                type="button"
              >
                ×
              </button>
            </div>
            <div className="mobile-menu-primary-section">
              <ul className="mobile-nav-list">
                {primaryNavItems.map((item) => {
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
                    className="btn btn-orange btn-organic"
                    onClick={handleLinkClick}
                  >
                    biocò werden
                  </Link>
                </li>
              </ul>
            </div>
            <div className="mobile-menu-secondary-section">
              <ul className="mobile-nav-list">
                {utilityNavItems.map((item) => {
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
              </ul>
            </div>
          </nav>
        </div>
      )}
    </>
  )
}
