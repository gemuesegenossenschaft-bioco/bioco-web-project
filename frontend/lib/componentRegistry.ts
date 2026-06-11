import registryData from '../../site/templates/component-registry.json'
import type { SectionConfigObject, SectionConfigValue } from '@/lib/processwire-types'

export interface ComponentRegistryConfigOption {
  label: string
  value: string | number
}

export interface ComponentRegistryConfigField {
  key: string
  label: string
  type: 'select' | 'range' | 'text' | 'number'
  options?: ComponentRegistryConfigOption[]
  min?: number
  max?: number
  step?: number
  placeholder?: string
}

export interface ComponentRegistryEntry {
  key: string
  label: string
  kind: 'renderable' | 'semantic'
  frontendTarget: {
    file: string
    export: string
  }
  cmsFields: string[]
  notes?: string
  aliases?: string[]
  defaultConfig?: SectionConfigObject
  configSchema?: ComponentRegistryConfigField[]
}

export interface ResolvedComponentRegistryEntry {
  entry: ComponentRegistryEntry
  matchedKey: string
  canonicalKey: string
}

const componentRegistry = registryData as ComponentRegistryEntry[]

function normalizeLookupKey(value?: string | null): string {
  return String(value || '').trim().toLowerCase()
}

const componentRegistryLookup = componentRegistry.reduce<Record<string, ComponentRegistryEntry>>((acc, entry) => {
  acc[normalizeLookupKey(entry.key)] = entry
  for (const alias of entry.aliases || []) {
    acc[normalizeLookupKey(alias)] = entry
  }
  return acc
}, {})

export function getComponentRegistry(): ComponentRegistryEntry[] {
  return componentRegistry
}

export function getRenderableComponentRegistryEntries(): ComponentRegistryEntry[] {
  return componentRegistry.filter((entry) => entry.kind === 'renderable')
}

function cloneConfig<T extends SectionConfigValue | ComponentRegistryConfigField[] | undefined>(value: T): T {
  if (value == null) return value
  return JSON.parse(JSON.stringify(value)) as T
}

export function resolveComponentRegistryEntry(rawKey?: string | null): ResolvedComponentRegistryEntry | null {
  const matchedKey = normalizeLookupKey(rawKey)
  if (!matchedKey) return null
  const entry = componentRegistryLookup[matchedKey]
  if (!entry) return null
  return {
    entry,
    matchedKey,
    canonicalKey: entry.key,
  }
}

export function formatComponentDisplayName(rawKey?: string | null): string {
  const raw = String(rawKey || '').trim()
  if (!raw) return ''
  const resolved = resolveComponentRegistryEntry(raw)
  if (!resolved) return raw
  return raw === resolved.entry.key ? `${resolved.entry.label} (${resolved.entry.key})` : `${resolved.entry.label} (${raw})`
}

export function isComponentKey(rawKey: string | null | undefined, canonicalKey: string): boolean {
  const resolved = resolveComponentRegistryEntry(rawKey)
  return resolved?.canonicalKey === canonicalKey
}

export function getComponentDefaultConfig(rawKey?: string | null): SectionConfigObject {
  const resolved = resolveComponentRegistryEntry(rawKey)
  return cloneConfig(resolved?.entry.defaultConfig || {}) || {}
}

export function getResolvedComponentConfig(
  rawKey?: string | null,
  config?: SectionConfigObject | null,
): SectionConfigObject {
  return {
    ...getComponentDefaultConfig(rawKey),
    ...(cloneConfig(config || {}) || {}),
  }
}

export function getComponentConfigSchema(rawKey?: string | null): ComponentRegistryConfigField[] {
  const resolved = resolveComponentRegistryEntry(rawKey)
  return cloneConfig(resolved?.entry.configSchema || []) || []
}
