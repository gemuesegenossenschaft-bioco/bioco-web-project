import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import fs from 'fs'
import path from 'path'
import { bootVisualEditorShell, type ShellHandle } from '../visual-editor-shell/app'
import { MSG_PREFIX } from '../lib/visual-editor/protocol'
import { STRINGS } from '../visual-editor-shell/strings'

// G.2 smoke test — mounts the REAL skeleton from site/templates/visual-editor.php
// (so PHP markup and shell app cannot drift apart), boots the app and drives it
// through protocol messages: ready handshake, origin allowlist, field-change
// dirtying, publish with revalidated:false pill, collection panel.

const SITE_ORIGIN = 'http://localhost:3000'

const testConfig = {
  siteUrl: SITE_ORIGIN,
  apiRoot: '/api/',
  adminUrl: '/cms/processwire/',
  pageEditUrl: '/cms/processwire/page/edit/',
  visualEditorUrl: '/cms/visual-editor/',
  draftSecret: 'draft-secret',
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
  componentRegistry: [],
  focusFields: {
    heroBaseFields: ['hero_headline'],
    sectionBaseFields: ['section_title'],
    fieldMappings: {},
    heroFieldMappings: {},
    buttonFieldMappings: {},
  },
}

function loadSkeleton(): string {
  const phpPath = path.resolve(__dirname, '../../site/templates/visual-editor.php')
  const source = fs.readFileSync(phpPath, 'utf8')
  const body = source.slice(source.indexOf('<body>') + '<body>'.length, source.indexOf('</body>'))
  return body
    .replace(/<script[\s\S]*?<\/script>/g, '') // config blob + app tag re-added by the test
    .replace(/<\?(?:=|php)?[\s\S]*?\?>/g, '') // strip PHP echoes
}

function jsonResponse(body: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(body),
  } as Response
}

function memoryStorage() {
  const map = new Map<string, string>()
  return {
    getItem: (k: string) => (map.has(k) ? map.get(k)! : null),
    setItem: (k: string, v: string) => void map.set(k, v),
    removeItem: (k: string) => void map.delete(k),
  }
}

type Routes = Record<string, (init?: RequestInit) => unknown | Promise<unknown>>

function buildFetchMock(routes: Routes) {
  return vi.fn((url: string, init?: RequestInit) => {
    const key = String(url).replace(/\?.*$/, '')
    const route = routes[key]
    if (!route) return Promise.resolve(jsonResponse({ success: false, error: `unmocked ${key}` }, 404))
    return Promise.resolve(route(init)).then((body) => jsonResponse(body))
  }) as unknown as typeof fetch
}

const homepagePayload = {
  sections: [
    { id: 's1', pwId: 11, title: 'Erster Abschnitt', text: '<p>Hallo</p>', layout: 'rich_text', theme: 'default' },
  ],
  hero: { headline: 'Hallo Hero', subtitle: 'Sub' },
  fingerprint: 'fp-1',
}

function emit(origin: string, data: Record<string, unknown>) {
  window.dispatchEvent(new MessageEvent('message', { origin, data }))
}

function ready(pathName: string, sectionIds: string[] = []) {
  emit(SITE_ORIGIN, { type: `${MSG_PREFIX}ready`, path: pathName, sectionIds })
}

