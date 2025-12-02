'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
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

  return (
    <nav className="primary-nav">
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
              className="btn btn-orange"
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
