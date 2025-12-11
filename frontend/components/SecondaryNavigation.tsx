'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useEffect, useState } from 'react'
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
  const [scrolled, setScrolled] = useState(false)

  useEffect(() => {
    const onScroll = () => {
      // Auf Unterseiten (kein Hero): immer im scrolled state (kein State-Wechsel)
      const isHomePage = pathname === '/'
      if (!isHomePage) {
        // On sub pages: always in scrolled state (no state change)
        setScrolled(true)
      } else {
        // Auf Homepage: erst nach Hero sticky - detect when hero image is scrolled past
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
            >
              biocò werden
            </Link>
          </li>
        </ul>
      </div>
    </nav>
  )
}

// Keep SecondaryNavigation as an alias for backwards compatibility
export const SecondaryNavigation = PrimaryNavigation
