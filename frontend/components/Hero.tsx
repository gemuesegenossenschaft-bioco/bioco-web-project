import Link from 'next/link'
import Image from 'next/image'

import { ReactNode } from 'react'

interface HeroProps {
  title: ReactNode
  subtitle?: string
  image?: {
    url: string
    description: string
  }
}

export function Hero({ title, subtitle, image }: HeroProps) {
  return (
    <section id="hero" className="hero">
      <div className="hero-container">
        <div className="hero-text-card bento-card">
          <div className="hero-content">
            <div className="hero-text">
              {subtitle && (
                <p className="hero-subtitle">{subtitle}</p>
              )}
              <h1 className="hero-title">{title}</h1>
              <div className="hero-buttons">
                <Link href="/gemuese" className="btn btn-primary">
                  Was wächst gerade
                </Link>
                <Link href="/wir" className="btn btn-secondary">
                  Lerne uns kennen
                </Link>
              </div>
            </div>
          </div>
        </div>
        {image && (
          <div className="hero-image-card bento-card">
            <div className="hero-image-container">
              <Image
                src={image.url}
                alt={image.description}
                fill
                style={{ objectFit: 'cover' }}
                priority
              />
            </div>
          </div>
        )}
      </div>
    </section>
  )
}
