'use client'

import Image from 'next/image'
import Link from 'next/link'
import { AktuellesItem } from './AktuellesData'

interface AktuellesItemProps {
  item: AktuellesItem
  variant?: 'aktuelles' | 'event'
  onClick?: (item: AktuellesItem) => void
}

export function AktuellesItemComponent({ item, variant = 'aktuelles', onClick }: AktuellesItemProps) {
  // Determine variant based on item type
  const displayVariant = item.type === 'instagram' ? 'aktuelles' : (variant || 'aktuelles')
  const className = displayVariant === 'event' ? 'event-item' : 'aktuelles-item'
  const isInstagram = item.type === 'instagram'
  const canRegister = item.type === 'event' && (item.signupEnabled ?? item.signupRequired ?? false)
  const hasDetails =
    item.fullDescription ||
    item.location ||
    item.time ||
    item.timeLabel ||
    canRegister ||
    isInstagram ||
    (item.media && item.media.length > 0)
  
  const handleClick = () => {
    if (isInstagram && item.instagram_url) {
      // Open Instagram link in new tab
      window.open(item.instagram_url, '_blank', 'noopener,noreferrer')
      return
    }
    if (hasDetails && onClick) {
      onClick(item)
    }
  }
  
  return (
    <div 
      className={className}
      onClick={handleClick}
      style={{
        cursor: hasDetails ? 'pointer' : 'default',
        transition: 'all 0.2s ease',
        position: 'relative'
      }}
      onMouseEnter={(e) => {
        if (hasDetails) {
          e.currentTarget.style.transform = 'translateY(-2px)'
          e.currentTarget.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
        }
      }}
      onMouseLeave={(e) => {
        if (hasDetails) {
          e.currentTarget.style.transform = 'translateY(0)'
          e.currentTarget.style.boxShadow = '0 1px 3px 0 rgba(0, 0, 0, 0.1)'
        }
      }}
    >
      {isInstagram && item.imageUrl && (
        <div style={{ 
          marginBottom: '12px', 
          borderRadius: '8px', 
          overflow: 'hidden',
          position: 'relative',
          width: '100%',
          aspectRatio: '1',
          backgroundColor: '#f5f5f5'
        }}>
          <Image
            src={item.imageUrl}
            alt={item.title}
            fill
            style={{ objectFit: 'cover' }}
            sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
          />
          <div style={{
            position: 'absolute',
            top: '8px',
            right: '8px',
            background: 'rgba(0, 0, 0, 0.6)',
            borderRadius: '4px',
            padding: '4px 8px',
            fontSize: '0.75rem',
            color: 'white',
            display: 'flex',
            alignItems: 'center',
            gap: '4px'
          }}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
            </svg>
            Instagram
          </div>
        </div>
      )}
      <h3>{item.date}</h3>
      <p><strong>{item.title}</strong></p>
      <p>{item.description}</p>
      {hasDetails && (
        <p style={{ 
          marginTop: '8px', 
          fontSize: '0.875rem', 
          color: 'var(--bioco-green)',
          fontWeight: 600
        }}>
          {isInstagram ? 'Auf Instagram ansehen →' : 'Mehr erfahren →'}
        </p>
      )}
    </div>
  )
}

