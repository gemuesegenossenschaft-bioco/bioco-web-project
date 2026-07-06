import { describe, expect, it } from 'vitest'
import { iframeOriginsFor, parseShellConfig } from '../visual-editor-shell/config'

// G.2 — the PHP bootstrap emits one JSON config object; the shell must
// validate it and derive a strict iframe-origin allowlist from the site URL
// (closing the parent-side origin hole: the old IIFE accepted messages from
// any origin and posted with targetOrigin '*').

const validConfig = {
  siteUrl: 'https://bioco.ch',
  apiRoot: '/api/',
  adminUrl: '/cms/processwire/',
  pageEditUrl: '/cms/processwire/page/edit/',
  visualEditorUrl: '/cms/visual-editor/',
  draftSecret: 'secret-token',
  pages: [
    { id: 1, title: 'Startseite', path: '/', template: 'home' },
    { id: 1042, title: 'Abos', path: '/abos', template: 'content_page' },
  ],
  collections: {
    '/aktuelles': {
      type: 'event',
      root: '/aktuelles',
      label: 'Events',
      listEndpoint: 'content/events',
      addLabel: 'Neuen Event erstellen',
    },
  },
  componentRegistry: [
    { key: 'pricing_table', label: 'Abo-Tabelle', cmsFields: ['section_config'] },
  ],
  focusFields: {
    heroBaseFields: ['hero_headline'],
    sectionBaseFields: ['section_title'],
    fieldMappings: { title: ['section_title'] },
    heroFieldMappings: { title: ['hero_headline'] },
    buttonFieldMappings: { '0': ['button_text'] },
  },
}

describe('iframeOriginsFor', () => {
  it('allows the site origin plus its www twin', () => {
    expect(iframeOriginsFor('https://bioco.ch')).toEqual(['https://bioco.ch', 'https://www.bioco.ch'])
  })

  it('allows the non-www twin when the site URL uses www', () => {
    expect(iframeOriginsFor('https://www.bioco.ch')).toEqual(['https://www.bioco.ch', 'https://bioco.ch'])
  })

  it('keeps localhost-style origins as-is', () => {
    expect(iframeOriginsFor('http://localhost:3000')).toEqual(['http://localhost:3000'])
  })

  it('ignores paths and trailing slashes', () => {
    expect(iframeOriginsFor('https://bioco.ch/some/path/')).toEqual(['https://bioco.ch', 'https://www.bioco.ch'])
  })
})

describe('parseShellConfig', () => {
  it('accepts a full config and derives iframeOrigins', () => {
    const config = parseShellConfig(validConfig)
    expect(config.siteUrl).toBe('https://bioco.ch')
    expect(config.apiRoot).toBe('/api/')
    expect(config.pageEditUrl).toBe('/cms/processwire/page/edit/')
    expect(config.pages).toHaveLength(2)
    expect(config.collections['/aktuelles']?.addLabel).toBe('Neuen Event erstellen')
    expect(config.iframeOrigins).toEqual(['https://bioco.ch', 'https://www.bioco.ch'])
  })

  it('strips the trailing slash from siteUrl', () => {
    const config = parseShellConfig({ ...validConfig, siteUrl: 'https://bioco.ch/' })
    expect(config.siteUrl).toBe('https://bioco.ch')
  })

  it('throws on a missing or invalid siteUrl/apiRoot/pageEditUrl', () => {
    expect(() => parseShellConfig(null)).toThrow()
    expect(() => parseShellConfig({})).toThrow()
    expect(() => parseShellConfig({ ...validConfig, siteUrl: '' })).toThrow()
    expect(() => parseShellConfig({ ...validConfig, siteUrl: 'not-a-url' })).toThrow()
    expect(() => parseShellConfig({ ...validConfig, apiRoot: '' })).toThrow()
    expect(() => parseShellConfig({ ...validConfig, pageEditUrl: '' })).toThrow()
  })

  it('defaults optional collections/registry/pages/focus config', () => {
    const config = parseShellConfig({
      siteUrl: 'https://bioco.ch',
      apiRoot: '/api/',
      adminUrl: '/cms/processwire/',
      pageEditUrl: '/cms/processwire/page/edit/',
      visualEditorUrl: '/cms/visual-editor/',
    })
    expect(config.draftSecret).toBe('')
    expect(config.pages).toEqual([])
    expect(config.collections).toEqual({})
    expect(config.componentRegistry).toEqual([])
    expect(config.focusFields.sectionBaseFields).toEqual([])
    expect(config.focusFields.fieldMappings).toEqual({})
  })

  it('drops malformed page descriptors and collection entries', () => {
    const config = parseShellConfig({
      ...validConfig,
      pages: [
        { id: 1, title: 'Startseite', path: '/', template: 'home' },
        { id: 0, title: 'kaputt', path: '/x', template: 't' },
        { title: 'no id', path: '/y' },
        'junk',
      ],
      collections: {
        '/aktuelles': validConfig.collections['/aktuelles'],
        '/broken': { label: 'Ohne Endpoint' },
      },
    })
    expect(config.pages).toEqual([{ id: 1, title: 'Startseite', path: '/', template: 'home' }])
    expect(Object.keys(config.collections)).toEqual(['/aktuelles'])
  })

  it('merges explicit extra allowedOrigins into iframeOrigins', () => {
    const config = parseShellConfig({ ...validConfig, allowedOrigins: ['http://127.0.0.1:49154', 'null', ''] })
    expect(config.iframeOrigins).toEqual([
      'https://bioco.ch',
      'https://www.bioco.ch',
      'http://127.0.0.1:49154',
    ])
  })
})
