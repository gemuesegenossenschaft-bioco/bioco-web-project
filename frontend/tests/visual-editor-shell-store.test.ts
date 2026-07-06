import { describe, expect, it, vi } from 'vitest'
import { createShellStore } from '../visual-editor-shell/store'
import { initialShellState } from '../lib/visual-editor/shellState'

// G.2 — thin observable store wrapping the committed shellReducer
// (frontend/lib/visual-editor/shellState.ts). No new transition logic here:
// the reducer stays the single source of truth for the status flow.

describe('createShellStore', () => {
  it('starts from initialShellState', () => {
    const store = createShellStore()
    expect(store.getState()).toEqual(initialShellState)
  })

  it('dispatch applies reducer events and reports whether state changed', () => {
    const store = createShellStore()
    expect(store.dispatch({ type: 'edit' })).toBe(true)
    expect(store.getState().status).toBe('dirty')
    // discard from idle is a no-op -> reducer returns same reference
    expect(store.dispatch({ type: 'discard' })).toBe(true)
    expect(store.dispatch({ type: 'discard' })).toBe(false)
  })

  it('notifies subscribers only when state actually changed', () => {
    const store = createShellStore()
    const listener = vi.fn()
    const unsubscribe = store.subscribe(listener)

    store.dispatch({ type: 'edit' })
    expect(listener).toHaveBeenCalledTimes(1)
    expect(listener).toHaveBeenLastCalledWith(store.getState(), { type: 'edit' })

    // no-op event: busy-end while not busy
    store.dispatch({ type: 'busy-end' })
    expect(listener).toHaveBeenCalledTimes(1)

    unsubscribe()
    store.dispatch({ type: 'publish-start' })
    expect(listener).toHaveBeenCalledTimes(1)
  })

  it('runs the publish flow and records the revalidate outcome', () => {
    const store = createShellStore()
    store.dispatch({ type: 'edit' })
    store.dispatch({ type: 'publish-start', busyLabel: 'Änderungen publizieren…' })
    expect(store.getState()).toMatchObject({ status: 'saving', busy: true, busyLabel: 'Änderungen publizieren…' })

    store.dispatch({ type: 'publish-success', revalidated: false })
    expect(store.getState()).toMatchObject({ status: 'published', busy: false, revalidated: false })
  })

  it('blocks edits and selection while busy but always processes iframe-ready', () => {
    const store = createShellStore()
    store.dispatch({ type: 'busy-start', busyLabel: 'Vorschau laden…' })
    expect(store.dispatch({ type: 'edit' })).toBe(false)
    expect(store.dispatch({ type: 'select-section', sectionId: 's1' })).toBe(false)
    expect(store.dispatch({ type: 'iframe-ready', path: '/abos' })).toBe(true)
    expect(store.getState().activePath).toBe('/abos')
  })
})
