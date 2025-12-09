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
      // Trigger when scrolling out of hero (approximately 65vh hero height)
      const heroHeight = window.innerHeight * 0.65
      setScrolled(window.scrollY > heroHeight - 80)
    }
    onScroll()
    window.addEventListener('scroll', onScroll)
    return () => window.removeEventListener('scroll', onScroll)
  }, [])


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
              href="/mitmachen" 
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
