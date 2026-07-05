/**
 * The shell's ONLY channel to the site iframe. Every inbound message is
 * validated by protocol.ts parseMessage against the config-derived origin
 * allowlist (the old IIFE accepted messages from any origin), every outbound
 * message is encoded by protocol.ts encodeMessage and posted with an explicit
 * targetOrigin (the old IIFE posted with '*').
 *
 * The site may redirect between its www/non-www twin, so the bridge adopts
 * the origin of the last accepted inbound message as the outbound target.
 */

import {
  IFRAME_MESSAGE_TYPES,
  createMessage,
  encodeMessage,
  parseMessage,
  type IframeToParentMessage,
  type MessageOfType,
  type ParentToIframeMessage,
} from '../lib/visual-editor/protocol'

const IFRAME_TYPE_SET: ReadonlySet<string> = new Set(IFRAME_MESSAGE_TYPES)

export interface IframeBridgeOptions {
  /** Window the shell listens on (the CMS admin window). */
  listenWindow: Window
  /** Allowed origins for inbound messages and valid outbound targets. */
  origins: readonly string[]
  /** Outbound targetOrigin until the first accepted inbound message arrives. */
  defaultTargetOrigin: string
  /** The iframe's contentWindow (may be null before the iframe loads). */
  getTargetWindow: () => Window | null
  onMessage: (message: IframeToParentMessage, origin: string) => void
}

export interface IframeBridge {
  send<T extends ParentToIframeMessage['type']>(type: T, payload: Omit<MessageOfType<T>, 'type'>): void
  /** Origin currently used as postMessage target. */
  targetOrigin(): string
  destroy(): void
}

export function createIframeBridge(options: IframeBridgeOptions): IframeBridge {
  let currentTargetOrigin = options.defaultTargetOrigin

  const handler = (event: MessageEvent) => {
    const message = parseMessage({ origin: event.origin, data: event.data }, options.origins)
    if (!message) return
    // Only iframe->parent messages may drive the shell; parent->iframe types
    // reflected back (or spoofed) are dropped.
    if (!IFRAME_TYPE_SET.has(message.type)) return
    currentTargetOrigin = event.origin
    options.onMessage(message as IframeToParentMessage, event.origin)
  }

  options.listenWindow.addEventListener('message', handler)

  return {
    send(type, payload) {
      const target = options.getTargetWindow()
      if (!target) return
      target.postMessage(encodeMessage(createMessage(type, payload)), currentTargetOrigin)
    },
    targetOrigin: () => currentTargetOrigin,
    destroy() {
      options.listenWindow.removeEventListener('message', handler)
    },
  }
}
