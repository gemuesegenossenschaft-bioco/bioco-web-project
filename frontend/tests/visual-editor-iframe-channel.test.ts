import { describe, expect, it, vi } from 'vitest'
import { CMS_PARENT_ORIGIN, MSG_PREFIX } from '@/lib/visual-editor/protocol'
import {
  PARENT_ORIGIN_PARAM,
  createIframeParentChannel,
  resolveParentOrigin,
  type ChannelWindow,
} from '@/lib/visual-editor/iframeChannel'

const SELF_ORIGIN = 'https://www.bioco.ch'

interface FakeWindow extends ChannelWindow {
  parentPostMessage: ReturnType<typeof vi.fn>
  emit(origin: string, data: unknown): void
  listenerCount(): number
}

function createFakeWindow(options: { search?: string; referrer?: string; embedded?: boolean } = {}): FakeWindow {
  const listeners: Array<(event: { origin: string; data: unknown }) => void> = []
  const parentPostMessage = vi.fn()
  const win: FakeWindow = {
    location: { origin: SELF_ORIGIN, search: options.search || '' },
    document: { referrer: options.referrer || '' },
    parent: null,
    addEventListener: (_type, listener) => {
      listeners.push(listener)
    },
    removeEventListener: (_type, listener) => {
      const index = listeners.indexOf(listener)
      if (index !== -1) listeners.splice(index, 1)
    },
    parentPostMessage,
    emit(origin, data) {
      for (const listener of [...listeners]) listener({ origin, data })
    },
    listenerCount: () => listeners.length,
  }
  win.parent = options.embedded === false ? (win as unknown as ChannelWindow['parent']) : { postMessage: parentPostMessage }
  return win
}

describe('resolveParentOrigin', () => {
  it('prefers a valid ?_visual_origin search param', () => {
    expect(PARENT_ORIGIN_PARAM).toBe('_visual_origin')
    expect(
      resolveParentOrigin({
        search: `?_visual=1&${PARENT_ORIGIN_PARAM}=${encodeURIComponent('https://cms.bioco.ch')}`,
        referrer: 'https://other.example/page',
      })
    ).toBe('https://cms.bioco.ch')
  })

  it('normalizes param values with paths down to their origin', () => {
    expect(
      resolveParentOrigin({ search: `?${PARENT_ORIGIN_PARAM}=${encodeURIComponent('https://shell.example/visual-editor/?path=/abos')}` })
    ).toBe('https://shell.example')
  })

  it('falls back to the document referrer origin', () => {
    expect(
      resolveParentOrigin({ search: '?_visual=1', referrer: 'https://cms.bioco.ch/visual-editor/?path=/abos' })
    ).toBe('https://cms.bioco.ch')
  })

  it('ignores invalid params and falls through to the referrer', () => {
    for (const bad of ['not-a-url', 'javascript:alert(1)', 'data:text/html,x', 'null', '']) {
      expect(
        resolveParentOrigin({ search: `?${PARENT_ORIGIN_PARAM}=${encodeURIComponent(bad)}`, referrer: 'https://cms.bioco.ch/x' })
      ).toBe('https://cms.bioco.ch')
    }
  })

  it('returns null when neither param nor referrer yields an http(s) origin', () => {
    expect(resolveParentOrigin({})).toBeNull()
    expect(resolveParentOrigin({ search: '', referrer: '' })).toBeNull()
    expect(resolveParentOrigin({ referrer: 'ftp://files.example/x' })).toBeNull()
    expect(resolveParentOrigin({ search: '?_visual=1' })).toBeNull()
  })
})

