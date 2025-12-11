'use client'

import { useRouter } from 'next/navigation'
import { useEffect } from 'react'

interface CTAProps {
  text: string
  href: string
  variant?: 'primary' | 'secondary'
  onClick?: () => void
}

export function CTA({ text, href, variant = 'primary', onClick }: CTAProps) {
  const router = useRouter()
  const className = `${variant === 'primary' ? 'btn btn-primary' : 'btn btn-secondary'} btn-organic`
  
  const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault()
    if (onClick) {
      onClick()
    }
    
    // Determine scroll target based on href
    let scrollTarget: string | null = null
    if (href === '/kontakt') {
      scrollTarget = 'kontakt-formular'
    } else if (href === '/standorte-depots') {
      scrollTarget = 'E-02'
    }
    
    if (scrollTarget) {
      router.push(href)
      // Wait for navigation to complete, then scroll
      setTimeout(() => {
        const element = document.getElementById(scrollTarget!)
        if (element) {
          const headerOffset = 100
          const elementPosition = element.getBoundingClientRect().top
          const offsetPosition = elementPosition + window.pageYOffset - headerOffset
          window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
          })
        }
      }, 100)
    } else {
      router.push(href)
    }
  }
  
  return (
    <button
      type="button"
      className={className}
      onClick={handleClick}
    >
      {text}
    </button>
  )
}