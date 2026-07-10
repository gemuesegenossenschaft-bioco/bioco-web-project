'use client'

import type { CSSProperties, MouseEvent, ReactNode } from 'react'

export type ModalVariant = 'schnuppertage' | 'item-detail'

interface ModalProps {
  open: boolean
  onClose: () => void
  variant: ModalVariant
  header: ReactNode
  children: ReactNode
}

const OVERLAY_STYLE: Record<ModalVariant, CSSProperties> = {
  schnuppertage: {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    background: 'rgba(0, 0, 0, 0.5)',
    zIndex: 1000,
    backdropFilter: 'blur(4px)',
  },
  'item-detail': {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    background: 'rgba(0, 0, 0, 0.5)',
    zIndex: 3000,
    backdropFilter: 'blur(4px)',
  },
}

const PANEL_STYLE: Record<ModalVariant, CSSProperties> = {
  schnuppertage: {
    position: 'fixed',
    top: '50%',
    left: '50%',
    transform: 'translate(-50%, -50%)',
    background: 'white',
    borderRadius: '16px',
    maxWidth: '500px',
    width: '90%',
    maxHeight: '90vh',
    overflow: 'auto',
    zIndex: 1001,
    boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
  },
  'item-detail': {
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
    boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
  },
}

const HEADER_STYLE: Record<ModalVariant, CSSProperties> = {
  schnuppertage: {
    position: 'sticky',
    top: 0,
    background: 'white',
    borderBottom: '1px solid var(--border-color)',
    padding: 'var(--space-4) var(--space-5)',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    zIndex: 10,
  },
  'item-detail': {
    position: 'sticky',
    top: 0,
    background: 'white',
    borderBottom: '1px solid var(--border-color)',
    padding: '12px 24px',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    zIndex: 10,
  },
}

const CLOSE_BUTTON_STYLE: Record<ModalVariant, CSSProperties> = {
  schnuppertage: {
    background: 'transparent',
    border: 'none',
    fontSize: '1.5rem',
    cursor: 'pointer',
    padding: 'var(--space-1) var(--space-2)',
    color: 'var(--text-secondary)',
    lineHeight: 1,
  },
  'item-detail': {
    background: 'transparent',
    border: 'none',
    fontSize: '1.5rem',
    cursor: 'pointer',
    padding: '4px 8px',
    color: 'var(--text-secondary)',
    lineHeight: 1,
    width: '36px',
    flexShrink: 0,
  },
}

function stopPropagation(event: MouseEvent<HTMLDivElement>) {
  event.stopPropagation()
}

export function Modal({ open, onClose, variant, header, children }: ModalProps) {
  if (!open) return null

  return (
    <>
      <div onClick={onClose} style={OVERLAY_STYLE[variant]} />
      <div style={PANEL_STYLE[variant]} onClick={stopPropagation}>
        <div style={HEADER_STYLE[variant]}>
          {header}
          <button onClick={onClose} style={CLOSE_BUTTON_STYLE[variant]} aria-label="Schließen">
            ×
          </button>
        </div>
        {children}
      </div>
    </>
  )
}