describe('createIframeParentChannel', () => {
  it('builds the allowlist from CMS origin, self origin, and the derived parent origin', () => {
    const win = createFakeWindow({ search: `?${PARENT_ORIGIN_PARAM}=${encodeURIComponent('https://shell.example')}` })
    const channel = createIframeParentChannel(win)
    expect(channel.allowedOrigins).toEqual([CMS_PARENT_ORIGIN, SELF_ORIGIN, 'https://shell.example'])
    channel.destroy()
  })

  it('does not duplicate an already-allowlisted derived origin', () => {
    const win = createFakeWindow({ referrer: 'https://cms.bioco.ch/visual-editor/' })
    const channel = createIframeParentChannel(win)
    expect(channel.allowedOrigins).toEqual([CMS_PARENT_ORIGIN, SELF_ORIGIN])
    channel.destroy()
  })

  it('broadcasts once per allowlisted origin when the parent origin is unknown', () => {
    const win = createFakeWindow()
    const channel = createIframeParentChannel(win)
    expect(channel.targetOrigin()).toBeNull()

    channel.send('ready', { path: '/abos' })

    expect(win.parentPostMessage).toHaveBeenCalledTimes(2)
    expect(win.parentPostMessage).toHaveBeenNthCalledWith(1, { type: `${MSG_PREFIX}ready`, path: '/abos' }, CMS_PARENT_ORIGIN)
    expect(win.parentPostMessage).toHaveBeenNthCalledWith(2, { type: `${MSG_PREFIX}ready`, path: '/abos' }, SELF_ORIGIN)
    channel.destroy()
  })

  it('sends only to the derived parent origin when one is resolved', () => {
    const win = createFakeWindow({ referrer: 'https://cms.bioco.ch/visual-editor/?path=/' })
    const channel = createIframeParentChannel(win)
    expect(channel.targetOrigin()).toBe(CMS_PARENT_ORIGIN)

    channel.send('section-click', { sectionId: 'section-1' })

    expect(win.parentPostMessage).toHaveBeenCalledTimes(1)
    expect(win.parentPostMessage).toHaveBeenCalledWith(
      { type: `${MSG_PREFIX}section-click`, sectionId: 'section-1' },
      CMS_PARENT_ORIGIN
    )
    channel.destroy()
  })

  it('adopts the origin of the first accepted inbound message as the outbound target', () => {
    const win = createFakeWindow()
    const channel = createIframeParentChannel(win)

    win.emit(SELF_ORIGIN, { type: `${MSG_PREFIX}save-state`, mode: 'edit' })
    expect(channel.targetOrigin()).toBe(SELF_ORIGIN)

    channel.send('section-click', { sectionId: 'section-1' })
    expect(win.parentPostMessage).toHaveBeenCalledTimes(1)
    expect(win.parentPostMessage).toHaveBeenCalledWith(
      { type: `${MSG_PREFIX}section-click`, sectionId: 'section-1' },
      SELF_ORIGIN
    )
    channel.destroy()
  })

  it('delivers parsed, typed parent messages to subscribers', () => {
    const win = createFakeWindow()
    const channel = createIframeParentChannel(win)
    const onMessage = vi.fn()
    channel.subscribe(onMessage)

    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}section-highlight`, sectionId: 'section-2' })

    expect(onMessage).toHaveBeenCalledTimes(1)
    expect(onMessage).toHaveBeenCalledWith({ type: 'section-highlight', sectionId: 'section-2' }, CMS_PARENT_ORIGIN)
    channel.destroy()
  })

  it('drops messages from origins outside the allowlist without adopting them', () => {
    const win = createFakeWindow()
    const channel = createIframeParentChannel(win)
    const onMessage = vi.fn()
    channel.subscribe(onMessage)

    win.emit('https://evil.example', { type: `${MSG_PREFIX}section-update`, sectionId: 's1', field: 'title', value: 'HACKED' })
    win.emit('', { type: `${MSG_PREFIX}section-update`, sectionId: 's1', field: 'title', value: 'HACKED' })
    win.emit('null', { type: `${MSG_PREFIX}section-update`, sectionId: 's1', field: 'title', value: 'HACKED' })

    expect(onMessage).not.toHaveBeenCalled()
    expect(channel.targetOrigin()).toBeNull()
    channel.destroy()
  })

  it('drops iframe-direction messages reflected back at the iframe', () => {
    const win = createFakeWindow()
    const channel = createIframeParentChannel(win)
    const onMessage = vi.fn()
    channel.subscribe(onMessage)

    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}section-click`, sectionId: 'section-1' })
    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}ready`, path: '/' })

    expect(onMessage).not.toHaveBeenCalled()
    // Reflected traffic must not steer the outbound target either.
    expect(channel.targetOrigin()).toBeNull()
    channel.destroy()
  })

  it('drops malformed payloads from allowed origins', () => {
    const win = createFakeWindow()
    const channel = createIframeParentChannel(win)
    const onMessage = vi.fn()
    channel.subscribe(onMessage)

    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}section-scroll` }) // no sectionId
    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}sections-replace`, sections: [{}] })
    win.emit(CMS_PARENT_ORIGIN, { type: 'unrelated:message' })
    win.emit(CMS_PARENT_ORIGIN, null)

    expect(onMessage).not.toHaveBeenCalled()
    channel.destroy()
  })

  it('no-ops sends when the page is not embedded but still parses inbound messages', () => {
    const win = createFakeWindow({ embedded: false })
    const channel = createIframeParentChannel(win)
    const onMessage = vi.fn()
    channel.subscribe(onMessage)

    channel.send('ready', { path: '/' })
    expect(win.parentPostMessage).not.toHaveBeenCalled()

    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}field-reset` })
    expect(onMessage).toHaveBeenCalledWith({ type: 'field-reset' }, CMS_PARENT_ORIGIN)
    channel.destroy()
  })

  it('supports unsubscribe and destroy', () => {
    const win = createFakeWindow()
    const channel = createIframeParentChannel(win)
    const first = vi.fn()
    const second = vi.fn()
    const unsubscribe = channel.subscribe(first)
    channel.subscribe(second)

    unsubscribe()
    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}field-reset` })
    expect(first).not.toHaveBeenCalled()
    expect(second).toHaveBeenCalledTimes(1)

    channel.destroy()
    expect(win.listenerCount()).toBe(0)
    win.emit(CMS_PARENT_ORIGIN, { type: `${MSG_PREFIX}field-reset` })
    expect(second).toHaveBeenCalledTimes(1)
  })

  it('never throws when postMessage fails', () => {
    const win = createFakeWindow()
    win.parentPostMessage.mockImplementation(() => {
      throw new Error('cross-origin denied')
    })
    const channel = createIframeParentChannel(win)
    expect(() => channel.send('ready', { path: '/' })).not.toThrow()
    channel.destroy()
  })
})
