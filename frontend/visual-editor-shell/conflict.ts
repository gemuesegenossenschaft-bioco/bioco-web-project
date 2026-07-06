/**
 * Three-way merge for publish conflicts (content-publish 409): base is the
 * canonical snapshot taken at load, server is the canonical state from the
 * conflict response, local is the draft. Straight port of the old shell's
 * resolvePublishConflict with the window.confirm prompts replaced by
 * injectable deciders (main.ts wires them to window.confirm with the exact
 * old German copy).
 */

import { cloneShellSections, normalizeShellSection, type ShellSection } from './draft'

type UnknownRecord = Record<string, unknown>

export interface ConflictDeciders {
  /** true = keep the local value, false = adopt the server value. */
  keepLocalField(sectionId: string, field: string): boolean
  /** true = keep the local section order, false = adopt the server order. */
  keepLocalOrder(): boolean
}

export interface FieldConflict {
  sectionId: string
  field: string
  keep: 'local' | 'server'
}

export interface ConflictResolution {
  mergedSections: ShellSection[]
  conflicts: FieldConflict[]
  keepLocalOrder: boolean
}

function clone<T>(value: T): T {
  if (value == null) return value
  return JSON.parse(JSON.stringify(value)) as T
}

function valueEquals(a: unknown, b: unknown): boolean {
  return JSON.stringify(a == null ? null : a) === JSON.stringify(b == null ? null : b)
}

function indexById(sections: readonly ShellSection[]): Record<string, UnknownRecord> {
  const map: Record<string, UnknownRecord> = {}
  for (const section of sections || []) {
    if (!section || !section.id) continue
    map[section.id] = clone(section) as unknown as UnknownRecord
  }
  return map
}

export function resolvePublishConflict(
  baseSections: readonly ShellSection[],
  serverSections: readonly ShellSection[],
  localSections: readonly ShellSection[],
  deciders: ConflictDeciders
): ConflictResolution {
  const baseById = indexById(baseSections)
  const serverById = indexById(serverSections)
  const localById = indexById(localSections)
  const ids = new Set<string>([...Object.keys(baseById), ...Object.keys(serverById), ...Object.keys(localById)])
  const mergedById: Record<string, ShellSection> = {}
  const conflicts: FieldConflict[] = []

  for (const id of Array.from(ids)) {
    const base = baseById[id] || null
    const server = serverById[id] || null
    const local = localById[id] || null

    if (!server && local) {
      mergedById[id] = clone(local) as unknown as ShellSection
      continue
    }
    if (server && !local) {
      mergedById[id] = clone(server) as unknown as ShellSection
      continue
    }
    if (!server && !local) continue

    const merged = clone(local) as UnknownRecord
    const keys = new Set<string>([
      ...Object.keys(base || {}),
      ...Object.keys(server || {}),
      ...Object.keys(local || {}),
    ])

    for (const key of Array.from(keys)) {
      const baseVal = base ? base[key] : undefined
      const serverVal = server ? server[key] : undefined
      const localVal = local ? local[key] : undefined
      if (valueEquals(localVal, serverVal)) {
        merged[key] = clone(localVal)
        continue
      }
      if (valueEquals(baseVal, serverVal)) {
        // only local changed
        merged[key] = clone(localVal)
        continue
      }
      if (valueEquals(baseVal, localVal)) {
        // only server changed
        merged[key] = clone(serverVal)
        continue
      }
      const useLocal = deciders.keepLocalField(id, key)
      merged[key] = useLocal ? clone(localVal) : clone(serverVal)
      conflicts.push({ sectionId: id, field: key, keep: useLocal ? 'local' : 'server' })
    }

    const normalized = normalizeShellSection(merged)
    if (normalized) mergedById[id] = normalized
  }

  const baseOrder = (baseSections || []).map((s) => s.id).join('|')
  const serverOrderList = (serverSections || []).map((s) => s.id)
  const localOrderList = (localSections || []).map((s) => s.id)
  const serverOrder = serverOrderList.join('|')
  const localOrder = localOrderList.join('|')

  let keepLocalOrder = true
  if (baseOrder !== serverOrder && baseOrder !== localOrder && serverOrder !== localOrder) {
    keepLocalOrder = deciders.keepLocalOrder()
  } else if (baseOrder === localOrder && baseOrder !== serverOrder) {
    keepLocalOrder = false
  }

  const orderedIds = keepLocalOrder ? [...localOrderList] : [...serverOrderList]
  for (const id of Object.keys(mergedById)) {
    if (!orderedIds.includes(id)) orderedIds.push(id)
  }

  const mergedSections = orderedIds
    .map((id) => mergedById[id])
    .filter((section): section is ShellSection => Boolean(section))

  return {
    mergedSections: cloneShellSections(mergedSections),
    conflicts,
    keepLocalOrder,
  }
}
