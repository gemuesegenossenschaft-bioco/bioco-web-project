/**
 * Visual-editor shell controller. The PHP bootstrap
 * (site/templates/visual-editor.php) renders the static skeleton + one JSON
 * config blob; this module wires the tested parts together:
 *
 *   config.ts   — config parsing + iframe origin allowlist
 *   api.ts      — typed admin API layer
 *   store.ts    — status flow (wraps lib/visual-editor/shellState.ts)
 *   bridge.ts   — ALL iframe postMessage I/O (protocol.ts, origin-checked)
 *   draft.ts    — draft model on lib/visualEditorContract.ts + sessionStorage
 *   conflict.ts — publish 409 three-way merge
 *   pwFocus.ts  — "→ In PW öffnen" deep links
 *   configEditor.ts — sidebar component-config editor
 *   strings.ts  — every German UI string
 *
 * Navigation model: there is NO page picker. The user navigates
 * the real site inside the iframe; every `ready` message carries the current
 * pathname and the shell adopts that page (or its collection panel).
 */

import { readShellConfig, type ShellCollectionConfig, type ShellComponentRegistryEntry, type ShellConfig } from './config'
import { ApiError, combineCollectionEntries, createShellApi, publishPill, type ShellApi } from './api'
import { createShellStore, type ShellStore } from './store'
import { createIframeBridge, type IframeBridge } from './bridge'
import {
  appendDraftMedia,
  applyDraftMedia,
  applyShellFieldChange,
  buildHomepageHeroSection,
  clearDraft,
  cloneShellSections,
  computeDirtyIds,
  hasDraftChanges,
  isHeroSection,
  normalizeShellSection,
  persistDraft,
  restoreDraft,
  sectionUpdateNotifications,
  type DraftMediaFile,
  type DraftStorage,
  type ShellFieldChange,
  type ShellSection,
} from './draft'
import { resolvePublishConflict } from './conflict'
import { buildProcessWireFocusUrl, type PwFocusRequest } from './pwFocus'
import { renderComponentConfigEditor, sanitizeConfigSchema } from './configEditor'
import { STRINGS } from './strings'
import { SHELL_CSS } from './styles'
import type { IframeToParentMessage, VeFieldDescriptor } from '../lib/visual-editor/protocol'

const BUSY_DELAY = 320
const IFRAME_READY_TIMEOUT = 10000
const DRAFT_AUTOSAVE_DELAY = 180
const HISTORY_LIMIT = 120

// Theme/bg/overlay/button-variant option catalogs from the old shell are not
// redeclared here: their inputs live in the iframe's inline overlays
// (InlineVisualEditorRuntime), not in the sidebar.

const CORE_LAYOUTS = ['rich_text', 'split_media_text', 'split_text_media', 'full_width_banner', 'media_grid', 'video_embed']
const CORE_ICONS: Record<string, string> = {
  rich_text: '¶', split_media_text: '◧', split_text_media: '◨',
  full_width_banner: '▬', media_grid: '⊞', video_embed: '▶',
}
const COMP_ICONS: Record<string, string> = {
  page_intro: '§', media_text: '◫', cards_grid: '▦',
  gallery_strip: '≡', text_columns: '☰', cta_band: '▸',
  timeline_header: '◉', timeline_item: '◉',
  contact_form: '✉', membership_form: '✉', subscribe_form: '✉',
  visit_day_form: '✉', waiting_list_form: '✉',
  pricing_calculator: '⊕', events_feed: '◆', schnuppertage: '❀',
  saisonkalender: '❀', gallery: '▦',
  depot_map: '◎', geisshof_map: '◎',
}

interface AddCatalogEntry {
  id: string
  category: string
  label: string
  description: string
  icon: string
  payload: Record<string, unknown>
}

interface PresetItem {
  name?: string
  description?: string
  category?: string
  payload?: Record<string, unknown> & { component?: string }
}

export interface BootOptions {
  win?: Window
  doc?: Document
  fetchImpl?: typeof fetch
  storage?: DraftStorage
}

export interface ShellHandle {
  destroy(): void
}

