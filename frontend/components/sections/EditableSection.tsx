'use client'

import { useState, type ReactNode } from 'react'
import { EditPanel } from './EditPanel'
import type { ContentSection } from '@/lib/processwire-types'

interface EditableSectionProps {
  section: ContentSection
  isEditing: boolean
  children: ReactNode
}

export function EditableSection({ section, isEditing, children }: EditableSectionProps) {
  const [showPanel, setShowPanel] = useState(false)

  const handleSave = async (fields: Record<string, string>) => {
    try {
      await fetch('/api/content-save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ sectionId: section.id, fields }),
      })
      setShowPanel(false)
      window.location.reload()
    } catch (err) {
      console.error('Save failed:', err)
    }
  }

  return (
    <div style={{ position: 'relative' }}>
      {children}
      {isEditing && (
        <button
          onClick={() => setShowPanel(true)}
          style={{
            position: 'absolute',
            top: 8,
            right: 8,
            background: 'rgba(74, 124, 89, 0.9)',
            color: '#fff',
            border: 'none',
            borderRadius: '6px',
            padding: '6px 12px',
            cursor: 'pointer',
            fontSize: '0.8rem',
            zIndex: 10,
          }}
        >
          Bearbeiten
        </button>
      )}
      {showPanel && (
        <EditPanel
          section={section}
          onSave={handleSave}
          onClose={() => setShowPanel(false)}
        />
      )}
    </div>
  )
}
