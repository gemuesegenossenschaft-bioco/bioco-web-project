/**
 * Typed postMessage protocol between the ProcessWire visual-editor shell
 * (site/templates/visual-editor.php, the "parent") and the Next.js site
 * rendered inside its iframe (useVisualEditor.ts + InlineVisualEditorRuntime.tsx).
 *
 * This module formalizes the wire format both sides already speak today:
 * every message is a flat object whose `type` is `MSG_PREFIX + <bare type>`
 * and whose payload fields sit next to `type` (no nested envelope).
 *
 * It is dependency-free and UI-agnostic so the PHP shell's JS can be
 * aligned with it without pulling in React or DOM types.
 */

export const MSG_PREFIX = 'bioco:visual-editor:'

/** Mode the parent shell drives; anything unknown normalizes to 'edit'. */
export type VisualEditorMode = 'edit' | 'browse'

/** Section toolbar actions the iframe can request from the parent. */
export type SectionActionKind = 'duplicate' | 'move-up' | 'move-down' | 'delete'

const SECTION_ACTIONS: readonly SectionActionKind[] = ['duplicate', 'move-up', 'move-down', 'delete']

/**
 * Shared descriptor for a focused/selected field. Mirrors the
 * `data-ve-*` markers (fieldAttrs.ts) and the parent's `activeField`.
 * `kind` is a free-form marker string ('text', 'button', 'richtext',
 * 'media', ... — see data-ve-kind usage), deliberately not a closed union.
 */
export interface VeFieldDescriptor {
  sectionId: string
  field: string
  kind: string
  /** Defaults to true on the wire unless explicitly false (matches both runtimes). */
  inline: boolean
  buttonIndex?: number
  targetField?: string
}

/** Payload of the parent's `save-state` broadcast (syncIframeState in visual-editor.php). */
export interface SaveStatePayload {
  mode: VisualEditorMode
  dirty: boolean
  saving: boolean
  busy: boolean
  busyLabel: string
  message: string
  selectedSectionId: string | null
  presetTagsByComponent: Record<string, string[]>
}

/** Messages the parent shell sends into the iframe. */
export type ParentToIframeMessage =
  | ({ type: 'save-state' } & SaveStatePayload)
  | { type: 'section-highlight'; sectionId: string | null }
  | ({ type: 'field-highlight' } & VeFieldDescriptor)
  | { type: 'field-reset' }
  | { type: 'section-scroll'; sectionId: string }
  | { type: 'section-update'; sectionId: string; field: string; value?: unknown }
  | { type: 'save-result'; success: boolean; revalidated?: boolean; error?: string }

/** Messages the iframe runtime sends up to the parent shell. */
export type IframeToParentMessage =
  | { type: 'ready'; path: string; sectionIds: string[] }
  | { type: 'section-click'; sectionId: string }
  | ({ type: 'field-select' } & VeFieldDescriptor)
  | {
      type: 'field-change'
      sectionId: string
      field: string
      value?: unknown
      buttonIndex?: number
      configKey?: string
    }
  | {
      type: 'field-commit'
      sectionId: string
      field: string
      value?: unknown
      buttonIndex?: number
      configKey?: string
    }
  | { type: 'media-request'; sectionId: string; targetField?: string }
  | {
      type: 'open-processwire'
      sectionId: string
      field?: string
      kind?: string
      inline?: boolean
      buttonIndex?: number
      targetField?: string
    }
  | { type: 'section-action'; sectionId: string; action: SectionActionKind }

export type VisualEditorMessage = ParentToIframeMessage | IframeToParentMessage

export type VisualEditorMessageType = VisualEditorMessage['type']
export type MessageOfType<T extends VisualEditorMessageType> = Extract<VisualEditorMessage, { type: T }>

/* ------------------------------------------------------------------ */
/* Structural parsing                                                  */
/* ------------------------------------------------------------------ */

type UnknownRecord = Record<string, unknown>

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isNonEmptyString(value: unknown): value is string {
  return typeof value === 'string' && value.length > 0
}

function sanitizeStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) return []
  return value.filter((entry): entry is string => typeof entry === 'string')
}

function sanitizePresetTags(value: unknown): Record<string, string[]> {
  if (!isRecord(value)) return {}
  const out: Record<string, string[]> = {}
  for (const [key, entry] of Object.entries(value)) {
    if (!Array.isArray(entry)) continue
    out[key] = entry.filter((tag): tag is string => typeof tag === 'string')
  }
  return out
}

function parseFieldDescriptor(payload: UnknownRecord): VeFieldDescriptor | null {
  if (!isNonEmptyString(payload.sectionId)) return null
  if (!isNonEmptyString(payload.field)) return null
  if (!isNonEmptyString(payload.kind)) return null
  return {
    sectionId: payload.sectionId,
    field: payload.field,
    kind: payload.kind,
    // Both runtimes treat everything except an explicit false as inline.
    inline: payload.inline !== false,
    ...(typeof payload.buttonIndex === 'number' ? { buttonIndex: payload.buttonIndex } : {}),
    ...(isNonEmptyString(payload.targetField) ? { targetField: payload.targetField } : {}),
  }
}

