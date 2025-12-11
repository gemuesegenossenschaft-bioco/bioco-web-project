'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
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
  const router = useRouter()

  useEffect(() => {
    const onScroll = () => {
      // Check if we're on the home page
      const isHomePage = pathname === '/'
      
      if (!isHomePage) {
        // On subpages: scrolled state immediately when scrolling
        setScrolled(window.scrollY > 0)
      } else {
        // On home page: only change state after leaving the hero image
        const heroBleed = document.querySelector('.hero-bleed')
        if (heroBleed) {
          const heroRect = heroBleed.getBoundingClientRect()
          // When hero image bottom is scrolled past (above viewport)
          setScrolled(heroRect.bottom < 0)
        } else {
          // Fallback: check for hero-bg element
          const heroBg = document.querySelector('.hero-bg')
          if (heroBg) {
            const heroRect = heroBg.getBoundingClientRect()
            setScrolled(heroRect.bottom < 0)
          } else {
            // Final fallback: use viewport height calculation
            const heroHeight = window.innerHeight * 0.65
            setScrolled(window.scrollY > heroHeight)
          }
        }
      }
    }
    onScroll()
    window.addEventListener('scroll', onScroll)
    return () => window.removeEventListener('scroll', onScroll)
  }, [pathname])

  const handleLinkClick = (href: string) => (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault()
    setIsOpen(false)
    router.push(href)
    // Scroll to top after navigation
    setTimeout(() => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      })
    }, 100)
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
                        onClick={handleLinkClick(item.href)}
                      >
                        {item.title}
                      </Link>
                    </li>
                  )
                })}
                <li>
                  <Link
                    href="/bioco-werden"
                    className="btn btn-orange btn-organic"
                    onClick={handleLinkClick('/bioco-werden')}
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
                        onClick={handleLinkClick(item.href)}
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
