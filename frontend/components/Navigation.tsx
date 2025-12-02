'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'

const navigationItems = [
  { title: 'Wir', href: '/wir', id: 'F' },
  { title: 'Gemüse', href: '/gemuese', id: 'B' },
  { title: 'Mitmachen', href: '/mitmachen', id: 'D' },
  { title: 'Abos', href: '/abos', id: 'C' },
  { title: 'Standorte', href: '/standorte-depots', id: 'E' },
  { title: 'Aktuelles', href: '/aktuelles', id: 'G' },
]

interface NavigationProps {
  onLinkClick?: () => void
  hideMitmachen?: boolean
}

export function Navigation({ onLinkClick, hideMitmachen = false }: NavigationProps = {}) {
  const pathname = usePathname()

  return (
    <nav id="header-nav" className="header-nav">
      <ul>
        {navigationItems.map((item) => {
          const isActive = pathname === item.href
          
          return (
            <li key={item.id}>
              <Link 
                href={item.href} 
                className={isActive ? 'nav-active' : ''}
                onClick={onLinkClick}
              >
                {item.title}
              </Link>
            </li>
          )
        })}
        {!hideMitmachen && (
          <li>
            <Link 
              href="/mitmachen" 
              className="btn btn-orange"
              onClick={onLinkClick}
            >
              biocò werden
            </Link>
          </li>
        )}
      </ul>
    </nav>
  )
}