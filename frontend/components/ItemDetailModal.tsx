'use client'

import { useEffect } from 'react'
import Image from 'next/image'
import Link from 'next/link'
import { AktuellesItem } from './AktuellesData'
import { EventSignupForm } from './EventSignupForm'

interface ItemDetailModalProps {
  item: AktuellesItem | null
  isOpen: boolean
  onClose: () => void
}

export function ItemDetailModal({ item, isOpen, onClose }: ItemDetailModalProps) {
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = 'unset'
    }
    return () => {
      document.body.style.overflow = 'unset'
    }
  }, [isOpen])

  if (!isOpen || !item) return null

  const handleSignupSuccess = () => {
    onClose()
  }

  return (
    <>
      {/* Overlay */}
      <div
        onClick={onClose}
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(0, 0, 0, 0.5)',
          zIndex: 3000,
          backdropFilter: 'blur(4px)'
        }}
      />
      
      {/* Modal */}
      <div
        style={{
          position: 'fixed',
          top: '50%',
          left: '50%',
          transform: 'translate(-50%, -50%)',
          background: 'white',
          borderRadius: '16px',
          maxWidth: '600px',
          width: '90%',
          maxHeight: '90vh',
          overflow: 'auto',
          zIndex: 3001,
          boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Sticky Navigation Bar */}
        <div style={{
          position: 'sticky',
          top: 0,
          background: 'white',
          borderBottom: '1px solid var(--border-color)',
          padding: '12px 24px',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          zIndex: 10
        }}>
          <Link href="/" onClick={onClose}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src="/images/bioco-logo.png"
              alt="biocò Logo"
              style={{ height: 'auto', width: 'auto', maxHeight: '36px' }}
            />
          </Link>
          <button
            onClick={onClose}
            style={{
              background: 'transparent',
              border: 'none',
              fontSize: '1.5rem',
              cursor: 'pointer',
              padding: '4px 8px',
              color: 'var(--text-secondary)',
              lineHeight: 1
            }}
            aria-label="Schließen"
          >
            ×
          </button>
        </div>

        {/* Header */}
        <div style={{
          padding: '24px',
          borderBottom: '1px solid var(--border-color)'
        }}>
          <div style={{ 
            fontSize: '0.875rem', 
            color: 'var(--text-secondary)',
            marginBottom: '8px'
          }}>
            {item.date}
          </div>
          <h2 style={{ 
            margin: 0, 
            fontSize: '1.5rem',
            color: 'var(--text-primary)'
          }}>
            {item.title}
          </h2>
        </div>

        {/* Content */}
        <div style={{ padding: '24px' }}>
          {item.fullDescription && (
            <div
              style={{
                marginBottom: '16px',
                lineHeight: 1.7,
                color: 'var(--text-secondary)',
              }}
              dangerouslySetInnerHTML={{ __html: item.fullDescription }}
            />
          )}

          {(item.location || item.timeLabel || item.time) && (
            <div style={{
              padding: '16px',
              background: 'var(--bg-secondary)',
              borderRadius: '8px',
              marginBottom: '16px'
            }}>
              {item.location && (
                <div style={{ marginBottom: '8px' }}>
                  <strong>Ort:</strong> {item.location}
                </div>
              )}
              {(item.timeLabel || item.time) && (
                <div>
                  <strong>Zeit:</strong> {item.timeLabel || item.time}
                </div>
              )}
            </div>
          )}

          {item.media && item.media.length > 0 && (
            <div className="event-media-grid">
              {item.media.map((media, index) => (
                <figure key={media.url + index}>
                  {media.type === 'image' ? (
                    <Image
                      src={media.url}
                      alt={media.description || item.title}
                      width={480}
                      height={320}
                      style={{ width: '100%', height: 'auto', borderRadius: '8px' }}
                    />
                  ) : (
                    <video
                      src={media.url}
                      controls
                      style={{ width: '100%', borderRadius: '8px', background: '#000' }}
                    />
                  )}
                  {media.description && <figcaption>{media.description}</figcaption>}
                </figure>
              ))}
            </div>
          )}

          {item.signupNotes && (
            <div style={{
              padding: '12px 16px',
              background: 'var(--bg-secondary)',
              borderRadius: '8px',
              marginBottom: '16px',
              fontSize: '0.9rem',
              color: 'var(--text-secondary)',
            }}>
              <strong>Hinweis:</strong> {item.signupNotes}
            </div>
          )}

          {item.type === 'event' && item.signupEnabled === true && (
            <div style={{ marginTop: '24px', borderTop: '1px solid var(--border-color)', paddingTop: '24px' }}>
              <EventSignupForm
                eventTitle={item.title}
                eventId={item.id}
                onSuccess={handleSignupSuccess}
                onCancel={onClose}
              />
            </div>
          )}
        </div>
      </div>
    </>
  )
}









