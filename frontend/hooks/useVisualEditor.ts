'use client'

import { useState, useEffect, useCallback, useRef } from 'react'
import { usePathname } from 'next/navigation'
import type { ContentSection } from '@/lib/processwire-types'

const MSG_PREFIX = 'bioco:visual-editor:'

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

function isInIframe(): boolean {
  try {
    return typeof window !== 'undefined' && window.parent !== window
  } catch {
    return false
  }
}

function sendToParent(type: string, data: Record<string, unknown> = {}) {
  if (!isInIframe()) return
  try {
    window.parent.postMessage({ type: `${MSG_PREFIX}${type}`, ...data }, '*')
  } catch {
    // cross-origin or unavailable
  }
}

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

  // Send ready message on mount
  useEffect(() => {
    if (!enabled) return
    sendToParent('ready', {
      path: pathname || '/',
      sectionIds: sectionsRef.current.map(s => s.id),
    })
  }, [enabled, pathname])

  // Listen for messages from PW admin
  useEffect(() => {
    if (!enabled) return

    function handleMessage(event: MessageEvent) {
      const data = event.data
      if (!data || typeof data.type !== 'string' || !data.type.startsWith(MSG_PREFIX)) return

      const action = data.type.slice(MSG_PREFIX.length)

      switch (action) {
        case 'section-update': {
          const { sectionId, field, value } = data
          if (!sectionId || !field) return
          setSections(prev =>
            prev.map(s =>
              s.id === sectionId ? { ...s, [field]: value } : s
            )
          )
          break
        }
        case 'section-highlight': {
          setHighlightedSectionId(data.sectionId || null)
          break
        }
        case 'save-state': {
          setMode(data.mode === 'browse' ? 'browse' : 'edit')
          break
        }
        case 'sections-replace': {
          if (Array.isArray(data.sections)) {
            setSections(data.sections)
          }
          break
        }
      }
    }

    window.addEventListener('message', handleMessage)
    return () => window.removeEventListener('message', handleMessage)
  }, [enabled])

  const handleSectionClick = useCallback((sectionId: string) => {
    if (!enabled || mode !== 'edit') return
    const section = sectionsRef.current.find(s => s.id === sectionId)
    if (!section) return
    sendToParent('section-click', { sectionId, section })
  }, [enabled, mode])

  // Handle scroll-to-section messages
  useEffect(() => {
    if (!enabled) return

    function handleMessage(event: MessageEvent) {
      const data = event.data
      if (!data || data.type !== `${MSG_PREFIX}section-scroll`) return
      const el = document.querySelector(`[data-section-id="${data.sectionId}"]`)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }

    window.addEventListener('message', handleMessage)
    return () => window.removeEventListener('message', handleMessage)
  }, [enabled])

  return {
    sections: enabled ? sections : initialSections,
    highlightedSectionId: enabled ? highlightedSectionId : null,
    handleSectionClick,
  }
}
