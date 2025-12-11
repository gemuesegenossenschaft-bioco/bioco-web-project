'use client'

import { useRouter } from 'next/navigation'

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
    
    // Check if it's an anchor link (starts with #)
    if (href.startsWith('#')) {
      // For anchor links, scroll to the element on the same page
      const element = document.getElementById(href.substring(1))
      if (element) {
        const headerOffset = 100
        const elementPosition = element.getBoundingClientRect().top
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset
        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        })
      }
      return
    }
    
    // Determine scroll target based on href for specific pages
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
      // For other pages, scroll to top after navigation
      router.push(href)
      // Wait for navigation to complete, then scroll to top
      setTimeout(() => {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        })
      }, 100)
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