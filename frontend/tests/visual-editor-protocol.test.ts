import { describe, expect, it } from 'vitest'
import {
  CMS_PARENT_ORIGIN,
  IFRAME_MESSAGE_TYPES,
  MSG_PREFIX,
  PARENT_MESSAGE_TYPES,
  buildAllowedOrigins,
  createMessage,
  encodeMessage,
  isAllowedOrigin,
  isIframeMessage,
  isParentMessage,
  parseMessage,
  type IframeToParentMessage,
  type ParentToIframeMessage,
  type VisualEditorMessage,
} from '@/lib/visual-editor/protocol'

const PARENT_ORIGIN = 'https://cms.bioco.ch'
const SITE_ORIGIN = 'https://www.bioco.ch'
const ALLOWED = [PARENT_ORIGIN, SITE_ORIGIN]

function roundTrip(message: VisualEditorMessage): VisualEditorMessage | null {
  return parseMessage({ origin: PARENT_ORIGIN, data: encodeMessage(message) }, ALLOWED)
}

const parentMessages: ParentToIframeMessage[] = [
  {
    type: 'save-state',
    mode: 'edit',
    dirty: true,
    saving: false,
    busy: true,
    busyLabel: 'Änderungen publizieren…',
    message: 'Publiziert',
    selectedSectionId: 'section-1',
    presetTagsByComponent: { pricing_table: ['Abos', 'Preise'] },
  },
  {
    type: 'save-state',
    mode: 'browse',
    dirty: false,
    saving: false,
    busy: false,
    busyLabel: '',
    message: '',
    selectedSectionId: null,
    presetTagsByComponent: {},
  },
  { type: 'section-highlight', sectionId: 'section-1' },
  { type: 'section-highlight', sectionId: null },
  { type: 'field-highlight', sectionId: 'section-1', field: 'title', kind: 'text', inline: true },
  {
    type: 'field-highlight',
    sectionId: 'section-1',
    field: 'button_text',
    kind: 'button',
    inline: false,
    buttonIndex: 1,
    targetField: 'section_image',
  },
  { type: 'field-reset' },
  { type: 'section-scroll', sectionId: 'section-2' },
  { type: 'section-update', sectionId: 'section-1', field: 'title', value: 'Neuer Titel' },
  { type: 'section-update', sectionId: 'section-1', field: 'buttons', value: [{ text: 'A', href: '/a', variant: 'primary' }] },
  { type: 'sections-replace', sections: [{ id: 'section-1', title: 'Neuer Titel' }] },
  { type: 'save-result', success: true, revalidated: false },
  { type: 'save-result', success: false, error: 'Publizieren fehlgeschlagen' },
]

const iframeMessages: IframeToParentMessage[] = [
  { type: 'ready', path: '/abos' },
  // sectionIds is a deprecated dead payload (the shell never reads it); the
  // parser must keep tolerating senders that still include it.
  { type: 'ready', path: '/', sectionIds: [] },
  { type: 'section-click', sectionId: 'section-1' },
  { type: 'field-select', sectionId: 'section-1', field: 'text', kind: 'richtext', inline: true },
  {
    type: 'field-select',
    sectionId: 'section-1',
    field: 'media',
    kind: 'media',
    inline: false,
    targetField: 'section_images',
  },
  { type: 'field-change', sectionId: 'section-1', field: 'title', value: 'Neu' },
  { type: 'field-change', sectionId: 'section-1', field: 'button_href', value: '/kontakt', buttonIndex: 1 },
  { type: 'field-change', sectionId: 'section-1', field: 'config', value: 'right', configKey: 'mediaSide' },
  { type: 'field-commit', sectionId: 'section-1', field: 'text', value: '<p>Fertig</p>' },
  { type: 'field-commit', sectionId: 'section-1', field: 'button_text', value: 'Los', buttonIndex: 0 },
  { type: 'media-request', sectionId: 'section-1', targetField: 'section_image' },
  { type: 'open-processwire', sectionId: 'section-1' },
  {
    type: 'open-processwire',
    sectionId: 'section-1',
    field: 'button',
    kind: 'button',
    inline: true,
    buttonIndex: 1,
    targetField: 'section_image',
  },
  { type: 'section-action', sectionId: 'section-1', action: 'duplicate' },
  { type: 'section-action', sectionId: 'section-1', action: 'move-up' },
  { type: 'section-action', sectionId: 'section-1', action: 'move-down' },
  { type: 'section-action', sectionId: 'section-1', action: 'delete' },
]

