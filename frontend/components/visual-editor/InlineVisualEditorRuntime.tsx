'use client'

import { useEffect, useMemo, useRef, useState } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import { formatComponentDisplayName, resolveComponentRegistryEntry } from '@/lib/componentRegistry'
import type { ContentSection } from '@/lib/processwire-types'

const MSG_PREFIX = 'bioco:visual-editor:'

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
  return ''
}

function getRichTextValue(section: ContentSection | null): string {
  return section?.text || ''
}

function getFieldLabel(selectedField: SelectedField | null): string {
  if (!selectedField) return ''
  if (selectedField.kind === 'button') return 'Button'
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

  const selectedSection = useMemo(() => {
    return sections.find((section) => section.id === selectedSectionId) || null
  }, [sections, selectedSectionId])

  const selectedComponentMeta = useMemo(() => {
    return resolveComponentRegistryEntry(selectedSection?.component || '')
  }, [selectedSection?.component])

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
    if (!enabled) return

    function handleMessage(event: MessageEvent) {
      const data = event.data
      if (!data || typeof data.type !== 'string' || !data.type.startsWith(MSG_PREFIX)) return

      const action = data.type.slice(MSG_PREFIX.length)
      switch (action) {
        case 'save-state':
          setMode(data.mode === 'browse' ? 'browse' : 'edit')
          setSaveState({
            dirty: !!data.dirty,
            saving: !!data.saving,
            busy: !!data.busy,
            busyLabel: typeof data.busyLabel === 'string' ? data.busyLabel : '',
            message: typeof data.message === 'string' ? data.message : '',
          })
          setSelectedSectionId(typeof data.selectedSectionId === 'string' ? data.selectedSectionId : null)
          break
        case 'section-highlight':
          setSelectedSectionId(data.sectionId || null)
          setInspectorOpen(!!data.sectionId)
          if (!data.sectionId) {
            setSelectedField(null)
          }
          break
        case 'field-highlight':
          if (data.sectionId && data.field && data.kind) {
            setSelectedSectionId(data.sectionId)
            setSelectedField({
              sectionId: data.sectionId,
              field: data.field,
              kind: data.kind,
              inline: data.inline !== false,
              buttonIndex: typeof data.buttonIndex === 'number' ? data.buttonIndex : undefined,
              targetField: typeof data.targetField === 'string' ? data.targetField : undefined,
            })
            setInspectorOpen(true)
          }
          break
        case 'field-reset':
          setSelectedField(null)
          setInspectorOpen(!!selectedSectionId)
          break
        case 'save-result':
          setSaveState((current) => ({
            ...current,
            message: data.success ? 'Gespeichert' : String(data.error || 'Speichern fehlgeschlagen'),
          }))
          break
      }
    }

    window.addEventListener('message', handleMessage)
    return () => window.removeEventListener('message', handleMessage)
  }, [enabled, selectedSectionId])

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

  const toolbarVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedSectionId && !selectedField && anchorRect
  const shortTextVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField && (selectedField.kind === 'text' || selectedField.kind === 'button') && selectedField.field !== 'text' && anchorRect
  const richTextVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField?.field === 'text' && anchorRect
  const componentVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField?.field === 'component' && anchorRect
  const mediaVisible = mode === 'edit' && !saveState.busy && inspectorOpen && selectedField?.field === 'media' && anchorRect

  const toolbarStyle = anchorRect ? {
    top: `${Math.max(anchorRect.top - 56, 12)}px`,
    left: `${Math.max(anchorRect.left, 12)}px`,
  } : undefined

  function sendStructuredFieldChange(field: string, value: unknown, extra: Record<string, unknown> = {}) {
    if (!selectedSectionId) return
    sendToParent('field-change', { sectionId: selectedSectionId, field, value, ...extra })
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
          color: #e5e7eb;
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
          color: #94a3b8;
          font-size: 12px;
          margin-top: 8px;
        }
        .ve-inline-panel input,
        .ve-inline-panel select,
        .ve-inline-panel button {
          background: #111827;
          border: 1px solid #334155;
          border-radius: 8px;
          color: #e5e7eb;
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
        .ve-inline-text-editor {
          background: #0f172a;
          border: 1px solid #334155;
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
          border-top-color: #8ab272;
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
              <h4>Abschnitt</h4>
              <button className="ve-inline-close" type="button" onClick={() => setInspectorOpen(false)}>×</button>
            </div>
            <div className="ve-inline-actions">
              <button ref={sectionToolbarRef} type="button" disabled={saveState.busy} onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'duplicate' })}>Duplizieren</button>
              <button type="button" onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'move-up' })}>Hoch</button>
              <button type="button" onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'move-down' })}>Runter</button>
              <button type="button" onClick={() => sendToParent('section-action', { sectionId: selectedSectionId, action: 'delete' })}>Löschen</button>
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
            <p>{saveState.saving ? 'Speichert…' : saveState.dirty ? 'Ungespeicherte Änderungen' : 'Kein offener Abschnitt geändert'}</p>
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
              onChange={(event) => sendStructuredFieldChange('component', event.target.value)}
            />
            <p>
              {selectedSection?.component
                ? selectedComponentMeta
                  ? `${formatComponentDisplayName(selectedSection.component)}`
                  : 'Keine bekannte Registry-Zuordnung'
                : 'Kein Komponenten-Key gesetzt'}
            </p>
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
