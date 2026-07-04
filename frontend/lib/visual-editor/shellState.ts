/**
 * Pure state machine for the visual-editor parent shell
 * (site/templates/visual-editor.php). Dependency-free and UI-agnostic so the
 * PHP shell's inline JS can be generated from / aligned with it later.
 *
 * Status flow:   idle -> dirty -> saving -> published | error
 *                 ^______|          |________retry______^
 *
 * Busy blocking mirrors the shell: while busy, user-driven events (edit,
 * select-section, discard) are ignored — but iframe `ready` is always
 * processed, because real-site navigation inside the iframe must keep the
 * active page path in sync even mid-operation.
 */

export type ShellStatus = 'idle' | 'dirty' | 'saving' | 'published' | 'error'

export interface ShellState {
  status: ShellStatus
  /** True while any blocking operation runs (publishing, loading sections, ...). */
  busy: boolean
  busyLabel: string
  /** Set once the iframe has sent its first `ready` message. */
  iframeReady: boolean
  /** Current page path inside the iframe, synced from `ready` messages. */
  activePath: string | null
  selectedSectionId: string | null
  /** Last publish error, cleared on retry or further edits. */
  error: string | null
  /**
   * Outcome of the last publish: false means "Publiziert, aber Build nicht
   * aktualisiert" (content-publish returned revalidated: false). Null until
   * the first publish completes.
   */
  revalidated: boolean | null
}

export const initialShellState: ShellState = {
  status: 'idle',
  busy: false,
  busyLabel: '',
  iframeReady: false,
  activePath: null,
  selectedSectionId: null,
  error: null,
  revalidated: null,
}

export type ShellEvent =
  | { type: 'iframe-ready'; path: string }
  | { type: 'edit' }
  | { type: 'select-section'; sectionId: string | null }
  | { type: 'discard' }
  | { type: 'publish-start'; busyLabel?: string }
  | { type: 'publish-success'; revalidated: boolean }
  | { type: 'publish-failure'; error: string }
  | { type: 'busy-start'; busyLabel?: string }
  | { type: 'busy-end' }

/**
 * Pure reducer: returns the same state reference when an event does not
 * apply, so callers can cheaply detect "nothing changed".
 */
export function shellReducer(state: ShellState, event: ShellEvent): ShellState {
  switch (event.type) {
    // Always processed, even while busy: navigation inside the iframe is the
    // only source of truth for the active page.
    case 'iframe-ready':
      return { ...state, iframeReady: true, activePath: event.path }

    case 'edit': {
      if (state.busy) return state
      if (state.status === 'saving') return state
      return { ...state, status: 'dirty', error: null }
    }

    case 'select-section': {
      if (state.busy) return state
      if (state.selectedSectionId === event.sectionId) return state
      return { ...state, selectedSectionId: event.sectionId }
    }

    case 'discard': {
      if (state.busy) return state
      if (state.status !== 'dirty' && state.status !== 'error') return state
      return { ...state, status: 'idle', error: null }
    }

    case 'publish-start': {
      if (state.status !== 'dirty' && state.status !== 'error') return state
      return {
        ...state,
        status: 'saving',
        busy: true,
        busyLabel: event.busyLabel ?? 'Publizieren…',
        error: null,
      }
    }

    case 'publish-success': {
      if (state.status !== 'saving') return state
      return {
        ...state,
        status: 'published',
        busy: false,
        busyLabel: '',
        revalidated: event.revalidated,
        error: null,
      }
    }

    case 'publish-failure': {
      if (state.status !== 'saving') return state
      return { ...state, status: 'error', busy: false, busyLabel: '', error: event.error }
    }

    case 'busy-start': {
      // Saving already owns the busy flag; a generic busy cannot preempt it.
      if (state.status === 'saving') return state
      return { ...state, busy: true, busyLabel: event.busyLabel ?? '' }
    }

    case 'busy-end': {
      if (state.status === 'saving') return state
      if (!state.busy && state.busyLabel === '') return state
      return { ...state, busy: false, busyLabel: '' }
    }
  }
}
