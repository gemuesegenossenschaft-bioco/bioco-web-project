/**
 * Observable store around the committed pure reducer
 * (frontend/lib/visual-editor/shellState.ts). The reducer stays the single
 * source of truth for the status flow; this only adds subscription plumbing.
 */

import {
  initialShellState,
  shellReducer,
  type ShellEvent,
  type ShellState,
} from '../lib/visual-editor/shellState'

export type ShellStoreListener = (state: ShellState, event: ShellEvent) => void

export interface ShellStore {
  getState(): ShellState
  /** Applies the event; returns true when the state actually changed. */
  dispatch(event: ShellEvent): boolean
  subscribe(listener: ShellStoreListener): () => void
}

export function createShellStore(initial: ShellState = initialShellState): ShellStore {
  let state = initial
  const listeners = new Set<ShellStoreListener>()

  return {
    getState: () => state,

    dispatch(event) {
      const next = shellReducer(state, event)
      if (next === state) return false
      state = next
      for (const listener of Array.from(listeners)) listener(state, event)
      return true
    },

    subscribe(listener) {
      listeners.add(listener)
      return () => {
        listeners.delete(listener)
      }
    },
  }
}