describe('visual editor postMessage protocol', () => {
  it('exposes the shared message prefix', () => {
    expect(MSG_PREFIX).toBe('bioco:visual-editor:')
  })

  it('encodes messages with the prefixed wire type and flattened payload', () => {
    expect(encodeMessage({ type: 'section-click', sectionId: 'section-1' })).toEqual({
      type: 'bioco:visual-editor:section-click',
      sectionId: 'section-1',
    })
  })

  it('round-trips every parent-to-iframe message', () => {
    for (const message of parentMessages) {
      expect(roundTrip(message), `round trip failed for ${message.type}`).toEqual(message)
    }
  })

  it('round-trips every iframe-to-parent message', () => {
    for (const message of iframeMessages) {
      expect(roundTrip(message), `round trip failed for ${message.type}`).toEqual(message)
    }
  })

  it('rejects messages from origins outside the allowlist', () => {
    const data = encodeMessage({ type: 'section-click', sectionId: 'section-1' })
    expect(parseMessage({ origin: 'https://evil.example', data }, ALLOWED)).toBeNull()
    expect(parseMessage({ origin: '', data }, ALLOWED)).toBeNull()
    expect(parseMessage({ origin: 'null', data }, ALLOWED)).toBeNull()
    expect(parseMessage({ origin: PARENT_ORIGIN, data }, [])).toBeNull()
  })

  it('accepts any allowlisted origin', () => {
    const data = encodeMessage({ type: 'field-reset' })
    expect(parseMessage({ origin: PARENT_ORIGIN, data }, ALLOWED)).toEqual({ type: 'field-reset' })
    expect(parseMessage({ origin: SITE_ORIGIN, data }, ALLOWED)).toEqual({ type: 'field-reset' })
  })

  it('returns null for unknown message types without throwing', () => {
    // 'sections-replace' used to be the example here while it lived outside the
    // protocol; it is a formalized parent message now (G.2), so use another.
    expect(parseMessage({ origin: PARENT_ORIGIN, data: { type: `${MSG_PREFIX}draft-sync`, sections: [] } }, ALLOWED)).toBeNull()
    expect(parseMessage({ origin: PARENT_ORIGIN, data: { type: `${MSG_PREFIX}totally-unknown` } }, ALLOWED)).toBeNull()
    expect(parseMessage({ origin: PARENT_ORIGIN, data: { type: 'other-prefix:ready' } }, ALLOWED)).toBeNull()
  })

  it('returns null for malformed payloads without throwing', () => {
    const junk: unknown[] = [
      null,
      undefined,
      'string',
      42,
      [],
      { type: 5 },
      {},
      // missing required fields per type:
      { type: `${MSG_PREFIX}field-highlight`, sectionId: 'section-1', field: 'title' }, // no kind
      { type: `${MSG_PREFIX}section-update`, sectionId: 'section-1' }, // no field
      { type: `${MSG_PREFIX}section-scroll` }, // no sectionId
      { type: `${MSG_PREFIX}section-click`, sectionId: '' },
      { type: `${MSG_PREFIX}section-action`, sectionId: 'section-1', action: 'explode' },
      { type: `${MSG_PREFIX}field-change`, field: 'title', value: 'x' }, // no sectionId
      { type: `${MSG_PREFIX}media-request` }, // no sectionId
      { type: `${MSG_PREFIX}open-processwire` }, // no sectionId
      { type: `${MSG_PREFIX}ready`, path: 42 }, // path not a string
    ]
    for (const data of junk) {
      expect(() => parseMessage({ origin: PARENT_ORIGIN, data }, ALLOWED)).not.toThrow()
      expect(parseMessage({ origin: PARENT_ORIGIN, data }, ALLOWED)).toBeNull()
    }
  })

  it('normalizes loosely typed save-state payloads', () => {
    const parsed = parseMessage({
      origin: PARENT_ORIGIN,
      data: {
        type: `${MSG_PREFIX}save-state`,
        mode: 'weird',
        dirty: 1,
        saving: undefined,
        busy: 'yes',
        busyLabel: 42,
        selectedSectionId: 17,
        presetTagsByComponent: 'nope',
      },
    }, ALLOWED)

    expect(parsed).toEqual({
      type: 'save-state',
      mode: 'edit',
      dirty: true,
      saving: false,
      busy: true,
      busyLabel: '',
      message: '',
      selectedSectionId: null,
      presetTagsByComponent: {},
    })
  })

  it('drops non-string entries when sanitizing ready sectionIds and preset tags', () => {
    const ready = parseMessage({
      origin: PARENT_ORIGIN,
      data: { type: `${MSG_PREFIX}ready`, path: '/wir', sectionIds: ['a', 2, null, 'b'] },
    }, ALLOWED)
    expect(ready).toEqual({ type: 'ready', path: '/wir', sectionIds: ['a', 'b'] })

    // G.3: sectionIds is dead (the shell only reads `path`); when the sender
    // omits it, the parser must not fabricate an empty array.
    const bare = parseMessage({
      origin: PARENT_ORIGIN,
      data: { type: `${MSG_PREFIX}ready`, path: '/wir' },
    }, ALLOWED)
    expect(bare).toEqual({ type: 'ready', path: '/wir' })
    expect(bare).not.toHaveProperty('sectionIds')

    const saveState = parseMessage({
      origin: PARENT_ORIGIN,
      data: {
        type: `${MSG_PREFIX}save-state`,
        mode: 'edit',
        presetTagsByComponent: { pricing_table: ['Abos', 7], broken: 'not-a-list' },
      },
    }, ALLOWED)
    expect(saveState).toMatchObject({ presetTagsByComponent: { pricing_table: ['Abos'] } })
  })

  it('validates sections-replace entries structurally (records with a non-empty string id)', () => {
    // G.1 latent bug (2): sections-replace used to be applied with only
    // Array.isArray validation. The shell still sends it (app.ts
    // refreshDraftUi), so the parser must reject malformed entries.
    const parse = (sections: unknown) =>
      parseMessage({ origin: PARENT_ORIGIN, data: { type: `${MSG_PREFIX}sections-replace`, sections } }, ALLOWED)

    expect(parse([])).toEqual({ type: 'sections-replace', sections: [] })
    expect(parse([{ id: 'section-1' }, { id: 'draft:abc', title: 'Neu', buttons: [] }])).toEqual({
      type: 'sections-replace',
      sections: [{ id: 'section-1' }, { id: 'draft:abc', title: 'Neu', buttons: [] }],
    })

    expect(parse(undefined)).toBeNull()
    expect(parse('nope')).toBeNull()
    expect(parse(['nope'])).toBeNull()
    expect(parse([null])).toBeNull()
    expect(parse([42])).toBeNull()
    expect(parse([[]])).toBeNull()
    expect(parse([{}])).toBeNull()
    expect(parse([{ id: '' }])).toBeNull()
    expect(parse([{ id: 7 }])).toBeNull()
    expect(parse([{ id: 'ok' }, { title: 'missing id' }])).toBeNull()
  })

  it('defaults field descriptor inline to true unless explicitly false', () => {
    const parsed = parseMessage({
      origin: PARENT_ORIGIN,
      data: { type: `${MSG_PREFIX}field-highlight`, sectionId: 's1', field: 'title', kind: 'text' },
    }, ALLOWED)
    expect(parsed).toEqual({ type: 'field-highlight', sectionId: 's1', field: 'title', kind: 'text', inline: true })

    const explicit = parseMessage({
      origin: PARENT_ORIGIN,
      data: { type: `${MSG_PREFIX}field-select`, sectionId: 's1', field: 'media', kind: 'media', inline: false },
    }, ALLOWED)
    expect(explicit).toEqual({ type: 'field-select', sectionId: 's1', field: 'media', kind: 'media', inline: false })
  })

  it('exports a complete, disjoint message-type catalog covered by these fixtures', () => {
    // Union-to-catalog completeness (editability-audit pattern): the fixture
    // arrays above must exercise every cataloged type, and only cataloged types.
    expect(new Set(parentMessages.map((m) => m.type))).toEqual(new Set(PARENT_MESSAGE_TYPES))
    expect(new Set(iframeMessages.map((m) => m.type))).toEqual(new Set(IFRAME_MESSAGE_TYPES))

    const overlap = PARENT_MESSAGE_TYPES.filter((t) => (IFRAME_MESSAGE_TYPES as readonly string[]).includes(t))
    expect(overlap, 'directions must not share message types').toEqual([])

    const all = [...PARENT_MESSAGE_TYPES, ...IFRAME_MESSAGE_TYPES]
    expect(new Set(all).size).toBe(all.length)
  })

  it('creates typed messages via the factory that round-trip', () => {
    const click = createMessage('section-click', { sectionId: 'section-9' })
    expect(click).toEqual({ type: 'section-click', sectionId: 'section-9' })
    expect(roundTrip(click)).toEqual(click)

    const highlight = createMessage('section-highlight', { sectionId: null })
    expect(highlight).toEqual({ type: 'section-highlight', sectionId: null })

    const reset = createMessage('field-reset', {})
    expect(reset).toEqual({ type: 'field-reset' })
    expect(roundTrip(reset)).toEqual(reset)
  })

  it('builds the default origin allowlist from the CMS origin plus same-origin', () => {
    expect(CMS_PARENT_ORIGIN).toBe('https://cms.bioco.ch')
    expect(buildAllowedOrigins()).toEqual([CMS_PARENT_ORIGIN])
    expect(buildAllowedOrigins('http://localhost:3000')).toEqual([CMS_PARENT_ORIGIN, 'http://localhost:3000'])
    // dedupe + opaque/empty self origins are dropped
    expect(buildAllowedOrigins(CMS_PARENT_ORIGIN)).toEqual([CMS_PARENT_ORIGIN])
    expect(buildAllowedOrigins('')).toEqual([CMS_PARENT_ORIGIN])
    expect(buildAllowedOrigins('null')).toEqual([CMS_PARENT_ORIGIN])
  })

  it('checks origins against the allowlist defensively', () => {
    expect(isAllowedOrigin(PARENT_ORIGIN, ALLOWED)).toBe(true)
    expect(isAllowedOrigin(SITE_ORIGIN, ALLOWED)).toBe(true)
    expect(isAllowedOrigin('https://evil.example', ALLOWED)).toBe(false)
    expect(isAllowedOrigin('', ALLOWED)).toBe(false)
    expect(isAllowedOrigin('null', ['null'])).toBe(false)
    expect(isAllowedOrigin(undefined, ALLOWED)).toBe(false)
  })

  it('narrows messages by direction with type guards', () => {
    for (const message of parentMessages) {
      expect(isParentMessage(message), `${message.type} should be a parent message`).toBe(true)
      expect(isIframeMessage(message), `${message.type} should not be an iframe message`).toBe(false)
    }
    for (const message of iframeMessages) {
      expect(isIframeMessage(message), `${message.type} should be an iframe message`).toBe(true)
      expect(isParentMessage(message), `${message.type} should not be a parent message`).toBe(false)
    }
  })

  it('guards validate structure, not just the type string', () => {
    expect(isParentMessage({ type: 'field-highlight', sectionId: 's1', field: 'title' })).toBe(false) // no kind
    expect(isParentMessage({ type: 'section-scroll' })).toBe(false) // no sectionId
    expect(isIframeMessage({ type: 'section-action', sectionId: 's1', action: 'explode' })).toBe(false)
    expect(isIframeMessage({ type: 'ready', path: 42 })).toBe(false)
    expect(isIframeMessage({ type: 'section-click', sectionId: '' })).toBe(false)
    expect(isParentMessage({ type: 'sections-replace', sections: [{}] })).toBe(false)
    expect(isParentMessage({ type: 'sections-replace', sections: [{ id: 's1' }] })).toBe(true)
    expect(isParentMessage(null)).toBe(false)
    expect(isIframeMessage('ready')).toBe(false)
  })
})
