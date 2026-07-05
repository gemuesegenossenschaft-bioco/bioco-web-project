import { describe, expect, it, vi } from 'vitest'
import { resolvePublishConflict } from '../visual-editor-shell/conflict'

// G.2 — three-way merge on publish 409 (base = snapshot at load, server =
// canonical state from the conflict response, local = the draft). Port of the
// old IIFE's resolvePublishConflict with the window.confirm calls replaced by
// injectable deciders so the merge is testable.

function section(id: string, patch: Record<string, unknown> = {}) {
  return { id, title: `Titel ${id}`, text: '', layout: 'rich_text', theme: 'default', ...patch }
}

const keepLocal = { keepLocalField: () => true, keepLocalOrder: () => true }

describe('resolvePublishConflict', () => {
  it('keeps local-only sections and adopts server-only sections', () => {
    const base = [section('a')]
    const server = [section('a'), section('server-new')]
    const local = [section('a'), section('local-new')]
    const result = resolvePublishConflict(base, server, local, keepLocal)
    const ids = result.mergedSections.map((s) => s.id)
    expect(ids).toContain('local-new')
    expect(ids).toContain('server-new')
    expect(result.conflicts).toEqual([])
  })

  it('merges non-conflicting field changes from both sides without asking', () => {
    const base = [section('a', { title: 'Alt', text: '<p>alt</p>' })]
    const server = [section('a', { title: 'Alt', text: '<p>server</p>' })]
    const local = [section('a', { title: 'Lokal', text: '<p>alt</p>' })]
    const decider = vi.fn(() => true)
    const result = resolvePublishConflict(base, server, local, {
      keepLocalField: decider,
      keepLocalOrder: () => true,
    })
    expect(decider).not.toHaveBeenCalled()
    expect(result.mergedSections[0]).toMatchObject({ title: 'Lokal', text: '<p>server</p>' })
    expect(result.conflicts).toEqual([])
  })

  it('asks the decider on true conflicts and records the resolution', () => {
    const base = [section('a', { title: 'Alt' })]
    const server = [section('a', { title: 'Server' })]
    const local = [section('a', { title: 'Lokal' })]

    const localWins = resolvePublishConflict(base, server, local, keepLocal)
    expect(localWins.mergedSections[0].title).toBe('Lokal')
    expect(localWins.conflicts).toEqual([{ sectionId: 'a', field: 'title', keep: 'local' }])

    const serverWins = resolvePublishConflict(base, server, local, {
      keepLocalField: () => false,
      keepLocalOrder: () => true,
    })
    expect(serverWins.mergedSections[0].title).toBe('Server')
    expect(serverWins.conflicts).toEqual([{ sectionId: 'a', field: 'title', keep: 'server' }])
  })

  it('adopts the server order when only the server reordered', () => {
    const base = [section('a'), section('b')]
    const server = [section('b'), section('a')]
    const local = [section('a'), section('b')]
    const orderDecider = vi.fn(() => true)
    const result = resolvePublishConflict(base, server, local, {
      keepLocalField: () => true,
      keepLocalOrder: orderDecider,
    })
    expect(orderDecider).not.toHaveBeenCalled()
    expect(result.keepLocalOrder).toBe(false)
    expect(result.mergedSections.map((s) => s.id)).toEqual(['b', 'a'])
  })

  it('asks the order decider when both sides reordered differently', () => {
    const base = [section('a'), section('b'), section('c')]
    const server = [section('c'), section('a'), section('b')]
    const local = [section('b'), section('a'), section('c')]
    const result = resolvePublishConflict(base, server, local, {
      keepLocalField: () => true,
      keepLocalOrder: () => false,
    })
    expect(result.keepLocalOrder).toBe(false)
    expect(result.mergedSections.map((s) => s.id)).toEqual(['c', 'a', 'b'])
  })
})
