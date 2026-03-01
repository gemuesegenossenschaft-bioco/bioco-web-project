'use client'

import { useState } from 'react'
import type { ContentSection } from '@/lib/processwire-types'

interface EditPanelProps {
  section: ContentSection
  onSave: (fields: Record<string, string>) => void
  onClose: () => void
}

export function EditPanel({ section, onSave, onClose }: EditPanelProps) {
  const [title, setTitle] = useState(section.title || '')
  const [text, setText] = useState(section.text || '')

  const handleSubmit = () => {
    onSave({
      section_title: title,
      section_text: text,
    })
  }

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        right: 0,
        width: '400px',
        height: '100vh',
        background: '#fff',
        boxShadow: '-4px 0 20px rgba(0,0,0,0.15)',
        zIndex: 1000,
        padding: '24px',
        overflow: 'auto',
      }}
    >
      <h3 style={{ marginBottom: '16px' }}>Abschnitt bearbeiten</h3>
      <div style={{ marginBottom: '16px' }}>
        <label htmlFor="edit-title" style={{ display: 'block', marginBottom: '4px', fontWeight: 500 }}>
          Titel
        </label>
        <input
          id="edit-title"
          type="text"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          style={{
            width: '100%',
            padding: '8px',
            border: '1px solid #ddd',
            borderRadius: '4px',
            fontSize: '1rem',
          }}
        />
      </div>
      <div style={{ marginBottom: '24px' }}>
        <label htmlFor="edit-text" style={{ display: 'block', marginBottom: '4px', fontWeight: 500 }}>
          Text
        </label>
        <textarea
          id="edit-text"
          value={text}
          onChange={(e) => setText(e.target.value)}
          rows={8}
          style={{
            width: '100%',
            padding: '8px',
            border: '1px solid #ddd',
            borderRadius: '4px',
            fontSize: '1rem',
            fontFamily: 'inherit',
          }}
        />
      </div>
      <div style={{ display: 'flex', gap: '8px' }}>
        <button
          onClick={handleSubmit}
          style={{
            background: '#4a7c59',
            color: '#fff',
            border: 'none',
            borderRadius: '6px',
            padding: '8px 16px',
            cursor: 'pointer',
          }}
        >
          Speichern
        </button>
        <button
          onClick={onClose}
          style={{
            background: '#eee',
            border: 'none',
            borderRadius: '6px',
            padding: '8px 16px',
            cursor: 'pointer',
          }}
        >
          Abbrechen
        </button>
      </div>
    </div>
  )
}