function parseFieldEdit(payload: UnknownRecord): Omit<MessageOfType<'field-change'>, 'type'> | null {
  if (!isNonEmptyString(payload.sectionId)) return null
  if (!isNonEmptyString(payload.field)) return null
  return {
    sectionId: payload.sectionId,
    field: payload.field,
    value: payload.value,
    ...(typeof payload.buttonIndex === 'number' ? { buttonIndex: payload.buttonIndex } : {}),
    ...(isNonEmptyString(payload.configKey) ? { configKey: payload.configKey } : {}),
  }
}

type ParserMap = {
  [K in VisualEditorMessageType]: (payload: UnknownRecord) => MessageOfType<K> | null
}

/**
 * One structural parser per message type. The mapped type makes this map
 * exhaustive at compile time: adding a member to either union without a
 * parser is a type error.
 */
const PARSERS: ParserMap = {
  /* ---- parent -> iframe ---- */
  'save-state': (payload) => ({
    type: 'save-state',
    mode: payload.mode === 'browse' ? 'browse' : 'edit',
    dirty: Boolean(payload.dirty),
    saving: Boolean(payload.saving),
    busy: Boolean(payload.busy),
    busyLabel: typeof payload.busyLabel === 'string' ? payload.busyLabel : '',
    message: typeof payload.message === 'string' ? payload.message : '',
    selectedSectionId: isNonEmptyString(payload.selectedSectionId) ? payload.selectedSectionId : null,
    presetTagsByComponent: sanitizePresetTags(payload.presetTagsByComponent),
  }),
  'section-highlight': (payload) => ({
    type: 'section-highlight',
    sectionId: isNonEmptyString(payload.sectionId) ? payload.sectionId : null,
  }),
  'field-highlight': (payload) => {
    const descriptor = parseFieldDescriptor(payload)
    return descriptor ? { type: 'field-highlight', ...descriptor } : null
  },
  'field-reset': () => ({ type: 'field-reset' }),
  'section-scroll': (payload) =>
    isNonEmptyString(payload.sectionId) ? { type: 'section-scroll', sectionId: payload.sectionId } : null,
  'section-update': (payload) => {
    if (!isNonEmptyString(payload.sectionId) || !isNonEmptyString(payload.field)) return null
    return { type: 'section-update', sectionId: payload.sectionId, field: payload.field, value: payload.value }
  },
  'save-result': (payload) => {
    if (typeof payload.success !== 'boolean') return null
    return {
      type: 'save-result',
      success: payload.success,
      ...(typeof payload.revalidated === 'boolean' ? { revalidated: payload.revalidated } : {}),
      ...(isNonEmptyString(payload.error) ? { error: payload.error } : {}),
    }
  },

  /* ---- iframe -> parent ---- */
  ready: (payload) => {
    if (typeof payload.path !== 'string') return null
    return { type: 'ready', path: payload.path, sectionIds: sanitizeStringArray(payload.sectionIds) }
  },
  'section-click': (payload) =>
    isNonEmptyString(payload.sectionId) ? { type: 'section-click', sectionId: payload.sectionId } : null,
  'field-select': (payload) => {
    const descriptor = parseFieldDescriptor(payload)
    return descriptor ? { type: 'field-select', ...descriptor } : null
  },
  'field-change': (payload) => {
    const edit = parseFieldEdit(payload)
    return edit ? { type: 'field-change', ...edit } : null
  },
  'field-commit': (payload) => {
    const edit = parseFieldEdit(payload)
    return edit ? { type: 'field-commit', ...edit } : null
  },
  'media-request': (payload) => {
    if (!isNonEmptyString(payload.sectionId)) return null
    return {
      type: 'media-request',
      sectionId: payload.sectionId,
      ...(isNonEmptyString(payload.targetField) ? { targetField: payload.targetField } : {}),
    }
  },
  'open-processwire': (payload) => {
    if (!isNonEmptyString(payload.sectionId)) return null
    return {
      type: 'open-processwire',
      sectionId: payload.sectionId,
      ...(isNonEmptyString(payload.field) ? { field: payload.field } : {}),
      ...(isNonEmptyString(payload.kind) ? { kind: payload.kind } : {}),
      // Unlike field descriptors, `inline` is carried verbatim here: the
      // iframe only sends it when a field is focused.
      ...(typeof payload.inline === 'boolean' ? { inline: payload.inline } : {}),
      ...(typeof payload.buttonIndex === 'number' ? { buttonIndex: payload.buttonIndex } : {}),
      ...(isNonEmptyString(payload.targetField) ? { targetField: payload.targetField } : {}),
    }
  },
  'section-action': (payload) => {
    if (!isNonEmptyString(payload.sectionId)) return null
    if (!SECTION_ACTIONS.includes(payload.action as SectionActionKind)) return null
    return { type: 'section-action', sectionId: payload.sectionId, action: payload.action as SectionActionKind }
  },
}

/**
 * Runtime catalogs of the two directions. `satisfies` pins each array to the
 * exact union: a missing or extra entry is a compile error, so these stay
 * exhaustive as the unions evolve.
 */
