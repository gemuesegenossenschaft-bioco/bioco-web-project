'use client'

import { useState, useEffect, useCallback, useRef } from 'react'
import { usePathname } from 'next/navigation'
import type { ContentSection } from '@/lib/processwire-types'
import { applyVisualEditorFieldChange } from '@/lib/visualEditorContract'
import { useIframeChannel } from '@/lib/visual-editor/useIframeChannel'
import type { MessageOfType, ParentToIframeMessage } from '@/lib/visual-editor/protocol'

interface UseVisualEditorOptions {
  enabled: boolean
  sections: ContentSection[]
}

interface UseVisualEditorReturn {
  sections: ContentSection[]
  highlightedSectionId: string | null
  handleSectionClick: (sectionId: string) => void
}

type VisualEditorMode = 'edit' | 'browse'

export function useVisualEditor({ enabled, sections: initialSections }: UseVisualEditorOptions): UseVisualEditorReturn {
  const [sections, setSections] = useState<ContentSection[]>(initialSections)
  const [highlightedSectionId, setHighlightedSectionId] = useState<string | null>(null)
  const [mode, setMode] = useState<VisualEditorMode>('edit')
  const sectionsRef = useRef(initialSections)
  const pathname = usePathname()

  // Keep state in sync with server-rendered section data.
  useEffect(() => {
    sectionsRef.current = initialSections
    setSections(initialSections)
  }, [initialSections])

  useEffect(() => {
    sectionsRef.current = sections
  }, [sections])

  // Inbound messages arrive already origin-validated and structurally parsed by
  // the shared iframe channel (fail-closed against a non-allowlisted origin);
  // only parent->iframe types reach this handler.
  const handleParentMessage = useCallback((message: ParentToIframeMessage) => {
    switch (message.type) {
      case 'section-update': {
        const { sectionId, field, value } = message
        setSections(prev =>
          prev.map(s =>
            s.id === sectionId ? applyVisualEditorFieldChange(s, { field, value }) : s
          )
        )
        break
      }
      case 'section-highlight': {
        setHighlightedSectionId(message.sectionId || null)
        break
      }
      case 'save-state': {
        setMode(message.mode === 'browse' ? 'browse' : 'edit')
        break
      }
      case 'sections-replace': {
        // Entries are validated (records with a non-empty string id) by the
        // protocol parser before delivery.
        setSections(message.sections as ContentSection[])
        break
      }
      case 'section-scroll': {
        const el = document.querySelector(`[data-section-id="${message.sectionId}"]`)
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        break
      }
    }
  }, [])

  const { send } = useIframeChannel({ enabled, onMessage: handleParentMessage })

  // Send ready message on mount / when the pathname changes. Outbound posts
  // target the adopted parent origin (or broadcast across the allowlist), never '*'.
  useEffect(() => {
    if (!enabled) return
    send('ready', { path: pathname || '/' })
  }, [enabled, pathname, send])

  const handleSectionClick = useCallback((sectionId: string) => {
    if (!enabled || mode !== 'edit') return
    const section = sectionsRef.current.find(s => s.id === sectionId)
    if (!section) return
    // `section` is carried alongside `sectionId` for the parent shell; the
    // wire encoder passes it through unchanged.
    send('section-click', { sectionId, section } as Omit<MessageOfType<'section-click'>, 'type'>)
  }, [enabled, mode, send])

  return {
    sections: enabled ? sections : initialSections,
    highlightedSectionId: enabled ? highlightedSectionId : null,
    handleSectionClick,
  }
}
