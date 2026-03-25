import { describe, expect, it } from 'vitest'
import { componentRendererKeys, renderRegisteredComponent } from '@/lib/componentRenderers'
import {
  getRenderableComponentRegistryEntries,
  resolveComponentRegistryEntry,
} from '@/lib/componentRegistry'

describe('component registry', () => {
  it('keeps renderable registry keys aligned with renderer keys', () => {
    const registryKeys = getRenderableComponentRegistryEntries()
      .map((entry) => entry.key)
      .sort()

    expect(componentRendererKeys.slice().sort()).toEqual(registryKeys)
  })

  it('resolves aliases to canonical semantic keys', () => {
    expect(resolveComponentRegistryEntry('timeline')?.canonicalKey).toBe('timeline_header')
    expect(resolveComponentRegistryEntry('timeline-item')?.canonicalKey).toBe('timeline_item')
  })

  it('renders known renderable keys and ignores unmapped keys safely', () => {
    expect(renderRegisteredComponent('depot_map')).toBeTruthy()
    expect(renderRegisteredComponent('timeline-item')).toBeNull()
    expect(renderRegisteredComponent('totally_unknown_component')).toBeNull()
  })
})
