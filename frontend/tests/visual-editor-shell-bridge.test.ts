import { describe, expect, it, vi } from 'vitest'
import { createIframeBridge } from '../visual-editor-shell/bridge'
import { MSG_PREFIX, parseMessage } from '../lib/visual-editor/protocol'

// G.2 — ALL iframe I/O of the shell goes through protocol.ts. The bridge
// enforces the origin allowlist on inbound messages (the old IIFE accepted
// any origin) and posts with an explicit targetOrigin (the old IIFE used '*').

type Listener = (event: MessageEvent) => void

function fakeWindow() {
  const listeners = new Set<Listener>()
  return {
    addEventListener: vi.fn((type: string, fn: Listener) => {
      if (type === 'message') listeners.add(fn)
    }),
    removeEventListener: vi.fn((type: string, fn: Listener) => {
      if (type === 'message') listeners.delete(fn)
    }),
    emit(origin: string, data: unknown) {
      for (const fn of Array.from(listeners)) fn({ origin, data } as MessageEvent)
    },
    listenerCount: () => listeners.size,
  }
}

const ORIGINS = ['https://bioco.ch', 'https://www.bioco.ch']

function setup() {
  const win = fakeWindow()
  const postMessage = vi.fn()
  const onMessage = vi.fn()
  const bridge = createIframeBridge({
    listenWindow: win as unknown as Window,
    origins: ORIGINS,
    defaultTargetOrigin: 'https://bioco.ch',
    getTargetWindow: () => ({ postMessage } as unknown as Window),
    onMessage,
  })
  return { win, postMessage, onMessage, bridge }
}

describe('createIframeBridge', () => {
  it('delivers typed iframe messages from allowlisted origins only', () => {
    const { win, onMessage } = setup()

    win.emit('https://evil.example', { type: `${MSG_PREFIX}ready`, path: '/', sectionIds: [] })
    expect(onMessage).not.toHaveBeenCalled()

    win.emit('https://bioco.ch', { type: `${MSG_PREFIX}ready`, path: '/abos', sectionIds: ['s1'] })
    expect(onMessage).toHaveBeenCalledWith({ type: 'ready', path: '/abos', sectionIds: ['s1'] }, 'https://bioco.ch')
  })

  it('drops malformed, unprefixed and parent-direction messages', () => {
    const { win, onMessage } = setup()
    win.emit('https://bioco.ch', { type: 'ready', path: '/' })
    win.emit('https://bioco.ch', 'junk')
    win.emit('https://bioco.ch', { type: `${MSG_PREFIX}nonsense` })
    // parent->iframe types must not loop back into the shell handler
    win.emit('https://bioco.ch', { type: `${MSG_PREFIX}section-highlight`, sectionId: 's1' })
    expect(onMessage).not.toHaveBeenCalled()
  })

  it('posts encoded wire messages with an explicit targetOrigin, never "*"', () => {
    const { postMessage, bridge } = setup()
    bridge.send('section-highlight', { sectionId: 's1' })
    expect(postMessage).toHaveBeenCalledWith(
      { type: `${MSG_PREFIX}section-highlight`, sectionId: 's1' },
      'https://bioco.ch'
    )
  })

  it('adopts the origin of the last accepted inbound message as target', () => {
    const { win, postMessage, bridge } = setup()
    win.emit('https://www.bioco.ch', { type: `${MSG_PREFIX}ready`, path: '/', sectionIds: [] })
    bridge.send('field-reset', {})
    expect(postMessage).toHaveBeenCalledWith({ type: `${MSG_PREFIX}field-reset` }, 'https://www.bioco.ch')
  })

  it('supports the sections-replace broadcast (protocol addition)', () => {
    const { postMessage, bridge } = setup()
    const sections = [{ id: 's1', title: 'T' }]
    bridge.send('sections-replace', { sections })
    expect(postMessage).toHaveBeenCalledWith({ type: `${MSG_PREFIX}sections-replace`, sections }, 'https://bioco.ch')

    // the protocol itself must round-trip it (iframe side parses via parseMessage)
    const parsed = parseMessage(
      { origin: 'https://cms.bioco.ch', data: { type: `${MSG_PREFIX}sections-replace`, sections } },
      ['https://cms.bioco.ch']
    )
    expect(parsed).toEqual({ type: 'sections-replace', sections })
    const malformed = parseMessage(
      { origin: 'https://cms.bioco.ch', data: { type: `${MSG_PREFIX}sections-replace`, sections: 'junk' } },
      ['https://cms.bioco.ch']
    )
    expect(malformed).toBeNull()
  })

  it('stops listening after destroy', () => {
    const { win, onMessage, bridge } = setup()
    bridge.destroy()
    expect(win.listenerCount()).toBe(0)
    win.emit('https://bioco.ch', { type: `${MSG_PREFIX}ready`, path: '/', sectionIds: [] })
    expect(onMessage).not.toHaveBeenCalled()
  })
})