function escapeHtml(value: unknown): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function normalizePagePath(path: unknown, origin?: string): string {
  let next = String(path || '').trim()
  if (!next) return ''
  if (/^https?:\/\//i.test(next)) {
    try {
      next = new URL(next, origin).pathname || ''
    } catch {
      /* keep as-is */
    }
  }
  next = next.replace(/[?#].*$/, '')
  if (!next) return ''
  if (next === '/') return '/'
  return '/' + next.replace(/^\/+|\/+$/g, '')
}

function createDraftId(): string {
  return 'draft:' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8)
}

function cloneJson<T>(value: T, fallback: T): T {
  if (value == null) return fallback
  try {
    return JSON.parse(JSON.stringify(value)) as T
  } catch {
    return fallback
  }
}

export function bootVisualEditorShell(options: BootOptions = {}): ShellHandle {
  const win = options.win || window
  const doc = options.doc || win.document
  const fetchImpl = options.fetchImpl || win.fetch.bind(win)
  const storage: DraftStorage = options.storage || win.sessionStorage

  /* ---- style injection (keeps the PHP bootstrap small) ---- */
  if (!doc.getElementById('ve-style')) {
    const style = doc.createElement('style')
    style.id = 've-style'
    style.textContent = SHELL_CSS
    doc.head.appendChild(style)
  }

  const config: ShellConfig = readShellConfig(doc)
  const api: ShellApi = createShellApi(config, fetchImpl)
  const store: ShellStore = createShellStore()

  /* ---- DOM ---- */
  const $ = <T extends HTMLElement = HTMLElement>(id: string): T => {
    const el = doc.getElementById(id)
    if (!el) throw new Error(`visual-editor shell: #${id} missing in skeleton`)
    return el as T
  }
  const iframe = $<HTMLIFrameElement>('ve-iframe')
  const statusEl = $('ve-status')
  const sectionList = $('ve-section-list')
  const emptyList = $('ve-empty-list')
  const currentPageTitleEl = $('ve-current-page-title')
  const currentPagePathEl = $('ve-current-page-path')
  const fieldEditor = $('ve-field-editor')
  const btnAdd = $<HTMLButtonElement>('ve-btn-add')
  const btnRefresh = $<HTMLButtonElement>('ve-btn-refresh')
  const btnPresets = $<HTMLButtonElement>('ve-btn-presets')
  const btnPw = $<HTMLButtonElement>('ve-btn-pw')
  const btnSave = $<HTMLButtonElement>('ve-btn-save')
  const btnReset = $<HTMLButtonElement>('ve-btn-reset')
  const btnModeEdit = $<HTMLButtonElement>('ve-mode-edit')
  const btnModeBrowse = $<HTMLButtonElement>('ve-mode-browse')
  const mediaModal = $('ve-media-modal')
  const mediaClose = $<HTMLButtonElement>('ve-media-close')
  const mediaEmpty = $('ve-media-empty')
  const mediaGrid = $('ve-media-grid')
  const presetModal = $('ve-preset-modal')
  const presetClose = $<HTMLButtonElement>('ve-preset-close')
  const presetSearch = $<HTMLInputElement>('ve-preset-search')
  const presetCategory = $<HTMLSelectElement>('ve-preset-category')
  const presetEmpty = $('ve-preset-empty')
  const presetList = $('ve-preset-list')
  const addModal = $('ve-add-modal')
  const addClose = $<HTMLButtonElement>('ve-add-close')
  const addSearch = $<HTMLInputElement>('ve-add-search')
  const addFilter = $<HTMLSelectElement>('ve-add-filter')
  const addScroll = $('ve-add-scroll')
  const busyOverlay = $('ve-busy-overlay')
  const busyLabel = $('ve-busy-label')

  /* ---- state ---- */
  let currentPageId: number | null = null
  let currentPath: string | null = null
  let currentCollection: ShellCollectionConfig | null = null
  let sections: ShellSection[] = []
  let canonicalSections: ShellSection[] = []
  let canonicalFingerprint = ''
  let activeSectionId: string | null = null
  let activeField: VeFieldDescriptor | null = null
  let iframeReady = false
  let dirtySectionIds: Record<string, true> = {}
  let editorMode: 'edit' | 'browse' = 'edit'
  let mediaFiles: Array<Record<string, unknown>> = []
  let mediaRequest: { sectionId: string; targetField: string } | null = null
  let presetItems: PresetItem[] = []
  let presetTagsByComponent: Record<string, string[]> = {}
  let busyDepth = 0
  let busyVisible = false
  let busyTimer: ReturnType<typeof setTimeout> | null = null
  let busyText = ''
  let waitingForIframeReady = false
  let iframeReadyTimer: ReturnType<typeof setTimeout> | null = null
  let draftAutosaveTimer: ReturnType<typeof setTimeout> | null = null
  let statusTimer: ReturnType<typeof setTimeout> | null = null
  let historyPast: ShellSection[][] = []
  let historyFuture: ShellSection[][] = []
  let applyingHistory = false

  const isSaving = () => store.getState().status === 'saving'
  const isBusy = () => busyDepth > 0

  /* ---- bridge (the only iframe channel) ---- */
  const bridge: IframeBridge = createIframeBridge({
    listenWindow: win,
    origins: config.iframeOrigins,
    defaultTargetOrigin: new URL(config.siteUrl).origin,
    getTargetWindow: () => iframe.contentWindow,
    onMessage: handleIframeMessage,
  })

  /* ---- status helpers ---- */
  function setStatus(text: string, cls?: string) {
    statusEl.textContent = text
    statusEl.className = 've-status' + (cls ? ' ' + cls : '')
  }

  function clearStatusLater() {
    if (statusTimer) clearTimeout(statusTimer)
    statusTimer = setTimeout(() => {
      if (!isSaving() && !isBusy()) {
        if (draftChanged()) {
          setStatus(STRINGS.statusDraftSaved, 'is-loading')
        } else {
          setStatus(STRINGS.statusConnected, 'is-ready')
        }
      }
    }, 2600)
  }

  function setTransientStatus(text: string, cls?: string) {
    setStatus(text, cls)
    clearStatusLater()
  }

  /* ---- page context ---- */
  function getPageDescriptorByPath(path: string | null) {
    const normalized = normalizePagePath(path, config.siteUrl)
    if (!normalized) return null
    return config.pages.find((page) => normalizePagePath(page.path, config.siteUrl) === normalized) || null
  }

  function renderCurrentPageContext() {
    if (currentCollection) {
      currentPageTitleEl.textContent = currentCollection.label
      currentPagePathEl.textContent = currentCollection.root + STRINGS.collectionSuffix
      return
    }
    const page = currentPageId ? config.pages.find((p) => p.id === currentPageId) : null
    if (page) {
      currentPageTitleEl.textContent = page.title || STRINGS.pageEditableFallback
      currentPagePathEl.textContent = page.path || '/'
      return
    }
    if (currentPath) {
      currentPageTitleEl.textContent = STRINGS.pageNotEditable
      currentPagePathEl.textContent = currentPath
      return
    }
    currentPageTitleEl.textContent = STRINGS.pageEditableFallback
    currentPagePathEl.textContent = STRINGS.sidebarPathPlaceholder
  }

  function resetPageState() {
    iframeReady = false
    sections = []
    canonicalSections = []
    canonicalFingerprint = ''
    activeSectionId = null
    activeField = null
    dirtySectionIds = {}
    historyPast = []
    historyFuture = []
  }

  function getCollectionForPath(path: string): ShellCollectionConfig | null {
    const norm = normalizePagePath(path, config.siteUrl)
    if (!norm) return null
    for (const root of Object.keys(config.collections)) {
      if (norm === root || norm.indexOf(root + '/') === 0) return config.collections[root]
    }
    return null
  }

  /* ---- draft helpers ---- */
  function draftChanged() {
    return hasDraftChanges(sections, canonicalSections)
  }

  function getSectionById(sectionId: string | null): ShellSection | null {
    if (!sectionId) return null
    return sections.find((section) => section.id === sectionId) || null
  }

  function getActiveSection() {
    return getSectionById(activeSectionId)
  }

  function getSortableSections() {
    return sections.filter((section) => !isHeroSection(section))
  }

  function persistCurrentDraftNow() {
    if (!currentPageId || !currentPath) return
    if (draftAutosaveTimer) {
      clearTimeout(draftAutosaveTimer)
      draftAutosaveTimer = null
    }
    if (!draftChanged()) {
      clearDraft(storage, currentPageId, currentPath)
      return
    }
    persistDraft(storage, {
      pageId: currentPageId,
      path: currentPath,
      baseFingerprint: canonicalFingerprint,
      sections,
      activeSectionId,
      activeField: cloneJson(activeField, null),
    })
  }

  function scheduleDraftAutosave() {
    if (!currentPageId || !currentPath) return
    if (draftAutosaveTimer) clearTimeout(draftAutosaveTimer)
    draftAutosaveTimer = setTimeout(() => {
      persistCurrentDraftNow()
      if (!isSaving() && !isBusy() && draftChanged()) {
        setStatus(STRINGS.statusDraftSaved, 'is-loading')
        clearStatusLater()
      }
    }, DRAFT_AUTOSAVE_DELAY)
  }

  /* ---- history (undo/redo) ---- */
  function pushHistorySnapshot() {
    if (applyingHistory) return
    historyPast.push(cloneShellSections(sections))
    if (historyPast.length > HISTORY_LIMIT) historyPast.shift()
    historyFuture = []
  }

  function undoChange() {
    if (!historyPast.length || isBusy()) return
    applyingHistory = true
    historyFuture.push(cloneShellSections(sections))
    sections = cloneShellSections(historyPast.pop()!)
    refreshDraftUi({ message: STRINGS.statusUndo })
    applyingHistory = false
  }

  function redoChange() {
    if (!historyFuture.length || isBusy()) return
    applyingHistory = true
    historyPast.push(cloneShellSections(sections))
    sections = cloneShellSections(historyFuture.pop()!)
    refreshDraftUi({ message: STRINGS.statusRedo })
    applyingHistory = false
  }

  /* ---- busy blocking ---- */
  function renderBusyOverlay() {
    busyOverlay.classList.toggle('is-visible', busyVisible)
    busyOverlay.setAttribute('aria-hidden', busyVisible ? 'false' : 'true')
    busyLabel.textContent = busyText || STRINGS.busyDefault
  }

  function beginBusy(label?: string) {
    busyDepth += 1
    busyText = label || busyText || STRINGS.busyDefault
    if (busyDepth === 1) store.dispatch({ type: 'busy-start', busyLabel: busyText })
    updateActions()
    if (busyVisible) {
      renderBusyOverlay()
      syncIframeState()
      return
    }
    if (busyTimer) clearTimeout(busyTimer)
    busyTimer = setTimeout(() => {
      busyVisible = true
      renderBusyOverlay()
      syncIframeState()
    }, BUSY_DELAY)
    syncIframeState()
  }

  function endBusy() {
    if (busyDepth > 0) busyDepth -= 1
    updateActions()
    if (busyDepth > 0) {
      syncIframeState()
      return
    }
    store.dispatch({ type: 'busy-end' })
    if (busyTimer) {
      clearTimeout(busyTimer)
      busyTimer = null
    }
    busyVisible = false
    busyText = ''
    renderBusyOverlay()
    syncIframeState()
  }

  function runWithBusy<T>(label: string, task: () => Promise<T> | T): Promise<T> {
    beginBusy(label)
    return Promise.resolve()
      .then(task)
      .finally(() => {
        endBusy()
      })
  }

  function clearIframeReadyTimeout() {
    if (iframeReadyTimer) {
      clearTimeout(iframeReadyTimer)
      iframeReadyTimer = null
    }
  }

  function scheduleIframeReadyTimeout() {
    clearIframeReadyTimeout()
    iframeReadyTimer = setTimeout(() => {
      if (!waitingForIframeReady || iframeReady) return
      waitingForIframeReady = false
      busyDepth = 0
      store.dispatch({ type: 'busy-end' })
      busyVisible = false
      busyText = ''
      if (busyTimer) {
        clearTimeout(busyTimer)
        busyTimer = null
      }
      renderBusyOverlay()
      updateActions()
      syncIframeState(STRINGS.statusPreviewFailed)
      setStatus(STRINGS.statusPreviewFailed, 'is-error')
    }, IFRAME_READY_TIMEOUT)
  }

  /* ---- iframe sync ---- */
  function syncIframeState(message?: string) {
    if (!iframeReady) return
    bridge.send('save-state', {
      mode: editorMode,
      dirty: draftChanged(),
      saving: isSaving(),
      busy: isBusy(),
      busyLabel: busyText || '',
      message: message || '',
      selectedSectionId: activeSectionId,
      presetTagsByComponent,
    })
  }

  function notifySectionUpdates(section: ShellSection, field: string) {
    if (!iframeReady) return
    for (const update of sectionUpdateNotifications(field, section)) {
      bridge.send('section-update', { sectionId: section.id, field: update.field, value: update.value })
    }
  }

  /* ---- actions/toolbar state ---- */
  function updateActions() {
    const busy = isBusy()
    const saving = isSaving()
    const dirty = draftChanged()
    btnAdd.disabled = !currentPageId || saving || busy
    btnPw.disabled = !currentPageId || busy
    btnRefresh.disabled = (!currentPageId && !currentPath) || busy
    btnPresets.disabled = busy
    btnSave.disabled = !dirty || saving || busy
    btnReset.disabled = !dirty || saving || busy
    btnModeEdit.disabled = busy
    btnModeBrowse.disabled = busy
    btnSave.textContent = saving ? STRINGS.btnPublishing : STRINGS.btnPublish
    btnModeEdit.classList.toggle('is-active', editorMode === 'edit')
    btnModeBrowse.classList.toggle('is-active', editorMode === 'browse')
  }

  function confirmDiscardChanges() {
    if (!draftChanged()) return true
    return win.confirm(STRINGS.confirmDiscard)
  }

  function blockWhileDirty(actionLabel: string) {
    if (!draftChanged()) return false
    const guarded: Record<string, true> = {
      [STRINGS.actionPageSwitch]: true,
      [STRINGS.actionReload]: true,
    }
    if (!guarded[actionLabel]) return false
    return !win.confirm(STRINGS.confirmDirtyAction(actionLabel))
  }

  /* ---- component registry (config passthrough) ---- */
  function normalizeComponentLookupKey(value: unknown) {
    return String(value || '').trim().toLowerCase()
  }

  function resolveComponentMeta(rawKey?: string | null): ShellComponentRegistryEntry | null {
    const lookup = normalizeComponentLookupKey(rawKey)
    if (!lookup) return null
    for (const entry of config.componentRegistry) {
      if (normalizeComponentLookupKey(entry.key) === lookup) return entry
      for (const alias of entry.aliases || []) {
        if (normalizeComponentLookupKey(alias) === lookup) return entry
      }
    }
    return null
  }

  function formatComponentLabel(rawKey?: string | null) {
    const raw = String(rawKey || '').trim()
    if (!raw) return ''
    const meta = resolveComponentMeta(raw)
    if (!meta) return raw
    return raw === meta.key ? `${meta.label} (${meta.key})` : `${meta.label} (${raw})`
  }

  function formatSectionListTitle(section: ShellSection) {
    const title = String(section.title || '').trim()
    if (title) return title
    if (section.component) {
      const meta = resolveComponentMeta(section.component)
      return meta ? meta.label : section.component
    }
    return STRINGS.layoutLabels[section.layout || ''] || section.layout || STRINGS.untitledSection
  }

  function getProcessWireTypeKey(section: ShellSection) {
    if (isHeroSection(section)) return 'hero'
    if (section.component) {
      const meta = resolveComponentMeta(section.component)
      return meta?.key || String(section.component)
    }
    return String(section.layout || '')
  }

  /* ---- ProcessWire focus deep links ---- */
  function openProcessWireFocus(request: PwFocusRequest & { sectionId?: string }) {
    if (isBusy() || !currentPageId) return
    if (draftChanged()) {
      win.alert(STRINGS.alertPwFocusNeedsCleanDraft)
      return
    }
    const section = request.sectionId ? getSectionById(request.sectionId) : getActiveSection()
    const result = buildProcessWireFocusUrl({
      pageEditUrl: config.pageEditUrl,
      visualEditorUrl: config.visualEditorUrl,
      pageId: currentPageId,
      path: currentPath || '',
      section,
      request,
      focusFields: config.focusFields,
      resolveComponent: (key) => {
        const meta = resolveComponentMeta(key)
        return meta ? { key: meta.key, cmsFields: meta.cmsFields } : null
      },
    })
    if ('url' in result) {
      win.open(result.url, '_blank', 'noopener')
      return
    }
    if (result.error === 'publish_first') {
      win.alert(STRINGS.alertPwFocusPublishFirst)
      return
    }
    win.alert(STRINGS.alertPwFocusUnavailable)
  }

  /* ---- collection mode ---- */
  function enterCollectionMode(collection: ShellCollectionConfig, path: string) {
    currentCollection = collection
    currentPageId = null
    currentPath = normalizePagePath(path, config.siteUrl)
    sections = []
    canonicalSections = []
    activeSectionId = null
    activeField = null
    dirtySectionIds = {}
    renderCurrentPageContext()
    renderSectionList()
    updateActions()
    setStatus(STRINGS.collectionStatus(collection.label), 'is-ready')
    renderCollectionPanel()
  }

  function renderCollectionPanel() {
    if (!currentCollection) return
    const col = currentCollection
    const today = new Date().toISOString().slice(0, 10)
    fieldEditor.innerHTML =
      '<div class="ve-info-card" style="margin-bottom:10px">' +
        '<strong>' + escapeHtml(col.label) + '</strong>' +
        '<p>' + escapeHtml(STRINGS.collectionDescription(col.root)) + '</p>' +
      '</div>' +
      '<div class="ve-collection-add">' +
        '<div>' +
          '<label for="ve-col-date">' + escapeHtml(STRINGS.collectionDateLabel) + '</label>' +
          '<input type="date" id="ve-col-date" value="' + escapeHtml(today) + '">' +
        '</div>' +
        '<button class="ve-btn ve-btn-primary" id="ve-col-add" type="button">' + escapeHtml(col.addLabel) + '</button>' +
      '</div>' +
      '<div class="ve-ownership-header ve-ownership-pw">' + escapeHtml(STRINGS.collectionEntriesHeader) + '</div>' +
      '<div class="ve-collection-list" id="ve-col-list"><div class="ve-empty-state">' + escapeHtml(STRINGS.collectionLoading) + '</div></div>'
    doc.getElementById('ve-col-add')?.addEventListener('click', createCollectionEntry)
    fetchCollectionEntriesPanel()
  }

  function renderCollectionRow(entry: Record<string, unknown>) {
    const status = String(entry.status || entry._status || '')
    const badge = status === 'past' ? STRINGS.collectionBadgePast : STRINGS.collectionBadgeUpcoming
    const meta = [String(entry.dateLabel || ''), badge].filter(Boolean).join(' · ')
    return '<div class="ve-ownership-item">' +
      '<span class="ve-ownership-item-label">' + escapeHtml(entry.title || STRINGS.collectionEntryUntitled) +
        '<br><span style="color:#64748b;font-size:10px">' + escapeHtml(meta) + '</span></span>' +
      '<button class="ve-ownership-pw-btn" type="button" data-edit-id="' + escapeHtml(String(entry.id || '')) + '">' +
        escapeHtml(STRINGS.openInPw) + '</button>' +
      '</div>'
  }

  function fetchCollectionEntriesPanel() {
    if (!currentCollection) return
    const listEl = doc.getElementById('ve-col-list')
    if (!listEl) return
    api
      .fetchCollectionEntries(currentCollection.listEndpoint)
      .then((data) => {
        const entries = combineCollectionEntries(data)
        if (!entries.length) {
          listEl.innerHTML = '<div class="ve-empty-state">' + escapeHtml(STRINGS.collectionEmpty) + '</div>'
          return
        }
        listEl.innerHTML = entries.map(renderCollectionRow).join('')
        for (const btn of Array.from(listEl.querySelectorAll('[data-edit-id]'))) {
          btn.addEventListener('click', () => openEntryInPw(btn.getAttribute('data-edit-id')))
        }
      })
      .catch(() => {
        listEl.innerHTML = '<div class="ve-empty-state">' + escapeHtml(STRINGS.collectionLoadFailed) + '</div>'
      })
  }

  function openEntryInPw(id: string | null) {
    if (!id) return
    win.open(config.pageEditUrl + '?id=' + encodeURIComponent(id), '_blank', 'noopener')
  }

  function createCollectionEntry() {
    if (isBusy() || !currentCollection) return
    const dateEl = doc.getElementById('ve-col-date') as HTMLInputElement | null
    const date = dateEl ? dateEl.value : ''
    runWithBusy(STRINGS.busyCreatingEntry, () =>
      api
        .createCollectionEntry(currentCollection!.type, date)
        .then((data) => {
          setTransientStatus(STRINGS.statusEntryCreated, 'is-ready')
          if (data && typeof data.editUrl === 'string' && data.editUrl) win.open(data.editUrl, '_blank', 'noopener')
          fetchCollectionEntriesPanel()
        })
        .catch((error: unknown) => {
          setStatus((error instanceof Error && error.message) || STRINGS.errorEntryCreateFailed, 'is-error')
        })
    )
  }

  /* ---- page adoption (iframe navigation is the only page source) ---- */
  function adoptIframePage(path: string) {
    const collection = getCollectionForPath(path)
    if (collection) {
      if (currentCollection && currentCollection.root === collection.root) return null
      persistCurrentDraftNow()
      enterCollectionMode(collection, path)
      return null
    }

    const descriptor = getPageDescriptorByPath(path)
    if (!descriptor) {
      currentCollection = null
      persistCurrentDraftNow()
      currentPageId = null
      currentPath = normalizePagePath(path, config.siteUrl) || path
      resetPageState()
      renderCurrentPageContext()
      renderSectionList()
      renderFieldEditor()
      updateActions()
      setStatus(STRINGS.pageUnavailable, 'is-error')
      return null
    }
    if (!currentCollection && currentPageId === descriptor.id && currentPath === descriptor.path) {
      return descriptor
    }
    currentCollection = null
    persistCurrentDraftNow()
    currentPageId = descriptor.id
    currentPath = descriptor.path
    resetPageState()
    renderCurrentPageContext()
    renderSectionList()
    renderFieldEditor()
    updateActions()
    setStatus(STRINGS.statusLoadingSections, 'is-loading')
    return descriptor
  }

  /* ---- sections fetch + draft restore ---- */
  function fetchSections(opts: { keepStatus?: boolean; busyLabel?: string } = {}): Promise<void> {
    if (!currentPageId || !currentPath) return Promise.resolve()
    const path = currentPath
    if (!opts.keepStatus) setStatus(STRINGS.statusLoadingSections, 'is-loading')

    return runWithBusy(opts.busyLabel || STRINGS.busyLoadingSections, async () => {
      try {
        const data = await api.fetchSections(path)
        let nextSections = cloneShellSections(Array.isArray(data.sections) ? data.sections : [])
        if (path === '/' && data.hero) {
          nextSections.unshift(buildHomepageHeroSection(data.hero, currentPageId))
        }
        canonicalSections = cloneShellSections(nextSections)
        canonicalFingerprint = String(data.fingerprint || '')

        const restore = restoreDraft(storage, {
          pageId: currentPageId!,
          path,
          sections: nextSections,
          fingerprint: canonicalFingerprint,
        })
        sections = restore.sections
        if (restore.restored) {
          if (restore.activeSectionId && sections.some((s) => s.id === restore.activeSectionId)) {
            activeSectionId = restore.activeSectionId
          }
          const storedField = restore.activeField as VeFieldDescriptor | null
          if (storedField && storedField.sectionId === activeSectionId) {
            activeField = storedField
          }
        }
        if (activeSectionId && !getSectionById(activeSectionId)) {
          activeSectionId = null
          activeField = null
        }
        historyPast = []
        historyFuture = []
        refreshDraftUi({ persist: false, message: restore.message || '' })
        if (!opts.keepStatus) {
          setStatus(
            restore.message || (draftChanged() ? STRINGS.statusDraftSaved : STRINGS.statusConnected),
            draftChanged() ? 'is-loading' : 'is-ready'
          )
        } else if (restore.message) {
          setTransientStatus(restore.message, 'is-loading')
        }
      } catch (error) {
        setStatus((error instanceof Error && error.message) || STRINGS.errorLoadFailed, 'is-error')
        throw error
      }
    })
  }

  function loadPath(path: string) {
    resetPageState()
    renderSectionList()
    renderFieldEditor()
    updateActions()
    setStatus(STRINGS.statusLoadingPreview, 'is-loading')
    waitingForIframeReady = true
    beginBusy(STRINGS.busyLoadingPreview)
    scheduleIframeReadyTimeout()

    let url = config.siteUrl + (normalizePagePath(path, config.siteUrl) || '/')
    url += (url.indexOf('?') === -1 ? '?' : '&') + '_visual=1'
    if (config.draftSecret) url += '&draft_secret=' + encodeURIComponent(config.draftSecret)
    iframe.src = url
  }

  /* ---- draft UI refresh ---- */
  function reconcileSelection() {
    if (activeSectionId && !getSectionById(activeSectionId)) {
      activeSectionId = null
      activeField = null
    }
    if (activeField && activeSectionId !== activeField.sectionId) {
      activeField = null
    }
  }

  function refreshDraftUi(opts: { persist?: boolean; message?: string } = {}) {
    dirtySectionIds = computeDirtyIds(sections, canonicalSections)
    reconcileSelection()
    renderSectionList()
    renderFieldEditor()
    updateActions()
    if (iframeReady) {
      bridge.send('sections-replace', { sections })
      bridge.send('section-highlight', { sectionId: activeSectionId })
      if (activeField && activeSectionId === activeField.sectionId) {
        bridge.send('field-highlight', activeField)
      } else {
        activeField = null
        bridge.send('field-reset', {})
      }
    }
    syncIframeState(opts.message || '')
    if (opts.persist !== false) scheduleDraftAutosave()
  }

  /* ---- field changes ---- */
  const HISTORY_FIELDS: Record<string, true> = {
    layout: true, theme: true, bgColor: true, imageOverlay: true, component: true,
    config: true, mediaItems: true, videoUrl: true, videoTitle: true,
  }

  function applyFieldChange(payload: ShellFieldChange & { sectionId: string; __commit?: boolean }) {
    if (isBusy()) return
    const section = getSectionById(payload.sectionId)
    if (!section) return
    if (payload.__commit || HISTORY_FIELDS[payload.field]) pushHistorySnapshot()

    const next = applyShellFieldChange(section, payload)
    if (next === section) return
    sections = sections.map((item) => (item.id === section.id ? next : item))
    store.dispatch({ type: 'edit' })
    notifySectionUpdates(next, payload.field)
    dirtySectionIds = computeDirtyIds(sections, canonicalSections)
    renderSectionList()
    renderFieldEditor()
    scheduleDraftAutosave()
    syncIframeState()
    updateActions()
  }

  /* ---- selection ---- */
  function selectSection(sectionId: string, opts: { scroll?: boolean; clearField?: boolean } = {}) {
    if (!getSectionById(sectionId)) return
    activeSectionId = sectionId
    store.dispatch({ type: 'select-section', sectionId })
    if (opts.clearField !== false) activeField = null
    renderSectionList()
    renderFieldEditor()
    updateActions()
    bridge.send('section-highlight', { sectionId })
    if (opts.clearField !== false) {
      bridge.send('field-reset', {})
    } else if (activeField) {
      bridge.send('field-highlight', activeField)
    }
    if (opts.scroll !== false) bridge.send('section-scroll', { sectionId })
    syncIframeState()
  }

  function selectField(field: VeFieldDescriptor, opts: { scroll?: boolean } = {}) {
    if (!field || !field.sectionId || !getSectionById(field.sectionId)) return
    activeSectionId = field.sectionId
    store.dispatch({ type: 'select-section', sectionId: field.sectionId })
    activeField = { ...field }
    renderSectionList()
    renderFieldEditor()
    updateActions()
    bridge.send('section-highlight', { sectionId: field.sectionId })
    bridge.send('field-highlight', activeField)
    if (opts.scroll !== false) bridge.send('section-scroll', { sectionId: field.sectionId })
    syncIframeState()
  }

  /* ---- section list ---- */
  function renderSectionList() {
    sectionList.innerHTML = ''
    emptyList.textContent = currentPageId ? STRINGS.emptyNoSections : STRINGS.emptyNoPage
    emptyList.style.display = sections.length ? 'none' : 'block'

    sections.forEach((section) => {
      const hero = isHeroSection(section)
      const item = doc.createElement('li')
      item.className = 've-section-item' + (section.id === activeSectionId ? ' is-active' : '')
      item.draggable = !hero

      const drag = doc.createElement('span')
      drag.className = 've-section-drag'
      drag.textContent = hero ? '★' : '⠿'

      const info = doc.createElement('div')
      info.className = 've-section-info'

      const title = doc.createElement('div')
      title.className = 've-section-title'
      title.textContent = formatSectionListTitle(section)
      info.appendChild(title)

      const meta = doc.createElement('div')
      meta.className = 've-section-meta'

      const layout = doc.createElement('span')
      layout.className = 've-layout-badge'
      layout.textContent = STRINGS.layoutLabels[section.layout || ''] || section.layout || STRINGS.sectionFallbackBadge
      meta.appendChild(layout)

      const pwType = getProcessWireTypeKey(section)
      if (pwType) {
        const badge = doc.createElement('span')
        badge.className = 've-layout-badge'
        badge.textContent = 'PW: ' + pwType
        meta.appendChild(badge)
      }

      if (dirtySectionIds[section.id]) {
        const dirty = doc.createElement('span')
        dirty.className = 've-dirty-pill'
        dirty.textContent = STRINGS.dirtyPill
        meta.appendChild(dirty)
      }

      info.appendChild(meta)

      const actions = doc.createElement('div')
      actions.className = 've-section-actions'

      if (!hero) {
        const duplicateBtn = doc.createElement('button')
        duplicateBtn.className = 've-icon-btn'
        duplicateBtn.type = 'button'
        duplicateBtn.title = STRINGS.duplicateTitle
        duplicateBtn.textContent = '⧉'
        duplicateBtn.addEventListener('click', (event) => {
          event.stopPropagation()
          if (isBusy()) return
          if (blockWhileDirty(STRINGS.actionCopy)) return
          duplicateSection(section)
        })

        const deleteBtn = doc.createElement('button')
        deleteBtn.className = 've-icon-btn'
        deleteBtn.type = 'button'
        deleteBtn.title = STRINGS.deleteTitle
        deleteBtn.textContent = '✕'
        deleteBtn.addEventListener('click', (event) => {
          event.stopPropagation()
          if (isBusy()) return
          if (blockWhileDirty(STRINGS.actionDelete)) return
          if (!win.confirm(STRINGS.confirmDeleteSection(section.title || ''))) return
          deleteSection(section)
        })

        actions.appendChild(duplicateBtn)
        actions.appendChild(deleteBtn)
      }

      item.appendChild(drag)
      item.appendChild(info)
      item.appendChild(actions)

      item.addEventListener('click', () => {
        if (isBusy()) return
        selectSection(section.id)
      })

      item.addEventListener('dragstart', (event) => {
        if (hero || isBusy() || blockWhileDirty(STRINGS.actionSort)) {
          event.preventDefault()
          return
        }
        event.dataTransfer?.setData('text/plain', section.id)
        item.style.opacity = '0.5'
      })
      item.addEventListener('dragend', () => {
        item.style.opacity = '1'
        item.style.borderTop = ''
      })
      item.addEventListener('dragover', (event) => {
        event.preventDefault()
        item.style.borderTop = '2px solid #4a7c59'
      })
      item.addEventListener('dragleave', () => {
        item.style.borderTop = ''
      })
      item.addEventListener('drop', (event) => {
        event.preventDefault()
        item.style.borderTop = ''
        if (hero) return
        const fromSectionId = event.dataTransfer?.getData('text/plain')
        if (!fromSectionId || fromSectionId === section.id) return
        reorderSectionsById(fromSectionId, section.id)
      })

      sectionList.appendChild(item)
    })
  }

  /* ---- field editor (ownership panel + config editor) ---- */
  function veFieldRow([label, hint]: readonly [string, string]) {
    return '<div class="ve-ownership-item">' +
      '<span class="ve-ownership-item-label">' + escapeHtml(label) + '</span>' +
      '<span class="ve-ownership-item-hint">' + escapeHtml(hint) + '</span>' +
      '</div>'
  }

  function pwFieldRow(label: string, field: string) {
    return '<div class="ve-ownership-item">' +
      '<span class="ve-ownership-item-label">' + escapeHtml(label) + '</span>' +
      '<button class="ve-ownership-pw-btn" type="button" data-pw-focus="' + escapeHtml(field) + '">' +
      escapeHtml(STRINGS.openInPw) + '</button>' +
      '</div>'
  }

  function renderFieldEditor() {
    if (currentCollection) {
      // Collection panel owns the editor area; leave it intact.
      updateActions()
      return
    }
    const section = getActiveSection()
    const page = currentPageId ? config.pages.find((p) => p.id === currentPageId) : null
    const dirtyCount = Object.keys(dirtySectionIds).length
    const hasDraft = draftChanged()

    if (!currentPageId || !page) {
      fieldEditor.innerHTML = '<div class="ve-empty-state">' + escapeHtml(STRINGS.emptyNoPage) + '</div>'
      updateActions()
      return
    }

    if (!section) {
      fieldEditor.innerHTML =
        '<div class="ve-info-card">' +
          '<strong>' + escapeHtml(STRINGS.infoCardPage) + '</strong>' +
          '<p>' + escapeHtml(page.title) + ' (' + escapeHtml(page.path) + ')</p>' +
        '</div>' +
        '<div class="ve-info-card">' +
          '<strong>' + escapeHtml(STRINGS.infoCardMode) + '</strong>' +
          '<p>' + escapeHtml(editorMode === 'edit' ? STRINGS.modeEditDescription : STRINGS.modeBrowseDescription) + '</p>' +
        '</div>' +
        '<div class="ve-info-card">' +
          '<strong>' + escapeHtml(STRINGS.infoCardStatus) + '</strong>' +
          '<p>' + escapeHtml(hasDraft ? STRINGS.draftOpenDescription : STRINGS.draftNoneDescription) + '</p>' +
        '</div>'
      updateActions()
      return
    }

    const hero = isHeroSection(section)
    const mediaLayouts = ['split_media_text', 'split_text_media', 'full_width_banner', 'media_grid']
    const hasMedia = hero || mediaLayouts.indexOf(section.layout || '') !== -1

    let html = ''

    html +=
      '<div class="ve-info-card" style="margin-bottom:10px">' +
        '<strong style="display:flex;justify-content:space-between;align-items:center">' +
          escapeHtml(section.title || STRINGS.untitledSection) +
          (dirtySectionIds[section.id] ? '<span class="ve-dirty-pill">' + escapeHtml(STRINGS.dirtyPill) + '</span>' : '') +
        '</strong>' +
        '<p style="color:#64748b;font-size:11px;margin-top:2px">' +
          escapeHtml(STRINGS.layoutLabels[section.layout || ''] || section.layout || STRINGS.sectionFallbackBadge) +
          (section.component ? ' · ' + escapeHtml(formatComponentLabel(section.component)) : '') +
        '</p>' +
      '</div>'

    html += '<div class="ve-ownership-header ve-ownership-ve">' + escapeHtml(STRINGS.ownershipVe) + '</div>'
    html += '<div class="ve-ownership-list">'
    if (hero) {
      for (const row of STRINGS.veRowHero) html += veFieldRow(row)
    } else {
      html += veFieldRow(STRINGS.veRowTitle)
      html += veFieldRow(STRINGS.veRowEyebrow)
      html += veFieldRow(STRINGS.veRowText)
      html += veFieldRow(STRINGS.veRowLayoutTheme)
      html += veFieldRow(STRINGS.veRowBgOverlay)
      html += veFieldRow(STRINGS.veRowButtons)
      if (hasMedia) {
        html += veFieldRow(STRINGS.veRowMedia)
        html += veFieldRow(STRINGS.veRowMediaMeta)
      }
      if (section.layout === 'video_embed') html += veFieldRow(STRINGS.veRowVideo)
      if (section.component) html += veFieldRow(STRINGS.veRowComponentConfig)
    }
    html += '</div>'

    // config editor placeholder (filled with DOM below)
    const componentMeta = section.component ? resolveComponentMeta(section.component) : null
    const configSchema = componentMeta ? sanitizeConfigSchema(componentMeta.configSchema) : []
    if (configSchema.length) {
      html += '<div class="ve-ownership-header ve-ownership-ve">' + escapeHtml(STRINGS.configEditorHeader) + '</div>'
      html += '<div id="ve-config-editor-slot"></div>'
    }

    html += '<div class="ve-ownership-header ve-ownership-pw">' + escapeHtml(STRINGS.ownershipPw) + '</div>'
    html += '<div class="ve-ownership-list">'
    if (hero) {
      html += pwFieldRow(STRINGS.pwRowHeroImage, 'media')
      html += pwFieldRow(STRINGS.pwRowHeroAll, '')
    } else {
      if (hasMedia) html += pwFieldRow(STRINGS.pwRowImages, 'media')
      html += pwFieldRow(STRINGS.pwRowAllFields, '')
    }
    html += '</div>'

    if (hasDraft && dirtyCount) {
      html +=
        '<div class="ve-info-card" style="margin-top:10px">' +
          '<strong>' + escapeHtml(STRINGS.infoCardDraft) + '</strong>' +
          '<p>' + escapeHtml(STRINGS.draftDirtyCount(dirtyCount)) + '</p>' +
        '</div>'
    }

    fieldEditor.innerHTML = html

    if (configSchema.length) {
      const slot = doc.getElementById('ve-config-editor-slot')
      if (slot) {
        slot.appendChild(
          renderComponentConfigEditor({
            doc,
            schema: configSchema,
            config: (section.config || {}) as Record<string, unknown>,
            onChange: (key, value) => {
              applyFieldChange({ sectionId: section.id, field: 'config', configKey: key, value })
            },
          })
        )
      }
    }

    for (const btn of Array.from(fieldEditor.querySelectorAll('[data-pw-focus]'))) {
      const field = btn.getAttribute('data-pw-focus') || ''
      btn.addEventListener('click', () => {
        openProcessWireFocus(field ? { field } : {})
      })
    }

    updateActions()
  }

  /* ---- publish / discard ---- */
  function saveDirtySections() {
    if (isSaving() || !draftChanged() || isBusy()) return
    if (!currentPageId || !currentPath) return
    const baseSectionsSnapshot = cloneShellSections(canonicalSections)
    const publishPath = currentPath

    store.dispatch({ type: 'edit' })
    store.dispatch({ type: 'publish-start', busyLabel: STRINGS.busyPublishing })
    updateActions()
    setStatus(STRINGS.btnPublishing, 'is-loading')
    syncIframeState()

    runWithBusy(STRINGS.busyPublishing, () =>
      api.publish({
        pageId: currentPageId!,
        path: publishPath,
        baseFingerprint: canonicalFingerprint,
        sections: cloneShellSections(sections),
      })
    )
      .then((data) => {
        let nextSections = cloneShellSections(Array.isArray(data.sections) ? data.sections : [])
        if (publishPath === '/' && data.hero) {
          nextSections.unshift(buildHomepageHeroSection(data.hero, currentPageId))
        }
        canonicalSections = cloneShellSections(nextSections)
        canonicalFingerprint = String(data.fingerprint || '')
        sections = cloneShellSections(nextSections)
        historyPast = []
        historyFuture = []
        dirtySectionIds = {}
        clearDraft(storage, currentPageId!, publishPath)
        const revalidated = data.revalidated !== false
        store.dispatch({ type: 'publish-success', revalidated })
        refreshDraftUi({ persist: false, message: STRINGS.statusPublished })
        const pill = publishPill(data)
        setStatus(pill.text, pill.cls)
        bridge.send('save-result', { success: true, revalidated })
      })
      .catch((error: unknown) => {
        const apiError = error instanceof ApiError ? error : null
        const message = (error instanceof Error && error.message) || STRINGS.errorPublishFailed
        store.dispatch({ type: 'publish-failure', error: message })
        let resolvedConflict = false
        const data = apiError?.data
        if (data && (Array.isArray(data.sections) || data.hero)) {
          let canonicalFromError = cloneShellSections(Array.isArray(data.sections) ? data.sections : [])
          if (publishPath === '/' && data.hero) {
            canonicalFromError.unshift(buildHomepageHeroSection(data.hero as Record<string, unknown>, currentPageId))
          }
          if (data.fingerprint) {
            const resolved = resolvePublishConflict(baseSectionsSnapshot, canonicalFromError, sections, {
              keepLocalField: (sectionId, field) => win.confirm(STRINGS.confirmFieldConflict(sectionId, field)),
              keepLocalOrder: () => win.confirm(STRINGS.confirmOrderConflict),
            })
            canonicalSections = cloneShellSections(canonicalFromError)
            canonicalFingerprint = String(data.fingerprint || '')
            sections = cloneShellSections(resolved.mergedSections)
            dirtySectionIds = {}
            refreshDraftUi({
              message: resolved.conflicts.length
                ? STRINGS.statusConflictsResolved
                : STRINGS.statusServerChangesAdopted,
            })
            resolvedConflict = true
          } else {
            canonicalSections = cloneShellSections(canonicalFromError)
            canonicalFingerprint = String(data.fingerprint || '')
          }
        }
        if (resolvedConflict) {
          setStatus(STRINGS.statusConflictsRetry, 'is-loading')
        } else {
          setStatus(message, 'is-error')
        }
        bridge.send('save-result', { success: false, error: message })
      })
      .finally(() => {
        updateActions()
        syncIframeState()
      })
  }

  function resetChanges() {
    if (isBusy()) return
    if (!confirmDiscardChanges()) return
    if (currentPageId && currentPath) clearDraft(storage, currentPageId, currentPath)
    sections = cloneShellSections(canonicalSections)
    historyPast = []
    historyFuture = []
    dirtySectionIds = {}
    activeField = null
    if (activeSectionId && !getSectionById(activeSectionId)) activeSectionId = null
    store.dispatch({ type: 'discard' })
    refreshDraftUi({ persist: false, message: STRINGS.statusDraftDiscarded })
    setStatus(STRINGS.statusDraftDiscarded, 'is-ready')
  }

  /* ---- section CRUD (draft-level, published together) ---- */
  function reorderSectionsById(fromSectionId: string, toSectionId: string) {
    if (!currentPageId || isSaving() || isBusy() || blockWhileDirty(STRINGS.actionSort)) return
    pushHistorySnapshot()
    const hero = sections.filter((section) => isHeroSection(section))
    const order = getSortableSections().slice()
    const fromIndex = order.findIndex((section) => section.id === fromSectionId)
    const toIndex = order.findIndex((section) => section.id === toSectionId)
    if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) return
    const [moved] = order.splice(fromIndex, 1)
    order.splice(toIndex, 0, moved)
    sections = hero.concat(order)
    store.dispatch({ type: 'edit' })
    refreshDraftUi()
    setTransientStatus(STRINGS.statusOrderUpdated, 'is-loading')
  }

  function moveSection(sectionId: string, direction: number) {
    if (!currentPageId || isSaving() || isBusy() || blockWhileDirty(STRINGS.actionMove)) return
    const order = getSortableSections()
    const index = order.findIndex((section) => section.id === sectionId)
    if (index === -1) return
    const targetIndex = index + direction
    if (targetIndex < 0 || targetIndex >= order.length) return
    reorderSectionsById(order[index].id, order[targetIndex].id)
  }

  function addDraftSection(payload: Record<string, unknown>, message: string, presetName?: string) {
    if (!currentPageId || isSaving() || isBusy()) return
    pushHistorySnapshot()
    const section = normalizeShellSection({
      id: createDraftId(),
      title: presetName || STRINGS.newSectionTitle,
      text: '<p></p>',
      layout: 'rich_text',
      theme: 'default',
      buttons: [],
      ...payload,
    })
    if (!section) return
    sections = cloneShellSections(sections)
    sections.push(section)
    activeSectionId = section.id
    activeField = null
    store.dispatch({ type: 'edit' })
    refreshDraftUi()
    setTransientStatus(message, 'is-loading')
  }

  function deleteSection(section: ShellSection) {
    if (!currentPageId || !section || isHeroSection(section) || isSaving() || isBusy()) return
    pushHistorySnapshot()
    sections = sections.filter((item) => item.id !== section.id)
    if (activeSectionId === section.id) {
      activeSectionId = null
      activeField = null
    }
    store.dispatch({ type: 'edit' })
    refreshDraftUi()
    setTransientStatus(STRINGS.statusSectionDeleted, 'is-loading')
  }

  function duplicateSection(section: ShellSection) {
    if (!section || isHeroSection(section) || !currentPageId || isSaving() || isBusy() || blockWhileDirty(STRINGS.actionDuplicate)) return
    pushHistorySnapshot()
    const copy = cloneShellSections([section])[0]
    if (!copy) return
    const sourceIndex = sections.findIndex((item) => item.id === section.id)
    copy.id = createDraftId()
    delete copy.pwId
    copy.title = (section.title || STRINGS.newSectionTitle) + STRINGS.copySuffix
    sections = cloneShellSections(sections)
    if (sourceIndex === -1) {
      sections.push(copy)
    } else {
      sections.splice(sourceIndex + 1, 0, copy)
    }
    activeSectionId = copy.id
    activeField = null
    store.dispatch({ type: 'edit' })
    refreshDraftUi()
    setTransientStatus(STRINGS.statusSectionDuplicated, 'is-loading')
  }

  function handleSectionAction(sectionId: string, action: string) {
    const section = getSectionById(sectionId)
    if (!section || isHeroSection(section)) return
    switch (action) {
      case 'delete':
        if (!win.confirm(STRINGS.confirmDeleteSection(section.title || ''))) return
        deleteSection(section)
        break
      case 'move-up':
        moveSection(sectionId, -1)
        break
      case 'move-down':
        moveSection(sectionId, 1)
        break
      case 'duplicate':
        duplicateSection(section)
        break
    }
  }

  /* ---- media modal ---- */
  function openMediaModal(request: { sectionId: string; targetField?: string }) {
    if (!request || !request.sectionId || isBusy()) return
    mediaRequest = {
      sectionId: request.sectionId,
      targetField: request.targetField || (request.sectionId === '__hero__' ? 'hero_image' : 'section_image'),
    }
    mediaFiles = []
    mediaGrid.innerHTML = ''
    mediaEmpty.textContent = STRINGS.mediaLoading
    mediaEmpty.style.display = 'block'
    mediaModal.classList.add('is-open')

    api
      .fetchMediaFiles()
      .then((files) => {
        mediaFiles = files
        renderMediaGrid()
      })
      .catch((error: unknown) => {
        mediaGrid.innerHTML = ''
        mediaEmpty.textContent = (error instanceof Error && error.message) || STRINGS.mediaLoadFailed
        mediaEmpty.style.display = 'block'
      })
  }

  function closeMediaModal() {
    mediaRequest = null
    mediaModal.classList.remove('is-open')
  }

  function renderMediaGrid() {
    mediaGrid.innerHTML = ''
    if (!mediaFiles.length) {
      mediaEmpty.textContent = STRINGS.mediaEmpty
      mediaEmpty.style.display = 'block'
      return
    }
    mediaEmpty.style.display = 'none'
    for (const file of mediaFiles) {
      const card = doc.createElement('button')
      card.type = 'button'
      card.className = 've-media-card'
      const name = String(file.assetTitle || file.fileName || STRINGS.mediaFallbackName)
      card.innerHTML =
        '<img src="' + escapeHtml(String(file.url || '')) + '" alt="' + escapeHtml(name) + '">' +
        '<div class="ve-media-card-body">' +
          '<strong>' + escapeHtml(name) + '</strong>' +
          '<span>' + escapeHtml(String(file.fileName || '')) + '</span>' +
        '</div>'
      card.addEventListener('click', () => importMediaFile(file))
      mediaGrid.appendChild(card)
    }
  }

  function importMediaFile(file: Record<string, unknown>) {
    if (isBusy()) return
    const section = mediaRequest ? getSectionById(mediaRequest.sectionId) : null
    if (!section || !mediaRequest) return

    pushHistorySnapshot()
    const targetField = mediaRequest.targetField || (isHeroSection(section) ? 'hero_image' : 'section_image')
    const mediaFile = file as unknown as DraftMediaFile
    const next =
      targetField === 'section_images'
        ? appendDraftMedia(section, mediaFile, targetField)
        : applyDraftMedia(section, mediaFile, targetField)
    sections = sections.map((item) => (item.id === section.id ? next : item))
    store.dispatch({ type: 'edit' })
    closeMediaModal()
    refreshDraftUi()
    if (activeField) {
      selectField(activeField, { scroll: false })
    } else if (section.id) {
      selectSection(section.id, { scroll: false })
    }
    setTransientStatus(STRINGS.statusMediaSelected, 'is-loading')
  }

  /* ---- presets ---- */
  function rebuildPresetTagsMap() {
    presetTagsByComponent = {}
    for (const preset of presetItems) {
      const component = preset?.payload?.component
      if (!component) continue
      const key = String(component)
      const tags = (presetTagsByComponent[key] = presetTagsByComponent[key] || [])
      if (preset.category && !tags.includes(String(preset.category))) tags.push(String(preset.category))
      if (preset.name && !tags.includes(String(preset.name))) tags.push(String(preset.name))
    }
  }

  function renderPresetCategories() {
    const current = presetCategory.value || ''
    const categories = new Set<string>()
    for (const preset of presetItems) {
      if (preset?.category) categories.add(String(preset.category))
    }
    presetCategory.innerHTML = '<option value="">' + escapeHtml(STRINGS.presetAllCategories) + '</option>'
    for (const name of Array.from(categories).sort()) {
      const option = doc.createElement('option')
      option.value = name
      option.textContent = name
      presetCategory.appendChild(option)
    }
    presetCategory.value = current
  }

  function filteredPresets() {
    const query = String(presetSearch.value || '').trim().toLowerCase()
    const category = String(presetCategory.value || '')
    return presetItems.filter((preset) => {
      if (category && preset.category !== category) return false
      if (!query) return true
      const haystack = [preset.name, preset.description, preset.category, preset.payload?.component]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
      return haystack.indexOf(query) !== -1
    })
  }

  function renderPresetList() {
    presetList.innerHTML = ''
    const items = filteredPresets()
    if (!items.length) {
      presetEmpty.textContent = STRINGS.presetEmpty
      presetEmpty.style.display = 'block'
      return
    }
    presetEmpty.style.display = 'none'
    for (const preset of items) {
      const card = doc.createElement('div')
      card.className = 've-preset-item'
      card.innerHTML =
        '<strong>' + escapeHtml(preset.name || STRINGS.presetFallbackName) + '</strong>' +
        (preset.category ? '<span class="ve-layout-badge">' + escapeHtml(preset.category) + '</span>' : '') +
        '<p>' + escapeHtml(preset.description || '') + '</p>'
      const actions = doc.createElement('div')
      actions.className = 've-inline-actions'
      const insertBtn = doc.createElement('button')
      insertBtn.type = 'button'
      insertBtn.className = 've-btn ve-btn-primary'
      insertBtn.textContent = STRINGS.presetInsert
      insertBtn.addEventListener('click', () => insertPreset(preset))
      actions.appendChild(insertBtn)
      card.appendChild(actions)
      presetList.appendChild(card)
    }
  }

  function loadPresets() {
    return api
      .fetchPresets()
      .then((presets) => {
        presetItems = presets as PresetItem[]
        rebuildPresetTagsMap()
        renderPresetCategories()
        renderPresetList()
        syncIframeState()
      })
      .catch((error: unknown) => {
        presetItems = []
        presetEmpty.textContent = (error instanceof Error && error.message) || STRINGS.presetLoadFailed
        presetEmpty.style.display = 'block'
      })
  }

  function insertPreset(preset: PresetItem) {
    if (!preset || !preset.payload) return
    if (isBusy() || !currentPageId) return
    closePresetModal()
    addDraftSection(cloneJson(preset.payload, {}) || {}, STRINGS.statusPresetInserted, preset.name)
  }

  function openPresetModal() {
    if (isBusy()) return
    if (!presetItems.length) {
      loadPresets().then(() => renderPresetList())
    } else {
      renderPresetList()
    }
    presetModal.classList.add('is-open')
  }

  function closePresetModal() {
    presetModal.classList.remove('is-open')
  }

  /* ---- add-section modal ---- */
  const ADD_SECTION_CATALOG: AddCatalogEntry[] = (() => {
    const items: AddCatalogEntry[] = []
    for (const key of CORE_LAYOUTS) {
      items.push({
        id: 'layout-' + key,
        category: STRINGS.addCategoryBase,
        label: STRINGS.layoutLabels[key] || key,
        description: STRINGS.coreLayoutDescriptions[key] || '',
        icon: CORE_ICONS[key] || '◆',
        payload: { layout: key },
      })
    }
    for (const entry of config.componentRegistry) {
      items.push({
        id: 'component-' + entry.key,
        category: STRINGS.componentCategories[entry.key] || STRINGS.addCategoryOther,
        label: entry.label || entry.key,
        description: String(entry.notes || ''),
        icon: COMP_ICONS[entry.key] || '◆',
        payload: {
          layout: 'component',
          component: entry.key,
          config: entry.defaultConfig || {},
        },
      })
    }
    items.sort((a, b) => {
      let ai = STRINGS.addCategoryOrder.indexOf(a.category)
      let bi = STRINGS.addCategoryOrder.indexOf(b.category)
      if (ai === -1) ai = 99
      if (bi === -1) bi = 99
      return ai - bi
    })
    return items
  })()

  function buildAddFilterOptions() {
    const categories = new Set(ADD_SECTION_CATALOG.map((entry) => entry.category))
    addFilter.innerHTML = '<option value="">' + escapeHtml(STRINGS.addAllFilter) + '</option>'
    for (const name of Array.from(categories)) {
      const option = doc.createElement('option')
      option.value = name
      option.textContent = name
      addFilter.appendChild(option)
    }
  }

  function filteredAddCatalog() {
    const query = String(addSearch.value || '').trim().toLowerCase()
    const category = String(addFilter.value || '')
    return ADD_SECTION_CATALOG.filter((entry) => {
      if (category && entry.category !== category) return false
      if (!query) return true
      return [entry.label, entry.description, entry.category].join(' ').toLowerCase().indexOf(query) !== -1
    })
  }

  function renderAddGrid() {
    addScroll.innerHTML = ''
    const items = filteredAddCatalog()
    if (!items.length) {
      addScroll.innerHTML = '<div class="ve-empty-state">' + escapeHtml(STRINGS.addEmpty) + '</div>'
      return
    }
    let currentCat = ''
    let grid: HTMLElement | null = null
    for (const entry of items) {
      if (entry.category !== currentCat) {
        currentCat = entry.category
        const label = doc.createElement('div')
        label.className = 've-add-group-label'
        label.textContent = currentCat
        addScroll.appendChild(label)
        grid = doc.createElement('div')
        grid.className = 've-add-grid'
        addScroll.appendChild(grid)
      }
      const card = doc.createElement('div')
      card.className = 've-add-card'
      card.innerHTML =
        '<div class="ve-add-icon">' + escapeHtml(entry.icon) + '</div>' +
        '<div class="ve-add-text">' +
          '<div class="ve-add-label">' + escapeHtml(entry.label) + '</div>' +
          '<div class="ve-add-desc">' + escapeHtml(entry.description) + '</div>' +
        '</div>'
      card.addEventListener('click', () => {
        closeAddModal()
        // verbatim old copy, including escapeHtml on the label
        addDraftSection(cloneJson(entry.payload, {}), escapeHtml(entry.label) + STRINGS.statusTypeAddedSuffix)
      })
      grid!.appendChild(card)
    }
  }

  function openAddModal() {
    if (isBusy() || !currentPageId) return
    if (blockWhileDirty(STRINGS.actionAdd)) return
    buildAddFilterOptions()
    addSearch.value = ''
    addFilter.value = ''
    renderAddGrid()
    addModal.classList.add('is-open')
    addSearch.focus()
  }

  function closeAddModal() {
    addModal.classList.remove('is-open')
  }

  /* ---- iframe message handling ---- */
  function handleIframeMessage(message: IframeToParentMessage) {
    if (message.type === 'ready') {
      const readyPath = normalizePagePath(message.path, config.siteUrl)
      let readyDescriptor = null
      if (readyPath) {
        store.dispatch({ type: 'iframe-ready', path: readyPath })
        readyDescriptor = adoptIframePage(readyPath)
      }
      iframeReady = true
      clearIframeReadyTimeout()
      syncIframeState()
      if (readyPath && !readyDescriptor) {
        if (waitingForIframeReady) {
          waitingForIframeReady = false
          endBusy()
        }
        return
      }
      setStatus(STRINGS.statusConnected, 'is-ready')
      if (currentPageId && currentPath) {
        fetchSections({ busyLabel: STRINGS.busyLoadingSections })
          .catch(() => undefined)
          .finally(() => {
            if (waitingForIframeReady) {
              waitingForIframeReady = false
              endBusy()
            }
          })
      } else if (waitingForIframeReady) {
        waitingForIframeReady = false
        endBusy()
      }
      return
    }

    if (isBusy()) return

    switch (message.type) {
      case 'section-click':
        selectSection(message.sectionId, { scroll: false })
        break
      case 'field-select':
        selectField(message, { scroll: false })
        break
      case 'field-change':
      case 'field-commit':
        applyFieldChange({
          sectionId: message.sectionId,
          field: message.field,
          value: message.value,
          buttonIndex: message.buttonIndex,
          configKey: message.configKey,
          __commit: message.type === 'field-commit',
        })
        break
      case 'media-request':
        openMediaModal(message)
        break
      case 'open-processwire':
        openProcessWireFocus(message)
        break
      case 'section-action':
        handleSectionAction(message.sectionId, message.action)
        break
    }
  }

  /* ---- top-level UI events ---- */
  const cleanups: Array<() => void> = []
  function on(el: HTMLElement | Window, type: string, handler: (event: any) => void) {
    el.addEventListener(type, handler)
    cleanups.push(() => el.removeEventListener(type, handler))
  }

  on(btnRefresh, 'click', () => {
    if (isBusy()) return
    if (!currentPath && !currentPageId) return
    if (blockWhileDirty(STRINGS.actionReload)) return
    persistCurrentDraftNow()
    loadPath(currentPath || '/')
  })

  on(btnPresets, 'click', () => openPresetModal())

  on(btnPw, 'click', () => {
    if (isBusy() || !currentPageId) return
    win.open(config.pageEditUrl + '?id=' + currentPageId, '_blank')
  })

  on(btnAdd, 'click', () => openAddModal())
  on(btnSave, 'click', () => saveDirtySections())
  on(btnReset, 'click', () => resetChanges())

  on(btnModeEdit, 'click', () => {
    if (isBusy()) return
    editorMode = 'edit'
    updateActions()
    syncIframeState()
  })

  on(btnModeBrowse, 'click', () => {
    if (isBusy()) return
    editorMode = 'browse'
    updateActions()
    syncIframeState()
  })

  on(mediaClose, 'click', () => closeMediaModal())
  on(mediaModal, 'click', (event) => {
    if (isBusy()) return
    if (event.target === mediaModal) closeMediaModal()
  })

  on(presetClose, 'click', () => closePresetModal())
  on(presetModal, 'click', (event) => {
    if (isBusy()) return
    if (event.target === presetModal) closePresetModal()
  })
  on(presetSearch, 'input', () => renderPresetList())
  on(presetCategory, 'change', () => renderPresetList())

  on(addClose, 'click', () => closeAddModal())
  on(addModal, 'click', (event) => {
    if (event.target === addModal) closeAddModal()
  })
  on(addSearch, 'input', () => renderAddGrid())
  on(addFilter, 'change', () => renderAddGrid())

  on(win, 'keydown', (event: KeyboardEvent) => {
    if (isBusy()) return
    const isMeta = Boolean(event.metaKey || event.ctrlKey)
    if (isMeta && event.key.toLowerCase() === 's') {
      event.preventDefault()
      saveDirtySections()
      return
    }
    if (isMeta && event.key.toLowerCase() === 'z') {
      event.preventDefault()
      if (event.shiftKey) {
        redoChange()
      } else {
        undoChange()
      }
      return
    }
    if (event.key === 'Escape') {
      if (addModal.classList.contains('is-open')) closeAddModal()
      if (mediaModal.classList.contains('is-open')) closeMediaModal()
      if (presetModal.classList.contains('is-open')) closePresetModal()
    }
  })

  on(win, 'beforeunload', (event: BeforeUnloadEvent) => {
    persistCurrentDraftNow()
    if (!draftChanged()) return
    event.preventDefault()
    event.returnValue = ''
  })

  /* ---- boot ---- */
  setStatus(STRINGS.statusDisconnected)
  renderBusyOverlay()
  renderCurrentPageContext()
  updateActions()
  loadPresets()
  ;(() => {
    const params = new URLSearchParams(win.location.search || '')
    const bootPath = normalizePagePath(params.get('path') || '', config.siteUrl) || '/'
    loadPath(bootPath)
  })()

  return {
    destroy() {
      bridge.destroy()
      for (const cleanup of cleanups) cleanup()
      if (busyTimer) clearTimeout(busyTimer)
      if (iframeReadyTimer) clearTimeout(iframeReadyTimer)
      if (draftAutosaveTimer) clearTimeout(draftAutosaveTimer)
      if (statusTimer) clearTimeout(statusTimer)
    },
  }
}
