'use client'

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import {
  formatComponentDisplayName,
  getComponentRegistry,
  getComponentConfigSchema,
  getResolvedComponentConfig,
  resolveComponentRegistryEntry,
} from '@/lib/componentRegistry'
import type { ContentSection } from '@/lib/processwire-types'
import { useIframeChannel } from '@/lib/visual-editor/useIframeChannel'
import type { IframeToParentMessage, ParentToIframeMessage } from '@/lib/visual-editor/protocol'

type EditorMode = 'edit' | 'browse'

interface SelectedField {
  sectionId: string
  field: string
  kind: string
  inline: boolean
  buttonIndex?: number
  targetField?: string
}

interface InlineVisualEditorRuntimeProps {
  enabled: boolean
  sections: ContentSection[]
}

function escapeSelector(value: string): string {
  if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
    return CSS.escape(value)
  }
  return value.replace(/["\\]/g, '\\$&')
}

function getShortTextValue(section: ContentSection | null, selectedField: SelectedField | null): string {
  if (!section || !selectedField) return ''
  if (selectedField.kind === 'button') {
    return section.buttons?.[selectedField.buttonIndex || 0]?.text || ''
  }
  if (selectedField.field === 'title') return section.title || ''
  if (selectedField.field === 'eyebrow') return section.eyebrow || ''
  if (selectedField.field === 'videoTitle') return section.video?.title || ''
  return ''
}

function getRichTextValue(section: ContentSection | null): string {
  return section?.text || ''
}

function getFieldLabel(selectedField: SelectedField | null): string {
  if (!selectedField) return ''
  if (selectedField.kind === 'button') return 'Button'
  if (selectedField.sectionId === '__hero__') {
    if (selectedField.field === 'title') return 'Hero Titel'
    if (selectedField.field === 'eyebrow') return 'Hero Untertitel'
    if (selectedField.field === 'media') return 'Hero Bild'
  }
  switch (selectedField.field) {
    case 'title':
      return 'Titel'
    case 'eyebrow':
      return 'Eyebrow'
    case 'text':
      return 'Text'
    case 'media':
      return 'Bild'
    case 'component':
      return 'Komponente'
    case 'video':
      return 'Video'
    case 'videoTitle':
      return 'Video Titel'
    default:
      return selectedField.field
  }
}

export function InlineVisualEditorRuntime({ enabled, sections }: InlineVisualEditorRuntimeProps) {
  const [mode, setMode] = useState<EditorMode>('edit')
  const [selectedSectionId, setSelectedSectionId] = useState<string | null>(null)
  const [selectedField, setSelectedField] = useState<SelectedField | null>(null)
  const [inspectorOpen, setInspectorOpen] = useState(true)
  const [saveState, setSaveState] = useState({ dirty: false, saving: false, busy: false, busyLabel: '', message: '' })
  const [presetTagsByComponent, setPresetTagsByComponent] = useState<Record<string, string[]>>({})
  const [anchorRect, setAnchorRect] = useState<DOMRect | null>(null)
  const shortTextRef = useRef<HTMLDivElement | null>(null)
  const sectionToolbarRef = useRef<HTMLButtonElement | null>(null)
  const componentInputRef = useRef<HTMLInputElement | null>(null)
  const mediaAltRef = useRef<HTMLInputElement | null>(null)
  const selectedFieldRef = useRef<SelectedField | null>(null)
  const shortTextValueRef = useRef('')
  const shortTextChangeTimerRef = useRef<number | null>(null)
  const richTextValueRef = useRef('')
  const richTextChangeTimerRef = useRef<number | null>(null)
  const richTextSyncingRef = useRef(false)

  // Inbound messages are origin-validated + structurally parsed by the shared
  // iframe channel before reaching this handler (fail-closed against a
  // non-allowlisted origin); only parent->iframe types arrive here.
  const handleParentMessage = useCallback((message: ParentToIframeMessage) => {
    switch (message.type) {
      case 'save-state':
        setMode(message.mode === 'browse' ? 'browse' : 'edit')
        setSaveState({
          dirty: message.dirty,
          saving: message.saving,
          busy: message.busy,
          busyLabel: message.busyLabel,
          message: message.message,
        })
        setPresetTagsByComponent(message.presetTagsByComponent)
        setSelectedSectionId(message.selectedSectionId)
        break
      case 'section-highlight':
        setSelectedSectionId(message.sectionId || null)
        setInspectorOpen(!!message.sectionId)
        if (!message.sectionId) {
          setSelectedField(null)
        }
        break
      case 'field-highlight':
        setSelectedSectionId(message.sectionId)
        setSelectedField({
          sectionId: message.sectionId,
          field: message.field,
          kind: message.kind,
          inline: message.inline,
          buttonIndex: message.buttonIndex,
          targetField: message.targetField,
        })
        setInspectorOpen(true)
        break
      case 'field-reset':
        setSelectedField(null)
        setInspectorOpen(!!selectedSectionId)
        break
      case 'save-result':
        setSaveState((current) => ({
          ...current,
          message: message.success ? 'Publiziert' : String(message.error || 'Publizieren fehlgeschlagen'),
        }))
        break
    }
  }, [selectedSectionId])

  const { send } = useIframeChannel({ enabled, onMessage: handleParentMessage })

  // Ergonomic adapter preserving every existing call site: the shared channel's
  // `send` targets the adopted parent origin (or broadcasts across the
  // allowlist), never '*'. Types stay loose here because these payloads carry
  // possibly-null section ids the strict per-message types would reject.
  const sendToParent = useCallback(
    (type: IframeToParentMessage['type'], data: Record<string, unknown> = {}) => {
      ;(send as (t: IframeToParentMessage['type'], p: Record<string, unknown>) => void)(type, data)
    },
    [send]
  )

  const selectedSection = useMemo(() => {
    return sections.find((section) => section.id === selectedSectionId) || null
  }, [sections, selectedSectionId])
  const isHeroSection = selectedSectionId === '__hero__' || selectedSection?.layout === 'hero'

  const selectedComponentMeta = useMemo(() => {
    return resolveComponentRegistryEntry(selectedSection?.component || '')
  }, [selectedSection?.component])
  const selectedComponentSchema = useMemo(() => {
    return getComponentConfigSchema(selectedSection?.component || '')
  }, [selectedSection?.component])
  const selectedComponentConfig = useMemo(() => {
    return getResolvedComponentConfig(selectedSection?.component || '', selectedSection?.config || {})
  }, [selectedSection?.component, selectedSection?.config])
  const componentCandidates = useMemo(() => {
    return getComponentRegistry().map((entry) => ({
      key: entry.key,
      label: entry.label,
    }))
  }, [])

  const editor = useEditor({
    extensions: [StarterKit],
    content: '',
    immediatelyRender: false,
    editable: false,
    onUpdate: ({ editor }) => {
      const activeField = selectedFieldRef.current
      if (!activeField || activeField.field !== 'text') return
      if (richTextSyncingRef.current) return
      const value = editor.getHTML()
      richTextValueRef.current = value
      if (richTextChangeTimerRef.current) {
        window.clearTimeout(richTextChangeTimerRef.current)
      }
      richTextChangeTimerRef.current = window.setTimeout(() => {
        sendToParent('field-change', {
          sectionId: activeField.sectionId,
          field: 'text',
          value,
        })
      }, 120)
    },
    editorProps: {
      attributes: {
        class: 've-inline-richtext-surface',
      },
    },
  })

  useEffect(() => {
    selectedFieldRef.current = selectedField
    if (!selectedField || selectedField.field !== 'text') {
      richTextValueRef.current = ''
      if (richTextChangeTimerRef.current) {
        window.clearTimeout(richTextChangeTimerRef.current)
        richTextChangeTimerRef.current = null
      }
    }
    if (!selectedField || (selectedField.kind !== 'text' && selectedField.kind !== 'button')) {
      shortTextValueRef.current = ''
      if (shortTextChangeTimerRef.current) {
        window.clearTimeout(shortTextChangeTimerRef.current)
        shortTextChangeTimerRef.current = null
      }
    }
  }, [selectedField])

  useEffect(() => {
    if (!enabled || mode !== 'edit' || saveState.busy) return

    function handleClick(event: MouseEvent) {
      const target = event.target as HTMLElement | null
      if (!target) return
      if (target.closest('[data-ve-overlay]')) return

      const fieldNode = target.closest<HTMLElement>('[data-ve-field]')
      if (fieldNode) {
        event.preventDefault()
        event.stopPropagation()
        const nextField: SelectedField = {
          sectionId: fieldNode.dataset.veSectionId || '',
          field: fieldNode.dataset.veField || '',
          kind: fieldNode.dataset.veKind || 'text',
          inline: fieldNode.dataset.veInline === 'true',
          buttonIndex: fieldNode.dataset.veButtonIndex ? Number(fieldNode.dataset.veButtonIndex) : undefined,
          targetField: fieldNode.dataset.veTargetField || undefined,
        }
        if (!nextField.sectionId || !nextField.field) return
        setSelectedSectionId(nextField.sectionId)
        setSelectedField(nextField)
        setInspectorOpen(true)
        sendToParent('field-select', { ...nextField })
        return
      }

      const sectionNode = target.closest<HTMLElement>('[data-section-id]')
      if (sectionNode) {
        event.preventDefault()
        event.stopPropagation()
        const sectionId = sectionNode.getAttribute('data-section-id')
        if (!sectionId) return
        setSelectedSectionId(sectionId)
        setSelectedField(null)
        setInspectorOpen(true)
        sendToParent('section-click', { sectionId })
      }
    }

    document.addEventListener('click', handleClick, true)
    return () => document.removeEventListener('click', handleClick, true)
  }, [enabled, mode, saveState.busy])

  useEffect(() => {
    if (!enabled || mode !== 'edit') return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key !== 'Escape') return
      if (!inspectorOpen) return
      event.preventDefault()
      setInspectorOpen(false)
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [enabled, inspectorOpen, mode])

  useEffect(() => {
    if (!saveState.busy) return
    if (document.activeElement instanceof HTMLElement) {
      document.activeElement.blur()
    }
    editor?.commands.blur()
  }, [editor, saveState.busy])

  useEffect(() => {
    if (!enabled) return

    function updateRect() {
      if (selectedField) {
        const selector = selectedField.buttonIndex != null
          ? `[data-ve-section-id="${escapeSelector(selectedField.sectionId)}"][data-ve-field="${escapeSelector(selectedField.field)}"][data-ve-button-index="${selectedField.buttonIndex}"]`
          : `[data-ve-section-id="${escapeSelector(selectedField.sectionId)}"][data-ve-field="${escapeSelector(selectedField.field)}"]`
        const node = document.querySelector<HTMLElement>(selector)
        if (node) {
          setAnchorRect(node.getBoundingClientRect())
          return
        }
      }
      if (selectedSectionId) {
        const node = document.querySelector<HTMLElement>(`[data-section-id="${escapeSelector(selectedSectionId)}"]`)
        setAnchorRect(node ? node.getBoundingClientRect() : null)
        return
      }
      setAnchorRect(null)
    }

    updateRect()
    window.addEventListener('resize', updateRect)
    window.addEventListener('scroll', updateRect, true)
    return () => {
      window.removeEventListener('resize', updateRect)
      window.removeEventListener('scroll', updateRect, true)
    }
  }, [enabled, selectedField, selectedSectionId, sections])

  useEffect(() => {
    if (!enabled || !selectedField || selectedField.kind !== 'text' && selectedField.kind !== 'button') return
    if (!shortTextRef.current) return
    const nextValue = getShortTextValue(selectedSection, selectedField)
    const isEditing = document.activeElement === shortTextRef.current
    if (isEditing && shortTextValueRef.current === nextValue) return
    if (shortTextRef.current.textContent !== nextValue) {
      shortTextRef.current.textContent = nextValue
    }
    if (!isEditing) {
      shortTextValueRef.current = nextValue
    }
  }, [enabled, selectedField, selectedSection])

  useEffect(() => {
    if (!inspectorOpen || saveState.busy || mode !== 'edit') return

    function focusEditable(node: HTMLDivElement | null) {
      if (!node) return
      node.focus()
      const selection = window.getSelection()
      if (!selection) return
      const range = document.createRange()
      range.selectNodeContents(node)
      range.collapse(false)
      selection.removeAllRanges()
      selection.addRange(range)
    }

    const timer = window.setTimeout(() => {
      if (selectedField?.field === 'text') {
        editor?.chain().focus('end').run()
        return
      }
      if (selectedField && (selectedField.kind === 'text' || selectedField.kind === 'button') && selectedField.field !== 'text') {
        focusEditable(shortTextRef.current)
        return
      }
      if (selectedField?.field === 'component') {
        componentInputRef.current?.focus()
        return
      }
      if (selectedField?.field === 'video') {
        componentInputRef.current?.focus()
        return
      }
      if (selectedField?.field === 'media') {
        mediaAltRef.current?.focus()
        return
      }
      if (selectedSectionId && !selectedField) {
        sectionToolbarRef.current?.focus()
      }
    }, 0)

    return () => window.clearTimeout(timer)
  }, [editor, inspectorOpen, mode, saveState.busy, selectedField, selectedSectionId])

  useEffect(() => {
    if (!editor) return
    const richTextActive = !!selectedField && selectedField.field === 'text' && mode === 'edit'
    editor.setEditable(richTextActive)
    if (richTextActive) {
      const nextValue = getRichTextValue(selectedSection)
      const currentValue = editor.getHTML()
      if (nextValue !== currentValue && nextValue !== richTextValueRef.current) {
        richTextSyncingRef.current = true
        editor.commands.setContent(nextValue, { emitUpdate: false })
        richTextSyncingRef.current = false
      }
      if (richTextValueRef.current !== nextValue && document.activeElement !== editor.view.dom) {
        richTextValueRef.current = nextValue
      }
    }
  }, [editor, mode, selectedField, selectedSection])

  useEffect(() => {
    return () => {
      if (shortTextChangeTimerRef.current) {
        window.clearTimeout(shortTextChangeTimerRef.current)
      }
      if (richTextChangeTimerRef.current) {
        window.clearTimeout(richTextChangeTimerRef.current)
      }
      editor?.destroy()
    }
  }, [editor])

  if (!enabled) return null

  const toolbarVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedSectionId && !selectedField && anchorRect && !isHeroSection
  const shortTextVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField && (selectedField.kind === 'text' || selectedField.kind === 'button') && selectedField.field !== 'text' && anchorRect
  const richTextVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField?.field === 'text' && anchorRect
  const componentVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField?.field === 'component' && anchorRect
  const videoVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField?.field === 'video' && anchorRect
  const mediaVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField?.field === 'media' && anchorRect

  const toolbarStyle = anchorRect ? {
    top: `${Math.max(anchorRect.top - 56, 12)}px`,
    left: `${Math.max(anchorRect.left, 12)}px`,
  } : undefined

  function sendStructuredFieldChange(field: string, value: unknown, extra: Record<string, unknown> = {}) {
    if (!selectedSectionId) return
    sendToParent('field-change', { sectionId: selectedSectionId, field, value, ...extra })
  }

  function openProcessWireFocus() {
    if (!selectedSectionId || saveState.busy) return
    sendToParent('open-processwire', {
      sectionId: selectedSectionId,
      field: selectedField?.field,
      kind: selectedField?.kind,
      inline: selectedField?.inline,
      buttonIndex: selectedField?.buttonIndex,
      targetField: selectedField?.targetField,
    })
  }

  function resolveComponentInput(rawValue: string): string {
    const raw = String(rawValue || '').trim()
    if (!raw) return ''
    const resolved = resolveComponentRegistryEntry(raw)
    return resolved?.canonicalKey || ''
  }

  function getEditableMediaItems(): Array<{ url: string; alt: string; type: 'image' | 'video' }> {
    if (!selectedSection?.media?.length) {
      if (selectedSection?.image) {
        return [{ url: selectedSection.image, alt: selectedSection.imageAlt || '', type: 'image' }]
      }
      return []
    }
    return selectedSection.media
      .filter((item) => (item.type || 'image') === 'image')
      .map((item) => ({ url: item.url, alt: item.alt || '', type: 'image' }))
  }

  function pushMediaItems(nextItems: Array<{ url: string; alt: string; type: 'image' | 'video' }>) {
    sendStructuredFieldChange('mediaItems', nextItems)
  }

  function handleShortTextInput() {
    if (!selectedField || !shortTextRef.current) return
    const value = shortTextRef.current.innerText.replace(/\n/g, '')
    shortTextValueRef.current = value
    if (shortTextChangeTimerRef.current) {
      window.clearTimeout(shortTextChangeTimerRef.current)
    }
    if (selectedField.kind === 'button') {
      shortTextChangeTimerRef.current = window.setTimeout(() => {
        sendToParent('field-change', {
          sectionId: selectedField.sectionId,
          field: 'button_text',
          buttonIndex: selectedField.buttonIndex || 0,
          value,
        })
      }, 90)
      return
    }
    shortTextChangeTimerRef.current = window.setTimeout(() => {
      sendToParent('field-change', {
        sectionId: selectedField.sectionId,
        field: selectedField.field,
        value,
      })
    }, 90)
  }

  function handleShortTextCommit() {
    if (!selectedField || !shortTextRef.current) return
    const value = shortTextRef.current.innerText.replace(/\n/g, '')
    shortTextValueRef.current = value
    if (shortTextChangeTimerRef.current) {
      window.clearTimeout(shortTextChangeTimerRef.current)
      shortTextChangeTimerRef.current = null
    }
    if (selectedField.kind === 'button') {
      sendToParent('field-commit', {
        sectionId: selectedField.sectionId,
        field: 'button_text',
        buttonIndex: selectedField.buttonIndex || 0,
        value,
      })
      return
    }
    sendToParent('field-commit', {
      sectionId: selectedField.sectionId,
      field: selectedField.field,
      value,
    })
  }

  return (
    <>
      <style dangerouslySetInnerHTML={{ __html: `
        [data-ve-field] {
          position: relative;
        }
        [data-ve-field]:hover {
          outline: 2px dashed rgba(74, 124, 89, 0.7);
          outline-offset: 4px;
        }
        [data-ve-field][data-ve-inline="false"]:hover {
          outline-style: solid;
        }
        .ve-inline-overlay {
          position: fixed;
          z-index: 9999;
        }
        .ve-inline-panel {
          background: rgba(15, 23, 42, 0.96);
          border: 1px solid rgba(74, 124, 89, 0.6);
          border-radius: 12px;
          box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
          color: var(--color-fog);
          min-width: 280px;
          padding: 12px;
        }
        .ve-inline-panel h4 {
          font-size: 12px;
          font-weight: 700;
          letter-spacing: 0.05em;
          margin-bottom: 8px;
          text-transform: uppercase;
        }
        .ve-inline-panel p {
          color: var(--color-haze);
          font-size: 12px;
          margin-top: 8px;
        }
        .ve-inline-panel input,
        .ve-inline-panel select,
        .ve-inline-panel button {
          background: var(--color-onyx);
          border: 1px solid var(--color-storm);
          border-radius: 8px;
          color: var(--color-fog);
          font: inherit;
          padding: 8px 10px;
          width: 100%;
        }
        .ve-inline-panel button {
          cursor: pointer;
          width: auto;
        }
        .ve-inline-panel button:disabled,
        .ve-inline-panel input:disabled,
        .ve-inline-panel select:disabled {
          cursor: not-allowed;
          opacity: 0.55;
        }
        .ve-inline-header {
          align-items: center;
          display: flex;
          gap: 10px;
          justify-content: space-between;
          margin-bottom: 8px;
        }
        .ve-inline-header h4 {
          margin-bottom: 0;
        }
        .ve-inline-close {
          align-items: center;
          display: inline-flex;
          height: 32px;
          justify-content: center;
          min-width: 32px;
          padding: 0;
        }
        .ve-inline-actions {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
          margin-top: 8px;
        }
        .ve-inline-config-grid {
          display: grid;
          gap: 10px;
          margin-top: 12px;
        }
        .ve-inline-config-grid label {
          color: var(--color-haze);
          display: block;
          font-size: 11px;
          font-weight: 700;
          letter-spacing: 0.04em;
          margin-bottom: 4px;
          text-transform: uppercase;
        }
        .ve-inline-text-editor {
          background: var(--color-midnight);
          border: 1px solid var(--color-storm);
          border-radius: 8px;
          min-height: 42px;
          outline: none;
          padding: 10px 12px;
          white-space: nowrap;
        }
        .ve-inline-richtext-surface {
          min-height: 180px;
          outline: none;
        }
        .ve-inline-toolbar {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
          margin-bottom: 10px;
        }
        .ve-inline-toolbar button {
          min-width: 44px;
        }
        .ve-inline-save-state {
          margin-top: 10px;
        }
        .ve-inline-blocker {
          align-items: center;
          background: rgba(15, 23, 42, 0.72);
          display: flex;
          inset: 0;
          justify-content: center;
          position: fixed;
          z-index: 10000;
        }
        .ve-inline-blocker .ve-inline-panel {
          min-width: 320px;
          text-align: center;
        }
        .ve-inline-spinner {
          animation: ve-spin 0.9s linear infinite;
          border: 4px solid rgba(148, 163, 184, 0.25);
          border-top-color: var(--color-sage);
          border-radius: 999px;
          height: 44px;
          margin: 0 auto 14px;
          width: 44px;
        }
        @keyframes ve-spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      ` }} />

      {saveState.busy ? (
        <div data-ve-overlay className="ve-inline-blocker">
          <div className="ve-inline-panel">
            <div className="ve-inline-spinner" />
            <strong>{saveState.busyLabel || 'Bitte warten…'}</strong>
            <p>Bearbeitung ist kurz gesperrt, bis der Vorgang abgeschlossen ist.</p>
          </div>
        </div>
      ) : null}

      {toolbarVisible ? (
        <div data-ve-overlay className="ve-inline-overlay" style={toolbarStyle}>
          <div className="ve-inline-panel">
            <div className="ve-inline-header">
              <h4>{selectedSection?.component ? formatComponentDisplayName(selectedSection.component) : 'Abschnitt'}</h4>
              <button className="ve-inline-close" type="button" onClick={() => setInspectorOpen(false)}>×</button>
            </div>
            <div className="ve-inline-actions">
              <button ref={sectionToolbarRef} type="button" disabled={saveState.busy} onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'duplicate' })}>Duplizieren</button>
              <button type="button" onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'move-up' })}>Hoch</button>
              <button type="button" onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'move-down' })}>Runter</button>
              <button type="button" onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'delete' })}>Löschen</button>
              <button type="button" disabled={saveState.busy} onClick={openProcessWireFocus}>In ProcessWire öffnen</button>
            </div>
            <div className="ve-inline-actions">
              <select
                aria-label="Layout"
                disabled={saveState.busy}
                value={selectedSection?.layout || 'rich_text'}
                onChange={(event) => sendStructuredFieldChange('layout', event.target.value)}
              >
                <option value="split_media_text">Bild + Text</option>
                <option value="split_text_media">Text + Bild</option>
                <option value="full_width_banner">Banner</option>
                <option value="media_grid">Bildergalerie</option>
                <option value="video_embed">Video</option>
                <option value="rich_text">Nur Text</option>
                <option value="component">Komponente</option>
              </select>
              <select
                aria-label="Theme"
                disabled={saveState.busy}
                value={selectedSection?.theme || 'default'}
                onChange={(event) => sendStructuredFieldChange('theme', event.target.value)}
              >
                <option value="default">Default</option>
                <option value="light">Light</option>
                <option value="dark">Dark</option>
                <option value="green">Green</option>
              </select>
              <select
                aria-label="Hintergrund"
                disabled={saveState.busy}
                value={selectedSection?.bgColor || 'none'}
                onChange={(event) => sendStructuredFieldChange('bgColor', event.target.value)}
              >
                <option value="none">Kein Hintergrund</option>
                <option value="green">Green</option>
                <option value="darkgreen">Dark Green</option>
                <option value="orange">Orange</option>
                <option value="gray">Gray</option>
                <option value="white">White</option>
              </select>
              <select
                aria-label="Overlay"
                disabled={saveState.busy}
                value={selectedSection?.imageOverlay || 'none'}
                onChange={(event) => sendStructuredFieldChange('imageOverlay', event.target.value)}
              >
                <option value="none">Kein Overlay</option>
                <option value="dark">Dark</option>
                <option value="green">Green</option>
                <option value="orange">Orange</option>
              </select>
            </div>
            {selectedSection?.component && selectedComponentSchema.length ? (
              <div className="ve-inline-config-grid">
                {selectedComponentSchema.map((field) => (
                  <div key={field.key}>
                    <label htmlFor={`ve-config-${field.key}`}>{field.label}</label>
                    {field.type === 'range' ? (
                      <input
                        id={`ve-config-${field.key}`}
                        aria-label={field.label}
                        disabled={saveState.busy}
                        min={field.min}
                        max={field.max}
                        step={field.step || 1}
                        type="range"
                        value={Number(selectedComponentConfig[field.key] ?? field.min ?? 0)}
                        onChange={(event) => sendStructuredFieldChange('config', Number(event.target.value), { configKey: field.key })}
                      />
                    ) : field.type === 'text' ? (
                      <input
                        id={`ve-config-${field.key}`}
                        aria-label={field.label}
                        disabled={saveState.busy}
                        type="text"
                        placeholder={field.placeholder || ''}
                        value={String(selectedComponentConfig[field.key] ?? '')}
                        onChange={(event) => sendStructuredFieldChange('config', event.target.value, { configKey: field.key })}
                      />
                    ) : field.type === 'number' ? (
                      <input
                        id={`ve-config-${field.key}`}
                        aria-label={field.label}
                        disabled={saveState.busy}
                        type="number"
                        min={field.min}
                        max={field.max}
                        step={field.step || 1}
                        placeholder={field.placeholder || ''}
                        value={String(selectedComponentConfig[field.key] ?? '')}
                        onChange={(event) => sendStructuredFieldChange('config', event.target.value === '' ? '' : Number(event.target.value), { configKey: field.key })}
                      />
                    ) : (
                      <select
                        id={`ve-config-${field.key}`}
                        aria-label={field.label}
                        disabled={saveState.busy}
                        value={String(selectedComponentConfig[field.key] ?? '')}
                        onChange={(event) => sendStructuredFieldChange('config', event.target.value, { configKey: field.key })}
                      >
                        {(field.options || []).map((option) => (
                          <option key={`${field.key}-${option.value}`} value={String(option.value)}>
                            {option.label}
                          </option>
                        ))}
                      </select>
                    )}
                  </div>
                ))}
              </div>
            ) : null}
            <p>{saveState.saving ? 'Publiziert…' : saveState.dirty ? 'Lokaler Entwurf noch nicht publiziert' : 'Kein offener Entwurf'}</p>
          </div>
        </div>
      ) : null}

      {shortTextVisible ? (
        <div
          data-ve-overlay
          className="ve-inline-overlay"
          style={{
            top: `${(anchorRect?.bottom || 0) + 10}px`,
            left: `${anchorRect?.left || 0}px`,
          }}
        >
          <div className="ve-inline-panel">
            <div className="ve-inline-header">
              <h4>{getFieldLabel(selectedField)}</h4>
              <button className="ve-inline-close" type="button" onClick={() => setInspectorOpen(false)}>×</button>
            </div>
            <div
              ref={shortTextRef}
              className="ve-inline-text-editor"
              contentEditable={!saveState.busy}
              suppressContentEditableWarning
              onInput={handleShortTextInput}
              onBlur={handleShortTextCommit}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault()
                  ;(event.currentTarget as HTMLDivElement).blur()
                }
              }}
            />
            <div className="ve-inline-actions">
              <button type="button" disabled={saveState.busy} onClick={openProcessWireFocus}>In ProcessWire öffnen</button>
            </div>
            {selectedField?.kind === 'button' ? (
              <div className="ve-inline-actions">
                <input
                  aria-label="Button Link"
                  disabled={saveState.busy}
                  type="text"
                  value={selectedSection?.buttons?.[selectedField.buttonIndex || 0]?.href || ''}
                  onChange={(event) => sendToParent('field-change', {
                    sectionId: selectedField.sectionId,
                    field: 'button_href',
                    buttonIndex: selectedField.buttonIndex || 0,
                    value: event.target.value,
                  })}
                />
                <select
                  aria-label="Button Stil"
                  disabled={saveState.busy}
                  value={selectedSection?.buttons?.[selectedField.buttonIndex || 0]?.variant || ((selectedField.buttonIndex || 0) === 0 ? 'primary' : 'secondary')}
                  onChange={(event) => sendToParent('field-change', {
                    sectionId: selectedField.sectionId,
                    field: 'button_variant',
                    buttonIndex: selectedField.buttonIndex || 0,
                    value: event.target.value,
                  })}
                >
                  <option value="primary">Primary</option>
                  <option value="secondary">Secondary</option>
                </select>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}

      {richTextVisible ? (
        <div
          data-ve-overlay
          className="ve-inline-overlay"
          style={{
            top: `${(anchorRect?.bottom || 0) + 10}px`,
            left: `${Math.max((anchorRect?.left || 0) - 20, 12)}px`,
          }}
        >
          <div className="ve-inline-panel" style={{ width: 'min(720px, calc(100vw - 24px))' }}>
            <div className="ve-inline-header">
              <h4>Rich Text</h4>
              <button className="ve-inline-close" type="button" onClick={() => setInspectorOpen(false)}>×</button>
            </div>
            <div className="ve-inline-toolbar">
              <button type="button" disabled={saveState.busy} onClick={() => editor?.chain().focus().toggleBold().run()}>B</button>
              <button type="button" disabled={saveState.busy} onClick={() => editor?.chain().focus().toggleItalic().run()}>I</button>
              <button type="button" disabled={saveState.busy} onClick={() => editor?.chain().focus().toggleBulletList().run()}>• List</button>
              <button type="button" disabled={saveState.busy} onClick={() => editor?.chain().focus().toggleOrderedList().run()}>1. List</button>
            </div>
            <EditorContent
              editor={editor}
              onBlur={() => {
                if (!selectedField) return
                if (richTextChangeTimerRef.current) {
                  window.clearTimeout(richTextChangeTimerRef.current)
                  richTextChangeTimerRef.current = null
                }
                richTextValueRef.current = editor?.getHTML() || ''
                sendToParent('field-commit', {
                  sectionId: selectedField.sectionId,
                  field: 'text',
                  value: editor?.getHTML() || '',
                })
              }}
            />
            <div className="ve-inline-actions">
              <button type="button" disabled={saveState.busy} onClick={openProcessWireFocus}>In ProcessWire öffnen</button>
            </div>
          </div>
        </div>
      ) : null}

      {componentVisible ? (
        <div
          data-ve-overlay
          className="ve-inline-overlay"
          style={{
            top: `${(anchorRect?.bottom || 0) + 10}px`,
            left: `${anchorRect?.left || 0}px`,
          }}
        >
          <div className="ve-inline-panel">
            <div className="ve-inline-header">
              <h4>Komponente</h4>
              <button className="ve-inline-close" type="button" onClick={() => setInspectorOpen(false)}>×</button>
            </div>
            <input
              ref={componentInputRef}
              aria-label="Komponente"
              disabled={saveState.busy}
              type="text"
              value={selectedSection?.component || ''}
              list="ve-component-options"
              onChange={(event) => {
                const next = resolveComponentInput(event.target.value)
                if (!next) return
                sendStructuredFieldChange('component', next)
              }}
            />
            <datalist id="ve-component-options">
              {componentCandidates.map((candidate) => (
                <option key={candidate.key} value={candidate.key}>
                  {candidate.label}
                </option>
              ))}
            </datalist>
            <p>
              {selectedSection?.component
                ? selectedComponentMeta
                  ? `${formatComponentDisplayName(selectedSection.component)}`
                  : 'Keine bekannte Registry-Zuordnung'
                : 'Kein Komponenten-Key gesetzt'}
            </p>
            {selectedSection?.component && presetTagsByComponent[selectedSection.component]?.length ? (
              <p>Preset Tags: {presetTagsByComponent[selectedSection.component].join(', ')}</p>
            ) : null}
            <div className="ve-inline-actions">
              <button type="button" disabled={saveState.busy} onClick={openProcessWireFocus}>In ProcessWire öffnen</button>
            </div>
          </div>
        </div>
      ) : null}

      {videoVisible ? (
        <div
          data-ve-overlay
          className="ve-inline-overlay"
          style={{
            top: `${(anchorRect?.bottom || 0) + 10}px`,
            left: `${anchorRect?.left || 0}px`,
          }}
        >
          <div className="ve-inline-panel">
            <div className="ve-inline-header">
              <h4>Video</h4>
              <button className="ve-inline-close" type="button" onClick={() => setInspectorOpen(false)}>×</button>
            </div>
            <input
              ref={componentInputRef}
              aria-label="Video URL"
              disabled={saveState.busy}
              type="text"
              value={selectedSection?.video?.url || ''}
              onChange={(event) => sendStructuredFieldChange('videoUrl', event.target.value)}
            />
            <input
              aria-label="Video Titel"
              disabled={saveState.busy}
              type="text"
              value={selectedSection?.video?.title || ''}
              onChange={(event) => sendStructuredFieldChange('videoTitle', event.target.value)}
            />
            <div className="ve-inline-actions">
              <button type="button" disabled={saveState.busy} onClick={openProcessWireFocus}>In ProcessWire öffnen</button>
            </div>
          </div>
        </div>
      ) : null}

      {mediaVisible ? (
        <div
          data-ve-overlay
          className="ve-inline-overlay"
          style={{
            top: `${(anchorRect?.bottom || 0) + 10}px`,
            left: `${anchorRect?.left || 0}px`,
          }}
        >
          <div className="ve-inline-panel">
            <div className="ve-inline-header">
              <h4>Bild</h4>
              <button className="ve-inline-close" type="button" onClick={() => setInspectorOpen(false)}>×</button>
            </div>
            <input
              ref={mediaAltRef}
              aria-label="Alt Text"
              disabled={saveState.busy}
              type="text"
              value={selectedSection?.imageAlt || ''}
              onChange={(event) => sendStructuredFieldChange('imageAlt', event.target.value)}
            />
            {getEditableMediaItems().length ? (
              <div className="ve-inline-config-grid">
                {getEditableMediaItems().map((item, index) => (
                  <div key={`${item.url}-${index}`}>
                    <label htmlFor={`ve-media-alt-${index}`}>Bild {index + 1}</label>
                    <input
                      id={`ve-media-alt-${index}`}
                      aria-label={`Bild ${index + 1} Alt Text`}
                      disabled={saveState.busy}
                      type="text"
                      value={item.alt || ''}
                      onChange={(event) => {
                        const next = getEditableMediaItems().map((entry) => ({ ...entry }))
                        next[index].alt = event.target.value
                        pushMediaItems(next)
                      }}
                    />
                    <div className="ve-inline-actions">
                      <button
                        type="button"
                        aria-label={`Bild ${index + 1} hoch`}
                        disabled={saveState.busy || index === 0}
                        onClick={() => {
                          const next = getEditableMediaItems().map((entry) => ({ ...entry }))
                          const [moved] = next.splice(index, 1)
                          next.splice(index - 1, 0, moved)
                          pushMediaItems(next)
                        }}
                      >
                        Hoch
                      </button>
                      <button
                        type="button"
                        aria-label={`Bild ${index + 1} runter`}
                        disabled={saveState.busy || index === getEditableMediaItems().length - 1}
                        onClick={() => {
                          const next = getEditableMediaItems().map((entry) => ({ ...entry }))
                          const [moved] = next.splice(index, 1)
                          next.splice(index + 1, 0, moved)
                          pushMediaItems(next)
                        }}
                      >
                        Runter
                      </button>
                      <button
                        type="button"
                        aria-label={`Bild ${index + 1} löschen`}
                        disabled={saveState.busy}
                        onClick={() => {
                          const next = getEditableMediaItems().map((entry) => ({ ...entry }))
                          next.splice(index, 1)
                          pushMediaItems(next)
                        }}
                      >
                        Löschen
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : null}
            <div className="ve-inline-actions">
              <button
                type="button"
                disabled={saveState.busy}
                onClick={() => sendToParent('media-request', {
                  sectionId: selectedField?.sectionId,
                  targetField: selectedField?.targetField || 'section_image',
                })}
              >
                Mediathek öffnen
              </button>
              <button
                type="button"
                disabled={saveState.busy}
                onClick={() => sendToParent('media-request', {
                  sectionId: selectedField?.sectionId,
                  targetField: 'section_images',
                })}
              >
                Bild hinzufügen
              </button>
              <button type="button" disabled={saveState.busy} onClick={openProcessWireFocus}>In ProcessWire öffnen</button>
            </div>
          </div>
        </div>
      ) : null}

      {saveState.message ? (
        <div
          data-ve-overlay
          className="ve-inline-overlay ve-inline-save-state"
          style={{ top: '12px', right: '12px', position: 'fixed' }}
        >
          <div className="ve-inline-panel">
            <strong>{saveState.message}</strong>
          </div>
        </div>
      ) : null}
    </>
  )
}
