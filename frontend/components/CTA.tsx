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
  const className = variant === 'primary' ? 'btn btn-primary' : 'btn btn-secondary'
  
  const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault()
    if (onClick) {
      onClick()
    }
    router.push(href)
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