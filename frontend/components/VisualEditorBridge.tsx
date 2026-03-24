'use client'

import { useEffect, useState, useRef, useCallback } from 'react'
import { useSearchParams, usePathname } from 'next/navigation'

const MSG_PREFIX = 'bioco:visual-editor:'

interface SectionInfo {
  id: string
  pwId?: number
  sort?: number
  title: string
  layout?: string
  text?: string
}

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

/**
 * Global visual editor bridge. Lives in root layout.
 * When ?_visual=1 is active and page is in an iframe:
 * - Fetches sections from CMS API for current path
 * - Sends ready message with section list
 * - Adds data-section-id attributes to DOM sections
 * - Handles click delegation for section selection
 * - Listens for highlight/scroll messages from parent
 */
export function VisualEditorBridge() {
  const searchParams = useSearchParams()
  const pathname = usePathname()
  const isVisualEditor = searchParams.get('_visual') === '1'
  const [sections, setSections] = useState<SectionInfo[]>([])
  const [highlightedId, setHighlightedId] = useState<string | null>(null)
  const sectionsRef = useRef<SectionInfo[]>([])

  // Fetch sections from API when path changes
  useEffect(() => {
    if (!isVisualEditor || !isInIframe()) return

    const path = pathname === '/' ? '' : pathname.replace(/^\//, '')
    const cmsBase = process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL || 'https://cms.bioco.ch'
    const apiUrl = path
      ? `${cmsBase}/api/content/sections?path=${encodeURIComponent(path)}`
      : `${cmsBase}/api/content/homepage`

    fetch(apiUrl)
      .then(r => r.json())
      .then(data => {
        const secs: SectionInfo[] = (data.sections || []).map((s: Record<string, unknown>) => ({
          id: s.id,
          pwId: s.pwId,
          sort: s.sort,
          title: s.title || '',
          layout: s.layout || '',
          text: typeof s.text === 'string' ? s.text.slice(0, 200) : '',
        }))
        setSections(secs)
        sectionsRef.current = secs
        sendToParent('ready', { sectionIds: secs.map(s => s.id) })
      })
      .catch(() => {
        // API not available, try to scan DOM
        scanDomSections()
      })
  }, [isVisualEditor, pathname])

  // Scan DOM for section elements and add data attributes
  const scanDomSections = useCallback(() => {
    // Look for elements that represent sections: .section-block, [id^="section-"], main > section, etc.
    const candidates = document.querySelectorAll(
      'main section, main > div > section, [class*="section"], [id^="section-"]'
    )
    const found: SectionInfo[] = []
    candidates.forEach((el, i) => {
      const id = el.getAttribute('data-section-id') || el.id || `dom-section-${i}`
      if (!el.getAttribute('data-section-id')) {
        el.setAttribute('data-section-id', id)
      }
      const heading = el.querySelector('h1, h2, h3')
      found.push({
        id,
        title: heading?.textContent?.trim() || `Abschnitt ${i + 1}`,
        layout: el.getAttribute('data-section-layout') || undefined,
      })
    })
    if (found.length > 0) {
      setSections(found)
      sectionsRef.current = found
      sendToParent('ready', { sectionIds: found.map(s => s.id) })
    }
  }, [])

  // Add data-section-id attributes once sections are known
  useEffect(() => {
    if (!isVisualEditor || sections.length === 0) return

    // Try to match sections to DOM elements
    sections.forEach(sec => {
      if (document.querySelector(`[data-section-id="${sec.id}"]`)) return
      // Try matching by heading text
      const headings = document.querySelectorAll('main h1, main h2, main h3')
      headings.forEach(h => {
        if (h.textContent?.trim() === sec.title) {
          const sectionEl = h.closest('section') || h.parentElement?.closest('div[class]')
          if (sectionEl && !sectionEl.getAttribute('data-section-id')) {
            sectionEl.setAttribute('data-section-id', sec.id)
            if (sec.layout) sectionEl.setAttribute('data-section-layout', sec.layout)
          }
        }
      })
    })
  }, [isVisualEditor, sections])

  // Inject visual editor CSS
  useEffect(() => {
    if (!isVisualEditor || !isInIframe()) return
    const style = document.createElement('style')
    style.id = 've-bridge-styles'
    style.textContent = `
      [data-section-id] {
        position: relative;
        cursor: pointer;
        transition: outline 0.15s;
      }
      [data-section-id]:hover {
        outline: 2px dashed rgba(74, 124, 89, 0.6);
        outline-offset: -2px;
      }
      [data-section-id]:hover::after {
        content: attr(data-section-layout);
        position: absolute;
        top: 4px;
        right: 4px;
        background: rgba(74, 124, 89, 0.9);
        color: #fff;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 4px;
        z-index: 10;
        pointer-events: none;
      }
    `
    document.head.appendChild(style)
    return () => { style.remove() }
  }, [isVisualEditor])

  // Update highlight CSS
  useEffect(() => {
    if (!isVisualEditor) return
    const existing = document.getElementById('ve-highlight-style')
    if (existing) existing.remove()
    if (!highlightedId) return
    const style = document.createElement('style')
    style.id = 've-highlight-style'
    style.textContent = `
      [data-section-id="${highlightedId}"] {
        outline: 3px solid #4a7c59 !important;
        outline-offset: -3px !important;
      }
    `
    document.head.appendChild(style)
    return () => { style.remove() }
  }, [isVisualEditor, highlightedId])

  // Click delegation
  useEffect(() => {
    if (!isVisualEditor || !isInIframe()) return

    function onClick(e: MouseEvent) {
      const target = (e.target as HTMLElement).closest('[data-section-id]')
      if (!target) return
      const sectionId = target.getAttribute('data-section-id')
      if (!sectionId) return
      e.preventDefault()
      e.stopPropagation()
      const section = sectionsRef.current.find(s => s.id === sectionId)
      sendToParent('section-click', {
        sectionId,
        section: section || { id: sectionId, title: target.querySelector('h1,h2,h3')?.textContent || sectionId },
      })
    }

    document.addEventListener('click', onClick, true)
    return () => document.removeEventListener('click', onClick, true)
  }, [isVisualEditor])

  // Listen for messages from parent (highlight, scroll, update)
  useEffect(() => {
    if (!isVisualEditor || !isInIframe()) return

    function handleMessage(event: MessageEvent) {
      const data = event.data
      if (!data || typeof data.type !== 'string' || !data.type.startsWith(MSG_PREFIX)) return
      const action = data.type.slice(MSG_PREFIX.length)

      switch (action) {
        case 'section-highlight':
          setHighlightedId(data.sectionId || null)
          break
        case 'section-scroll': {
          const el = document.querySelector(`[data-section-id="${data.sectionId}"]`)
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
          break
        }
        case 'section-update': {
          const { sectionId, field, value } = data
          if (!sectionId || !field) return
          // Update DOM directly for live preview
          const el = document.querySelector(`[data-section-id="${sectionId}"]`)
          if (!el) return
          if (field === 'title') {
            const heading = el.querySelector('h1, h2, h3')
            if (heading) heading.textContent = value
          } else if (field === 'text') {
            const textEl = el.querySelector('p, .section-text, [class*="text"]')
            if (textEl) textEl.innerHTML = value
          }
          break
        }
      }
    }

    window.addEventListener('message', handleMessage)
    return () => window.removeEventListener('message', handleMessage)
  }, [isVisualEditor])

  // Nothing to render
  return null
}
