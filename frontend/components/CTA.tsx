'use client'

import { useRouter } from 'next/navigation'

interface CTAProps {
  text: string
  href: string
  variant?: 'primary' | 'secondary'
  onClick?: () => void
  navigation?: 'client' | 'document'
}

function scrollToElement(id: string) {
  const element = document.getElementById(id)
  if (element) {
    const offsetPosition = element.getBoundingClientRect().top + window.pageYOffset - 100
    window.scrollTo({ top: offsetPosition, behavior: 'smooth' })
  }
}

export function CTA({ text, href, variant = 'primary', onClick, navigation = 'client' }: CTAProps) {
  const router = useRouter()
  const className = `${variant === 'primary' ? 'btn btn-primary' : 'btn btn-secondary'} btn-organic`
  const normalizedHref = href.trim()
  const schemeProbe = normalizedHref.replace(/[\u0000-\u0020]+/g, '')

  const isExternal = normalizedHref.startsWith('http://') || normalizedHref.startsWith('https://')
  const isFile = /\.(pdf|doc|docx|xls|xlsx|zip)$/i.test(normalizedHref)
  const explicitScheme = schemeProbe.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase()
  const isAllowedProtocolLink = explicitScheme === 'mailto' || explicitScheme === 'tel'
  const isUnsafeProtocolLink = Boolean(explicitScheme && !['http', 'https', 'mailto', 'tel'].includes(explicitScheme))

  if (isUnsafeProtocolLink) {
    return <button type="button" className={className} disabled>{text}</button>
  }

  if (isAllowedProtocolLink || navigation === 'document') {
    return <a className={className} href={normalizedHref} onClick={onClick}>{text}</a>
  }

  const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault()
    if (onClick) {
      onClick()
    }

    if (isExternal || isFile) {
      window.open(normalizedHref, '_blank', 'noopener,noreferrer')
      return
    }

    if (normalizedHref.startsWith('#')) {
      scrollToElement(normalizedHref.substring(1))
      return
    }

    router.push(normalizedHref)
    setTimeout(() => {
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }, 100)
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