describe('visual editor shell smoke (real skeleton + protocol messages)', () => {
  let handle: ShellHandle | null = null
  let postMessage: ReturnType<typeof vi.fn>
  let iframeSrc: string

  function boot(routes: Routes = {}) {
    document.head.innerHTML = ''
    document.body.innerHTML = loadSkeleton()

    const blob = document.createElement('script')
    blob.type = 'application/json'
    blob.id = 've-config'
    blob.textContent = JSON.stringify(testConfig)
    document.body.appendChild(blob)

    const iframe = document.getElementById('ve-iframe') as HTMLIFrameElement
    iframeSrc = 'about:blank'
    Object.defineProperty(iframe, 'src', {
      get: () => iframeSrc,
      set: (value: string) => {
        iframeSrc = value
      },
    })
    postMessage = vi.fn()
    Object.defineProperty(iframe, 'contentWindow', { get: () => ({ postMessage }) })

    const fetchImpl = buildFetchMock({
      '/api/content/presets': () => ({ success: true, presets: [] }),
      '/api/content/homepage': () => homepagePayload,
      ...routes,
    })
    handle = bootVisualEditorShell({ fetchImpl, storage: memoryStorage() })
    return { fetchImpl }
  }

  afterEach(() => {
    handle?.destroy()
    handle = null
  })

  it('renders the PHP skeleton, injects styles and loads the preview iframe', async () => {
    boot()
    expect(document.getElementById('ve-style')?.textContent).toContain('.ve-toolbar')
    expect(document.querySelector('.ve-toolbar-logo')?.textContent).toBe(STRINGS.toolbarLogo)
    expect(iframeSrc).toBe(`${SITE_ORIGIN}/?_visual=1&draft_secret=draft-secret`)
    expect(document.getElementById('ve-status')?.textContent).toBe(STRINGS.statusLoadingPreview)
  })

  it('ignores messages from origins outside the allowlist (origin hole closed)', async () => {
    boot()
    emit('https://evil.example', { type: `${MSG_PREFIX}ready`, path: '/', sectionIds: [] })
    await new Promise((r) => setTimeout(r, 20))
    expect(document.getElementById('ve-status')?.textContent).toBe(STRINGS.statusLoadingPreview)
  })

  it('adopts the page from ready, loads sections and posts save-state with explicit targetOrigin', async () => {
    boot()
    ready('/')
    await vi.waitFor(() => {
      expect(document.getElementById('ve-status')?.textContent).toBe(STRINGS.statusConnected)
    })
    expect(document.getElementById('ve-current-page-title')?.textContent).toBe('Startseite')
    const listText = document.getElementById('ve-section-list')?.textContent || ''
    expect(listText).toContain('Hallo Hero')
    expect(listText).toContain('Erster Abschnitt')

    const saveStateCall = postMessage.mock.calls.find(
      (call) => (call[0] as { type?: string })?.type === `${MSG_PREFIX}save-state`
    )
    expect(saveStateCall).toBeTruthy()
    expect(saveStateCall?.[1]).toBe(SITE_ORIGIN)
  })

  it('marks the draft dirty on field-change and publishes with the red revalidate pill', async () => {
    const publishBody = {
      success: true,
      sections: [{ ...homepagePayload.sections[0], title: 'Neu' }],
      hero: homepagePayload.hero,
      fingerprint: 'fp-2',
      revalidated: false,
      revalidateStatus: 0,
      revalidateError: '',
    }
    boot({ '/api/content-publish': () => publishBody })
    ready('/')
    await vi.waitFor(() => {
      expect(document.getElementById('ve-status')?.textContent).toBe(STRINGS.statusConnected)
    })

    const btnSave = document.getElementById('ve-btn-save') as HTMLButtonElement
    expect(btnSave.disabled).toBe(true)

    emit(SITE_ORIGIN, { type: `${MSG_PREFIX}field-change`, sectionId: 's1', field: 'title', value: 'Neu' })
    expect(btnSave.disabled).toBe(false)
    expect(document.getElementById('ve-section-list')?.textContent).toContain(STRINGS.dirtyPill)

    btnSave.click()
    await vi.waitFor(() => {
      expect(document.getElementById('ve-status')?.textContent).toBe(STRINGS.statusPublishedStaleBuild)
    })
    expect(document.getElementById('ve-status')?.className).toContain('is-error')
    expect(btnSave.disabled).toBe(true)

    const saveResult = postMessage.mock.calls
      .map((call) => call[0] as { type?: string; revalidated?: boolean })
      .find((msg) => msg?.type === `${MSG_PREFIX}save-result`)
    expect(saveResult).toMatchObject({ revalidated: false })
  })

  it('blocks incoming edits while busy (save-state busy contract)', async () => {
    let resolvePublish: ((body: unknown) => void) | null = null
    boot({
      '/api/content-publish': () => new Promise((resolve) => (resolvePublish = resolve)),
    })
    ready('/')
    await vi.waitFor(() => {
      expect(document.getElementById('ve-status')?.textContent).toBe(STRINGS.statusConnected)
    })

    emit(SITE_ORIGIN, { type: `${MSG_PREFIX}field-change`, sectionId: 's1', field: 'title', value: 'Neu' })
    const btnSave = document.getElementById('ve-btn-save') as HTMLButtonElement
    btnSave.click()
    expect(btnSave.textContent).toBe(STRINGS.btnPublishing)

    // while publishing, further edits are ignored
    emit(SITE_ORIGIN, { type: `${MSG_PREFIX}field-change`, sectionId: 's1', field: 'title', value: 'Ignoriert' })
    expect(document.getElementById('ve-section-list')?.textContent).not.toContain('Ignoriert')

    // the fetch happens on a microtask after click(); wait for its resolver
    await vi.waitFor(() => {
      expect(resolvePublish).toBeTruthy()
    })
    resolvePublish!({
      success: true,
      sections: homepagePayload.sections,
      hero: homepagePayload.hero,
      fingerprint: 'fp-2',
      revalidated: true,
    })
    await vi.waitFor(() => {
      expect(document.getElementById('ve-status')?.textContent).toBe(STRINGS.statusPublishedLive)
    })
  })

  it('shows the collection panel for /aktuelles with entries and PW deep links', async () => {
    boot({
      '/api/content/events': () => ({
        upcoming: [{ id: 77, title: 'Sommerfest', dateLabel: '12. Juli' }],
        past: [],
      }),
    })
    ready('/aktuelles')
    await vi.waitFor(() => {
      expect(document.getElementById('ve-current-page-title')?.textContent).toBe('Events')
    })
    expect(document.getElementById('ve-current-page-path')?.textContent).toBe(
      '/aktuelles' + STRINGS.collectionSuffix
    )
    await vi.waitFor(() => {
      expect(document.getElementById('ve-col-list')?.textContent).toContain('Sommerfest')
    })
    expect(document.getElementById('ve-col-add')?.textContent).toBe('Neuen Event erstellen')
    expect(document.getElementById('ve-field-editor')?.textContent).toContain(STRINGS.openInPw)
    expect((document.getElementById('ve-col-date') as HTMLInputElement).type).toBe('date')
  })
})
