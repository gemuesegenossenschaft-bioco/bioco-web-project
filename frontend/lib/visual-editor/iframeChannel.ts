/**
 * Iframe-side counterpart of visual-editor-shell/bridge.ts: the ONE
 * postMessage channel implementation shared by useVisualEditor.ts and
 * InlineVisualEditorRuntime.tsx (which used to duplicate prefix parsing,
 * `isInIframe`, and `sendToParent` with targetOrigin '*' and no origin
 * validation — G.1 latent bug 1).
 *
 * Every inbound message is validated by protocol.ts parseMessage against an
 * origin allowlist; only parent-direction message types are dispatched
 * (iframe-direction types reflected or spoofed back are dropped). Every
 * outbound message is encoded by protocol.ts and posted with an explicit
 * targetOrigin — never '*'.
 *
 * Parent-origin derivation (documented contract):
 *   1. `?_visual_origin=<origin>` search param, when it parses as an http(s)
 *      origin. The shell does not send it today; it is the explicit override
 *      for previews/staging shells. It cannot be abused to exfiltrate to a
 *      foreign embedder: CSP `frame-ancestors 'self' https://cms.bioco.ch`
 *      (next.config.js) means only the CMS shell or the site itself can
 *      frame the site at all.
 *   2. `document.referrer` origin (the embedding page on first iframe load).
 *   3. Otherwise null — the channel falls back to the static allowlist
 *      (cms.bioco.ch + same-origin) and broadcasts outbound messages once
 *      per allowlisted origin; the browser only delivers the post whose
 *      targetOrigin matches the real parent.
 * A derived origin extends the allowlist and seeds the outbound target; like
 * the parent bridge, the channel then adopts the origin of the last accepted
 * inbound message (the site may redirect between its www/non-www twin).
 */

import {
  PARENT_MESSAGE_TYPES,
  buildAllowedOrigins,
  createMessage,
  encodeMessage,
  parseMessage,
  type IframeToParentMessage,
  type MessageOfType,
  type ParentToIframeMessage,
} from './protocol'

/** Search param carrying an explicit parent-shell origin. */
export const PARENT_ORIGIN_PARAM = '_visual_origin'

const PARENT_TYPE_SET: ReadonlySet<string> = new Set(PARENT_MESSAGE_TYPES)

/** The minimal window surface the channel needs (keeps tests DOM-free). */
export interface ChannelWindow {
  location: { origin: string; search: string }
  document: { referrer: string }
  parent: { postMessage(data: unknown, targetOrigin: string): void } | null
  addEventListener(type: 'message', listener: (event: { origin: string; data: unknown }) => void): void
  removeEventListener(type: 'message', listener: (event: { origin: string; data: unknown }) => void): void
}

export interface IframeParentChannel {
  /** Origins accepted for inbound messages and used as outbound targets. */
  readonly allowedOrigins: readonly string[]
  /** Current outbound target; null = unknown parent (broadcast to allowlist). */
  targetOrigin(): string | null
  send<T extends IframeToParentMessage['type']>(type: T, payload: Omit<MessageOfType<T>, 'type'>): void
  subscribe(listener: (message: ParentToIframeMessage, origin: string) => void): () => void
  destroy(): void
}

/** Parse a candidate value down to a safe http(s) origin, or null. */
function toHttpOrigin(value: unknown): string | null {
  if (typeof value !== 'string' || !value) return null
  try {
    const url = new URL(value)
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return null
    return url.origin && url.origin !== 'null' ? url.origin : null
  } catch {
    return null
  }
}

/**
 * Derive the embedding shell's origin: explicit `?_visual_origin` param
 * first, then the document referrer, else null (see module docs).
 */
export function resolveParentOrigin(source: { search?: string; referrer?: string }): string | null {
  const param = new URLSearchParams(source.search || '').get(PARENT_ORIGIN_PARAM)
  return toHttpOrigin(param) || toHttpOrigin(source.referrer)
}

function isEmbedded(win: ChannelWindow): boolean {
  try {
    return !!win.parent && (win.parent as unknown) !== (win as unknown)
  } catch {
    return false
  }
}

export function createIframeParentChannel(
  win: ChannelWindow = window as unknown as ChannelWindow
): IframeParentChannel {
  const parentOrigin = resolveParentOrigin({
    search: win.location.search,
    referrer: win.document.referrer,
  })
  const allowedOrigins = buildAllowedOrigins(win.location.origin)
  if (parentOrigin && !allowedOrigins.includes(parentOrigin)) allowedOrigins.push(parentOrigin)

  let adoptedOrigin: string | null = parentOrigin
  const subscribers = new Set<(message: ParentToIframeMessage, origin: string) => void>()

  const listener = (event: { origin: string; data: unknown }) => {
    const message = parseMessage(event, allowedOrigins)
    if (!message) return
    // Only parent->iframe messages may drive the runtimes; iframe-direction
    // types reflected back (or spoofed) are dropped and never adopt a target.
    if (!PARENT_TYPE_SET.has(message.type)) return
    adoptedOrigin = event.origin
    for (const subscriber of Array.from(subscribers)) subscriber(message as ParentToIframeMessage, event.origin)
  }
  win.addEventListener('message', listener)

  return {
    allowedOrigins,
    targetOrigin: () => adoptedOrigin,
    send(type, payload) {
      if (!isEmbedded(win)) return
      const wire = encodeMessage(createMessage(type, payload))
      const targets = adoptedOrigin ? [adoptedOrigin] : allowedOrigins
      for (const target of targets) {
        try {
          win.parent!.postMessage(wire, target)
        } catch {
          // cross-origin or unavailable — same silent tolerance as before
        }
      }
    },
    subscribe(subscriber) {
      subscribers.add(subscriber)
      return () => {
        subscribers.delete(subscriber)
      }
    },
    destroy() {
      subscribers.clear()
      win.removeEventListener('message', listener)
    },
  }
}
