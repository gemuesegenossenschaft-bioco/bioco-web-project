'use client'

import { useEffect } from 'react'
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
          zIndex: 1000,
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
          zIndex: 1001,
          boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div style={{
          padding: '24px',
          borderBottom: '1px solid var(--border-color)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start'
        }}>
          <div style={{ flex: 1 }}>
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

        {/* Content */}
        <div style={{ padding: '24px' }}>
          {item.fullDescription && (
            <p style={{ 
              marginBottom: '16px',
              lineHeight: 1.7,
              color: 'var(--text-secondary)'
            }}>
              {item.fullDescription}
            </p>
          )}

          {(item.location || item.time) && (
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
              {item.time && (
                <div>
                  <strong>Zeit:</strong> {item.time}
                </div>
              )}
            </div>
          )}

          {item.type === 'event' && item.signupRequired && (
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









