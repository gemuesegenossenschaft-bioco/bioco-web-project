'use client'

import { usePathname } from 'next/navigation'
import { useEffect } from 'react'

export function PathnameBodyClass() {
  const pathname = usePathname()

  useEffect(() => {
    // Set data attribute on body for CSS targeting
    document.body.setAttribute('data-pathname', pathname)
    
    return () => {
      document.body.removeAttribute('data-pathname')
    }
  }, [pathname])

  return null
}
