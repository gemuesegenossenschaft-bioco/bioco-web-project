'use client'

/**
 * React glue for iframeChannel.ts — the single subscription point both
 * iframe-side consumers (useVisualEditor, InlineVisualEditorRuntime) share.
 * `send` is referentially stable and no-ops while disabled or before the
 * channel exists, so debounced timers may safely fire after unmount.
 */

import { useCallback, useEffect, useRef } from 'react'
import { createIframeParentChannel, type IframeParentChannel } from './iframeChannel'
import type { IframeToParentMessage, MessageOfType, ParentToIframeMessage } from './protocol'

export interface UseIframeChannelOptions {
  enabled: boolean
  onMessage?: (message: ParentToIframeMessage, origin: string) => void
}

export type IframeChannelSend = <T extends IframeToParentMessage['type']>(
  type: T,
  payload: Omit<MessageOfType<T>, 'type'>
) => void

export function useIframeChannel({ enabled, onMessage }: UseIframeChannelOptions): { send: IframeChannelSend } {
  const channelRef = useRef<IframeParentChannel | null>(null)
  const onMessageRef = useRef(onMessage)

  useEffect(() => {
    onMessageRef.current = onMessage
  }, [onMessage])

  useEffect(() => {
    if (!enabled || typeof window === 'undefined') return
    const channel = createIframeParentChannel()
    channelRef.current = channel
    const unsubscribe = channel.subscribe((message, origin) => {
      onMessageRef.current?.(message, origin)
    })
    return () => {
      unsubscribe()
      channel.destroy()
      channelRef.current = null
    }
  }, [enabled])

  const send = useCallback<IframeChannelSend>((type, payload) => {
    channelRef.current?.send(type, payload)
  }, [])

  return { send }
}