export const PARENT_MESSAGE_TYPES = [
  'save-state',
  'section-highlight',
  'field-highlight',
  'field-reset',
  'section-scroll',
  'section-update',
  'save-result',
] as const satisfies readonly ParentToIframeMessage['type'][]

export const IFRAME_MESSAGE_TYPES = [
  'ready',
  'section-click',
  'field-select',
  'field-change',
  'field-commit',
  'media-request',
  'open-processwire',
  'section-action',
] as const satisfies readonly IframeToParentMessage['type'][]

// Compile-time completeness: every union member must appear in its catalog.
type AssertCatalogComplete<Union extends string, Catalog extends Union> = Exclude<Union, Catalog>
type _ParentCatalogComplete = AssertCatalogComplete<ParentToIframeMessage['type'], (typeof PARENT_MESSAGE_TYPES)[number]> extends never ? true : never
type _IframeCatalogComplete = AssertCatalogComplete<IframeToParentMessage['type'], (typeof IFRAME_MESSAGE_TYPES)[number]> extends never ? true : never
const _parentCatalogComplete: _ParentCatalogComplete = true
const _iframeCatalogComplete: _IframeCatalogComplete = true
void _parentCatalogComplete
void _iframeCatalogComplete

const PARENT_TYPE_SET: ReadonlySet<string> = new Set(PARENT_MESSAGE_TYPES)
const IFRAME_TYPE_SET: ReadonlySet<string> = new Set(IFRAME_MESSAGE_TYPES)

/** Wire shape actually put on postMessage: prefixed type + flattened payload. */
export type VisualEditorWireMessage = { type: string } & UnknownRecord

/* ------------------------------------------------------------------ */
/* Public API                                                          */
/* ------------------------------------------------------------------ */

/**
 * Origin of the ProcessWire admin shell that embeds the site. Iframe-side
 * listeners must accept messages from here; the parent shell must accept
 * messages from the site's own origin.
 */
export const CMS_PARENT_ORIGIN = 'https://cms.bioco.ch'

/**
 * Default allowlist: the CMS shell origin plus (optionally) the caller's own
 * origin — same-origin covers local dev, where shell and site share a host.
 * Opaque ('null') and empty origins are never allowlisted.
 */
export function buildAllowedOrigins(selfOrigin?: string | null): string[] {
  const origins = [CMS_PARENT_ORIGIN]
  if (isNonEmptyString(selfOrigin) && selfOrigin !== 'null' && !origins.includes(selfOrigin)) {
    origins.push(selfOrigin)
  }
  return origins
}

/** Typed factory: `createMessage('section-click', { sectionId })`. */
export function createMessage<T extends VisualEditorMessageType>(
  type: T,
  payload: Omit<MessageOfType<T>, 'type'>
): MessageOfType<T> {
  return { type, ...payload } as MessageOfType<T>
}

/** Flatten a typed message into the wire format both sides already speak. */
export function encodeMessage(message: VisualEditorMessage): VisualEditorWireMessage {
  const { type, ...payload } = message
  return { type: `${MSG_PREFIX}${type}`, ...payload }
}

/** Structurally validate a decoded (bare-typed) message. */
function parseDecoded(value: unknown, allowedTypes?: ReadonlySet<string>): VisualEditorMessage | null {
  if (!isRecord(value)) return null
  const type = value.type
  if (typeof type !== 'string') return null
  if (allowedTypes && !allowedTypes.has(type)) return null
  const parser = (PARSERS as Record<string, (payload: UnknownRecord) => VisualEditorMessage | null>)[type]
  if (!parser) return null
  return parser(value)
}

/**
 * Validate origin + wire shape and return a clean, typed message, or null.
 * Never throws. Unknown types, foreign origins, and malformed payloads all
 * yield null; loosely typed payloads are normalized (see PARSERS).
 */
export function parseMessage(
  event: { origin: string; data: unknown },
  allowedOrigins: readonly string[]
): VisualEditorMessage | null {
  if (!isAllowedOrigin(event.origin, allowedOrigins)) return null
  if (!isRecord(event.data)) return null
  const wireType = event.data.type
  if (typeof wireType !== 'string' || !wireType.startsWith(MSG_PREFIX)) return null
  return parseDecoded({ ...event.data, type: wireType.slice(MSG_PREFIX.length) })
}

/** True when `origin` is a non-opaque origin present in the allowlist. */
export function isAllowedOrigin(origin: unknown, allowedOrigins: readonly string[]): boolean {
  if (!isNonEmptyString(origin) || origin === 'null') return false
  return allowedOrigins.includes(origin)
}

/** Structural guard: is this a message the iframe sends to the parent? */
export function isIframeMessage(value: unknown): value is IframeToParentMessage {
  return parseDecoded(value, IFRAME_TYPE_SET) !== null
}

/** Structural guard: is this a message the parent sends to the iframe? */
export function isParentMessage(value: unknown): value is ParentToIframeMessage {
  return parseDecoded(value, PARENT_TYPE_SET) !== null
}
