import Image from 'next/image'

import { ReactNode } from 'react'
import { Button } from '@/components/ui/Button'

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
                <Button as="a" href="/gemuese" variant="primary">
                  Welche Gemüse haben Saison
                </Button>
                <Button as="a" href="/wir" variant="secondary">
                  Lerne uns kennen
                </Button>
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
                sizes="(max-width: 768px) 100vw, 50vw"
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
