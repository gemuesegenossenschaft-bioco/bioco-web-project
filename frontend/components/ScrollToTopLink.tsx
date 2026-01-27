'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'

interface ScrollToTopLinkProps {
  href: string
  className?: string
  style?: React.CSSProperties
  children: React.ReactNode
}

export function ScrollToTopLink({ href, className, style, children }: ScrollToTopLinkProps) {
  const router = useRouter()

  const handleClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault()
    router.push(href)
    // Wait for navigation to complete, then scroll to top
    setTimeout(() => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      })
    }, 100)
  }

  return (
    <Link
      href={href}
      className={className}
      style={style}
      onClick={handleClick}
    >
      {children}
    </Link>
  )
}
