import registryData from '../../site/templates/component-registry.json'

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
