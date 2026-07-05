/**
 * Shell configuration: the PHP bootstrap (site/templates/visual-editor.php)
 * emits ONE JSON blob (<script type="application/json" id="ve-config">).
 * This module validates it and derives the strict iframe-origin allowlist —
 * closing the old shell's origin hole (it accepted messages from any origin
 * and posted with targetOrigin '*').
 */

export interface ShellPageDescriptor {
  id: number
  title: string
  path: string
  template: string
}

export interface ShellCollectionConfig {
  type: string
  root: string
  label: string
  listEndpoint: string
  addLabel: string
}

export interface ShellComponentRegistryEntry {
  key: string
  label: string
  aliases?: string[]
  cmsFields?: string[]
  notes?: string
  defaultConfig?: Record<string, unknown>
  configSchema?: Array<Record<string, unknown>>
  [extra: string]: unknown
}

export interface ShellFocusFieldsConfig {
  heroBaseFields: string[]
  sectionBaseFields: string[]
  fieldMappings: Record<string, string[]>
  heroFieldMappings: Record<string, string[]>
  buttonFieldMappings: Record<string, string[]>
}

export interface ShellConfig {
  /** Site base URL without trailing slash, e.g. https://bioco.ch */
  siteUrl: string
  apiRoot: string
  adminUrl: string
  pageEditUrl: string
  visualEditorUrl: string
  draftSecret: string
  pages: ShellPageDescriptor[]
  collections: Record<string, ShellCollectionConfig>
  componentRegistry: ShellComponentRegistryEntry[]
  focusFields: ShellFocusFieldsConfig
  /** postMessage origin allowlist for the site iframe (derived + explicit extras). */
  iframeOrigins: string[]
}

type UnknownRecord = Record<string, unknown>

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isNonEmptyString(value: unknown): value is string {
  return typeof value === 'string' && value.length > 0
}

function sanitizeStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) return []
  return value.filter((entry): entry is string => isNonEmptyString(entry))
}

function sanitizeStringArrayMap(value: unknown): Record<string, string[]> {
  if (!isRecord(value)) return {}
  const out: Record<string, string[]> = {}
  for (const [key, entry] of Object.entries(value)) {
    if (!Array.isArray(entry)) continue
    out[key] = sanitizeStringArray(entry)
  }
  return out
}

function hostIsLocalOrIp(hostname: string): boolean {
  if (hostname === 'localhost' || hostname.endsWith('.localhost')) return true
  return /^[0-9.]+$/.test(hostname) || hostname.includes(':') // IPv4 / IPv6
}

/**
 * Origins the shell accepts iframe messages from (and posts to): the site
 * origin plus its www/non-www twin. Localhost/IP origins have no twin.
 */
export function iframeOriginsFor(siteUrl: string): string[] {
  const url = new URL(siteUrl)
  const origins = [url.origin]
  if (!hostIsLocalOrIp(url.hostname)) {
    const twinHost = url.hostname.startsWith('www.') ? url.hostname.slice(4) : `www.${url.hostname}`
    const twin = new URL(url.origin)
    twin.hostname = twinHost
    if (twin.origin !== url.origin) origins.push(twin.origin)
  }
  return origins
}

function parsePages(value: unknown): ShellPageDescriptor[] {
  if (!Array.isArray(value)) return []
  const pages: ShellPageDescriptor[] = []
  for (const entry of value) {
    if (!isRecord(entry)) continue
    const id = Number(entry.id)
    if (!Number.isFinite(id) || id <= 0) continue
    if (!isNonEmptyString(entry.path)) continue
    pages.push({
      id,
      title: typeof entry.title === 'string' ? entry.title : '',
      path: entry.path,
      template: typeof entry.template === 'string' ? entry.template : '',
    })
  }
  return pages
}

function parseCollections(value: unknown): Record<string, ShellCollectionConfig> {
  if (!isRecord(value)) return {}
  const collections: Record<string, ShellCollectionConfig> = {}
  for (const [root, entry] of Object.entries(value)) {
    if (!isRecord(entry)) continue
    if (!isNonEmptyString(entry.type) || !isNonEmptyString(entry.listEndpoint)) continue
    collections[root] = {
      type: entry.type,
      root: isNonEmptyString(entry.root) ? entry.root : root,
      label: isNonEmptyString(entry.label) ? entry.label : root,
      listEndpoint: entry.listEndpoint,
      addLabel: isNonEmptyString(entry.addLabel) ? entry.addLabel : 'Neuen Eintrag erstellen',
    }
  }
  return collections
}

function parseComponentRegistry(value: unknown): ShellComponentRegistryEntry[] {
  if (!Array.isArray(value)) return []
  return value.filter(
    (entry): entry is ShellComponentRegistryEntry =>
      isRecord(entry) && isNonEmptyString(entry.key) && isNonEmptyString(entry.label)
  )
}

function parseFocusFields(value: unknown): ShellFocusFieldsConfig {
  const record = isRecord(value) ? value : {}
  return {
    heroBaseFields: sanitizeStringArray(record.heroBaseFields),
    sectionBaseFields: sanitizeStringArray(record.sectionBaseFields),
    fieldMappings: sanitizeStringArrayMap(record.fieldMappings),
    heroFieldMappings: sanitizeStringArrayMap(record.heroFieldMappings),
    buttonFieldMappings: sanitizeStringArrayMap(record.buttonFieldMappings),
  }
}

function parseExtraOrigins(value: unknown): string[] {
  return sanitizeStringArray(value).filter((origin) => origin !== 'null')
}

/** Validate the PHP-emitted config blob. Throws on structurally unusable input. */
export function parseShellConfig(raw: unknown): ShellConfig {
  if (!isRecord(raw)) throw new Error('visual-editor shell config missing or not an object')
  if (!isNonEmptyString(raw.siteUrl)) throw new Error('visual-editor shell config: siteUrl missing')
  let siteOrigins: string[]
  try {
    siteOrigins = iframeOriginsFor(raw.siteUrl)
  } catch {
    throw new Error(`visual-editor shell config: siteUrl is not a valid URL: ${raw.siteUrl}`)
  }
  if (!isNonEmptyString(raw.apiRoot)) throw new Error('visual-editor shell config: apiRoot missing')
  if (!isNonEmptyString(raw.pageEditUrl)) throw new Error('visual-editor shell config: pageEditUrl missing')

  const iframeOrigins = [...siteOrigins]
  for (const origin of parseExtraOrigins(raw.allowedOrigins)) {
    if (!iframeOrigins.includes(origin)) iframeOrigins.push(origin)
  }

  return {
    siteUrl: raw.siteUrl.replace(/\/+$/, ''),
    apiRoot: raw.apiRoot,
    adminUrl: typeof raw.adminUrl === 'string' ? raw.adminUrl : '',
    pageEditUrl: raw.pageEditUrl,
    visualEditorUrl: typeof raw.visualEditorUrl === 'string' ? raw.visualEditorUrl : '',
    draftSecret: typeof raw.draftSecret === 'string' ? raw.draftSecret : '',
    pages: parsePages(raw.pages),
    collections: parseCollections(raw.collections),
    componentRegistry: parseComponentRegistry(raw.componentRegistry),
    focusFields: parseFocusFields(raw.focusFields),
    iframeOrigins,
  }
}

/** Read + parse the config blob the PHP bootstrap embeds in the page. */
export function readShellConfig(doc: Document): ShellConfig {
  const el = doc.getElementById('ve-config')
  if (!el || !el.textContent) throw new Error('visual-editor shell config element #ve-config missing')
  return parseShellConfig(JSON.parse(el.textContent))
}
