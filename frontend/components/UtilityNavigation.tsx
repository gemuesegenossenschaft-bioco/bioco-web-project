'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'

const utilityItems = [
  { title: 'Standorte', href: '/standorte-depots' },
  { title: 'Kontakt', href: '/kontakt' },
  { title: 'Intranet', href: '/intranet' },
]

export function UtilityNavigation() {
  const pathname = usePathname()

  return (
    <nav className="utility-nav">
      <div className="utility-nav-container">
        <ul>
          {utilityItems.map((item) => {
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
        </ul>
      </div>
    </nav>
  )
}

