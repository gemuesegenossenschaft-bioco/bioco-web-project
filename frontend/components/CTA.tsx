'use client'

import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'

interface CTAProps {
  text: string
  href: string
  variant?: 'primary' | 'secondary'
  onClick?: () => void
}

function scrollToElement(id: string) {
  const element = document.getElementById(id)
  if (element) {
    const offsetPosition = element.getBoundingClientRect().top + window.pageYOffset - 100
    window.scrollTo({ top: offsetPosition, behavior: 'smooth' })
  }
}

export function CTA({ text, href, variant = 'primary', onClick }: CTAProps) {
  const router = useRouter()

  const isExternal = href.startsWith('http://') || href.startsWith('https://')
  const isFile = /\.(pdf|doc|docx|xls|xlsx|zip)$/i.test(href)

  const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault()
    if (onClick) {
      onClick()
    }

    if (isExternal || isFile) {
      window.open(href, '_blank', 'noopener,noreferrer')
      return
    }

    if (href.startsWith('#')) {
      scrollToElement(href.substring(1))
      return
    }

    // Scroll targets are CMS section ids (SectionRenderer renders each
    // section wrapper with id={section.id}).
    let scrollTarget: string | null = null
    if (href === '/kontakt') {
      scrollTarget = 'kontakt-formular-intro'
    } else if (href === '/standorte-depots') {
      scrollTarget = 'depots'
    }

    router.push(href)
    setTimeout(() => {
      if (scrollTarget) {
        scrollToElement(scrollTarget)
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' })
      }
    }, 100)
  }
  
  return (
    <Button
      type="button"
      variant={variant}
      organic
      onClick={handleClick}
    >
      {text}
    </Button>
  )
}