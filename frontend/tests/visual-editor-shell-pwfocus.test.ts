import { describe, expect, it } from 'vitest'
import { buildProcessWireFocusUrl, getProcessWireFocusFields } from '../visual-editor-shell/pwFocus'
import type { ShellFocusFieldsConfig } from '../visual-editor-shell/config'

// G.2 — the "→ In PW öffnen" deep links: focus-field resolution from the
// shared visual-editor-focus-fields.json map + PW page-edit URL building.

const focusFields: ShellFocusFieldsConfig = {
  heroBaseFields: ['hero_headline', 'hero_subtitle', 'hero_image'],
  sectionBaseFields: ['section_title', 'section_text'],
  fieldMappings: {
    title: ['section_title'],
    text: ['section_text'],
  },
  heroFieldMappings: {
    title: ['hero_headline'],
    media: ['hero_image', 'image_alt'],
  },
  buttonFieldMappings: {
    '0': ['button_text', 'button_href', 'button_variant'],
    '1': ['button2_text', 'button2_href', 'button2_variant'],
  },
}

const resolveComponent = (key?: string | null) =>
  key === 'pricing_table'
    ? { key: 'pricing_table', label: 'Abo-Tabelle', cmsFields: ['section_component', 'section_config'] }
    : null

const heroSection = { id: '__hero__', title: 'Hero', layout: 'hero', theme: 'default', text: '' }
const plainSection = { id: 's1', pwId: 123, title: 'T', layout: 'rich_text', theme: 'default', text: '' }
const componentSection = { ...plainSection, component: 'pricing_table' }

describe('getProcessWireFocusFields', () => {
  it('uses hero mappings for hero sections', () => {
    expect(getProcessWireFocusFields(heroSection, {}, focusFields, resolveComponent))
      .toEqual(['hero_headline', 'hero_subtitle', 'hero_image'])
    expect(getProcessWireFocusFields(heroSection, { field: 'title' }, focusFields, resolveComponent))
      .toEqual(['hero_headline'])
    expect(getProcessWireFocusFields(heroSection, { field: 'media' }, focusFields, resolveComponent))
      .toEqual(['hero_image', 'image_alt'])
    // unmapped hero field falls back to hero base fields
    expect(getProcessWireFocusFields(heroSection, { field: 'unknown' }, focusFields, resolveComponent))
      .toEqual(['hero_headline', 'hero_subtitle', 'hero_image'])
  })

  it('prefers component cmsFields when no specific field is requested', () => {
    expect(getProcessWireFocusFields(componentSection, {}, focusFields, resolveComponent))
      .toEqual(['section_component', 'section_config'])
    expect(getProcessWireFocusFields(plainSection, {}, focusFields, resolveComponent))
      .toEqual(['section_title', 'section_text'])
  })

  it('maps buttons by index and media by targetField', () => {
    expect(getProcessWireFocusFields(plainSection, { field: 'button', buttonIndex: 1 }, focusFields, resolveComponent))
      .toEqual(['button2_text', 'button2_href', 'button2_variant'])
    expect(getProcessWireFocusFields(plainSection, { field: 'button' }, focusFields, resolveComponent))
      .toEqual(['button_text', 'button_href', 'button_variant'])
    expect(getProcessWireFocusFields(plainSection, { field: 'media' }, focusFields, resolveComponent))
      .toEqual(['section_image', 'image_alt'])
    expect(
      getProcessWireFocusFields(plainSection, { field: 'media', targetField: 'section_images' }, focusFields, resolveComponent)
    ).toEqual(['section_images', 'image_alt'])
  })
})

describe('buildProcessWireFocusUrl', () => {
  const base = {
    pageEditUrl: '/cms/processwire/page/edit/',
    visualEditorUrl: '/cms/visual-editor/',
    pageId: 1042,
    path: '/abos',
    focusFields,
    resolveComponent,
  }

  it('builds a focused PW edit URL with return link and section context', () => {
    const result = buildProcessWireFocusUrl({ ...base, section: plainSection, request: { field: 'title' } })
    expect('url' in result && result.url).toBeTruthy()
    const url = (result as { url: string }).url
    expect(url.startsWith('/cms/processwire/page/edit/?id=1042')).toBe(true)
    expect(url).toContain('veFocus=1')
    expect(url).toContain('vePageId=1042')
    expect(url).toContain('vePath=%2Fabos')
    expect(url).toContain('veSectionId=s1')
    expect(url).toContain('veSectionPwId=123')
    expect(url).toContain('veFields=section_title')
    expect(url).toContain('veField=title')
    expect(url).toContain(`veReturn=${encodeURIComponent('/cms/visual-editor/?pageId=1042&path=%2Fabos')}`)
  })

  it('marks component sections and forwards button/target context', () => {
    const result = buildProcessWireFocusUrl({
      ...base,
      section: componentSection,
      request: { field: 'button', buttonIndex: 1, targetField: 'section_image', kind: 'button' },
    })
    const url = (result as { url: string }).url
    expect(url).toContain('veComponent=pricing_table')
    expect(url).toContain('veButtonIndex=1')
    expect(url).toContain('veTargetField=section_image')
    expect(url).toContain('veKind=button')
  })

  it('reports the error cases: no target, unpublished draft section, no fields', () => {
    expect(buildProcessWireFocusUrl({ ...base, pageId: 0, section: plainSection, request: {} }))
      .toEqual({ error: 'missing_target' })
    expect(buildProcessWireFocusUrl({ ...base, section: null, request: {} }))
      .toEqual({ error: 'missing_target' })
    expect(buildProcessWireFocusUrl({ ...base, section: { ...plainSection, pwId: undefined }, request: {} }))
      .toEqual({ error: 'publish_first' })
    const emptyConfig: ShellFocusFieldsConfig = {
      heroBaseFields: [], sectionBaseFields: [], fieldMappings: {}, heroFieldMappings: {}, buttonFieldMappings: {},
    }
    expect(buildProcessWireFocusUrl({ ...base, focusFields: emptyConfig, section: plainSection, request: {} }))
      .toEqual({ error: 'missing_fields' })
  })
})
