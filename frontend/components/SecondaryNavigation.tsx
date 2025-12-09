'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useEffect, useState } from 'react'
import { Menu, X } from 'lucide-react'
import { Logo } from './Logo'

// Primary navigation items (main menu)
const primaryNavItems = [
  { title: 'Wir', href: '/wir' },
  { title: 'Gemüse', href: '/gemuese' },
  { title: 'Mitmachen', href: '/mitmachen' },
  { title: 'Abos', href: '/abos' },
  { title: 'Aktuelles', href: '/aktuelles' },
]

export function PrimaryNavigation() {
  const pathname = usePathname()
  const [isMobileOpen, setIsMobileOpen] = useState(false)
  const [scrolled, setScrolled] = useState(false)

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

  useEffect(() => {
    if (!isMobileOpen) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setIsMobileOpen(false)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [isMobileOpen])

  const handleNavClick = () => setIsMobileOpen(false)

  return (
    <nav className={`primary-nav ${scrolled ? 'nav-scrolled' : ''}`}>
      <div className="primary-nav-container">
        <div className="primary-nav-logo">
          <Logo />
        </div>
        <ul>
          {primaryNavItems.map((item) => {
            const isActive = pathname === item.href
            
            return (
              <li key={item.href}>
                <Link 
                  href={item.href}
                  className={isActive ? 'active' : ''}
                  onClick={handleNavClick}
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
              onClick={handleNavClick}
            >
              biocò werden
            </Link>
          </li>
        </ul>
        <button
          className="nav-mobile-toggle"
          aria-label="Navigation öffnen"
          onClick={() => setIsMobileOpen(!isMobileOpen)}
        >
          {isMobileOpen ? <X size={24} /> : <Menu size={24} />}
        </button>
      </div>

      {isMobileOpen && (
        <div className="nav-drawer">
          <ul>
            {primaryNavItems.map((item) => {
              const isActive = pathname === item.href
              return (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    className={isActive ? 'active' : ''}
                    onClick={handleNavClick}
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
                onClick={handleNavClick}
              >
                biocò werden
              </Link>
            </li>
          </ul>
        </div>
      )}
    </nav>
  )
}

// Keep SecondaryNavigation as an alias for backwards compatibility
export const SecondaryNavigation = PrimaryNavigation
