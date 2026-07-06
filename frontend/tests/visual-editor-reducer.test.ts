import { describe, expect, it } from 'vitest'
import {
  initialShellState,
  shellReducer,
  type ShellEvent,
  type ShellState,
} from '@/lib/visual-editor/shellState'

// G.1 — pure state machine for the visual-editor parent shell:
// idle -> dirty -> saving -> published | error, with busy blocking and
// active-page sync from iframe `ready` messages. Mirrors the behavior of
// site/templates/visual-editor.php so its JS can be aligned with it later.

function run(events: ShellEvent[], from: ShellState = initialShellState): ShellState {
  return events.reduce(shellReducer, from)
}

describe('visual editor shell state machine', () => {
  it('starts idle, not busy, disconnected', () => {
    expect(initialShellState).toEqual({
      status: 'idle',
      busy: false,
      busyLabel: '',
      iframeReady: false,
      activePath: null,
      selectedSectionId: null,
      error: null,
      revalidated: null,
    })
  })

  it('syncs the active page path from iframe ready messages', () => {
    const state = run([{ type: 'iframe-ready', path: '/abos' }])
    expect(state.iframeReady).toBe(true)
    expect(state.activePath).toBe('/abos')

    const next = shellReducer(state, { type: 'iframe-ready', path: '/wir' })
    expect(next.activePath).toBe('/wir')
  })

  it('processes ready even while busy (real-site navigation during busy)', () => {
    const busyState = run([{ type: 'busy-start', busyLabel: 'Laden…' }])
    const state = shellReducer(busyState, { type: 'iframe-ready', path: '/mitmachen' })
    expect(state.activePath).toBe('/mitmachen')
    expect(state.busy).toBe(true)
  })

  it('moves idle -> dirty on edit and published -> dirty on further edits', () => {
    const dirty = run([{ type: 'edit' }])
    expect(dirty.status).toBe('dirty')

    const published = run([
      { type: 'edit' },
      { type: 'publish-start' },
      { type: 'publish-success', revalidated: true },
    ])
    expect(published.status).toBe('published')
    expect(shellReducer(published, { type: 'edit' }).status).toBe('dirty')
  })

  it('blocks edits, selection, and discard while busy (returns same state)', () => {
    const busyState = run([{ type: 'edit' }, { type: 'busy-start', busyLabel: 'Publizieren…' }])
    expect(shellReducer(busyState, { type: 'edit' })).toBe(busyState)
    expect(shellReducer(busyState, { type: 'select-section', sectionId: 's1' })).toBe(busyState)
    expect(shellReducer(busyState, { type: 'discard' })).toBe(busyState)
  })

  it('tracks the selected section and clears it via null', () => {
    const selected = run([{ type: 'select-section', sectionId: 'section-1' }])
    expect(selected.selectedSectionId).toBe('section-1')
    expect(shellReducer(selected, { type: 'select-section', sectionId: null }).selectedSectionId).toBeNull()
  })

  it('discard returns dirty -> idle and is a no-op when clean', () => {
    const dirty = run([{ type: 'edit' }])
    expect(shellReducer(dirty, { type: 'discard' }).status).toBe('idle')
    expect(shellReducer(initialShellState, { type: 'discard' })).toBe(initialShellState)
  })

  it('publish-start only fires from dirty or error, and marks the shell busy', () => {
    expect(shellReducer(initialShellState, { type: 'publish-start' })).toBe(initialShellState)

    const saving = run([{ type: 'edit' }, { type: 'publish-start', busyLabel: 'Änderungen publizieren…' }])
    expect(saving.status).toBe('saving')
    expect(saving.busy).toBe(true)
    expect(saving.busyLabel).toBe('Änderungen publizieren…')
  })

  it('publish-success lands in published and carries the revalidate outcome', () => {
    const base: ShellEvent[] = [{ type: 'edit' }, { type: 'publish-start' }]

    const live = run([...base, { type: 'publish-success', revalidated: true }])
    expect(live).toMatchObject({ status: 'published', busy: false, busyLabel: '', revalidated: true, error: null })

    // "Publiziert, aber Build nicht aktualisiert" — published but stale build
    const stale = run([...base, { type: 'publish-success', revalidated: false }])
    expect(stale).toMatchObject({ status: 'published', revalidated: false })
  })

  it('publish-failure lands in error, keeps the draft retryable', () => {
    const failed = run([
      { type: 'edit' },
      { type: 'publish-start' },
      { type: 'publish-failure', error: 'Publizieren fehlgeschlagen' },
    ])
    expect(failed).toMatchObject({ status: 'error', busy: false, error: 'Publizieren fehlgeschlagen' })

    // retry from error
    const retrying = shellReducer(failed, { type: 'publish-start' })
    expect(retrying.status).toBe('saving')
    expect(retrying.error).toBeNull()

    // further edits from error go back to dirty and clear the error
    const editedAgain = shellReducer(failed, { type: 'edit' })
    expect(editedAgain).toMatchObject({ status: 'dirty', error: null })
  })

  it('publish results are ignored unless saving', () => {
    expect(shellReducer(initialShellState, { type: 'publish-success', revalidated: true })).toBe(initialShellState)
    expect(shellReducer(initialShellState, { type: 'publish-failure', error: 'x' })).toBe(initialShellState)
  })

  it('busy-start/busy-end toggle generic busy, but saving owns its busy flag', () => {
    const busyState = run([{ type: 'busy-start', busyLabel: 'Abschnitte laden…' }])
    expect(busyState).toMatchObject({ busy: true, busyLabel: 'Abschnitte laden…' })
    expect(shellReducer(busyState, { type: 'busy-end' })).toMatchObject({ busy: false, busyLabel: '' })

    const saving = run([{ type: 'edit' }, { type: 'publish-start' }])
    expect(shellReducer(saving, { type: 'busy-end' })).toBe(saving)
  })

  it('never mutates the input state', () => {
    const before = { ...initialShellState }
    shellReducer(initialShellState, { type: 'edit' })
    shellReducer(initialShellState, { type: 'iframe-ready', path: '/abos' })
    expect(initialShellState).toEqual(before)
  })
})
