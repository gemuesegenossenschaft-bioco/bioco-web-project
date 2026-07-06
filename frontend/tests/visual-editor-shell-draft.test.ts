import { describe, expect, it } from 'vitest'
import {
  applyDraftMedia,
  appendDraftMedia,
  applyShellFieldChange,
  buildHomepageHeroSection,
  clearDraft,
  computeDirtyIds,
  draftStorageKey,
  hasDraftChanges,
  isHeroSection,
  normalizeShellSection,
  persistDraft,
  restoreDraft,
  sectionUpdateNotifications,
} from '../visual-editor-shell/draft'
import { STRINGS } from '../visual-editor-shell/strings'

// G.2 — the shell's draft model. Field-change semantics come from the shared
// frontend/lib/visualEditorContract.ts (no duplicated switch); this module
// adds draft-media bookkeeping, dirty tracking and the ONE persistence layer
// (sessionStorage, fingerprint-gated) that replaced the old three
// (sessionStorage + server draft + last-page localStorage).

function memoryStorage() {
  const map = new Map<string, string>()
  return {
    getItem: (k: string) => (map.has(k) ? map.get(k)! : null),
    setItem: (k: string, v: string) => void map.set(k, v),
    removeItem: (k: string) => void map.delete(k),
    size: () => map.size,
  }
}

const baseSection = {
  id: 's1',
  pwId: 11,
  title: 'Titel',
  text: '<p>Text</p>',
  layout: 'rich_text',
  theme: 'default',
}

describe('normalizeShellSection', () => {
  it('drops sections without id and keeps only complete draft media refs', () => {
    expect(normalizeShellSection({ title: 'kein id' })).toBeNull()
    const normalized = normalizeShellSection({
      ...baseSection,
      draftMedia: { assetId: 5, fileField: 'media_file', fileName: 'a.jpg', url: '/a.jpg' },
      draftMediaItems: [
        { assetId: 6, fileField: 'media_file', fileName: 'b.jpg', url: '/b.jpg' },
        { assetId: 0, url: '/broken.jpg' },
      ],
    })
    expect(normalized?.draftMedia).toMatchObject({ assetId: 5, targetField: 'section_image' })
    expect(normalized?.draftMediaItems).toHaveLength(1)
    expect(normalized?.draftMediaItems?.[0]).toMatchObject({ assetId: 6, targetField: 'section_images' })
  })

  it('identifies hero sections and builds the homepage hero pseudo-section', () => {
    const hero = buildHomepageHeroSection({ headline: 'Hi', subtitle: 'Sub' }, 1)
    expect(hero.id).toBe('__hero__')
    expect(hero.pwId).toBe(1)
    expect(hero.title).toBe('Hi')
    expect(isHeroSection(hero)).toBe(true)
    expect(isHeroSection('__hero__')).toBe(true)
    expect(isHeroSection(normalizeShellSection(baseSection)!)).toBe(false)
  })
})

describe('dirty tracking', () => {
  it('flags edited sections and detects draft changes', () => {
    const canonical = [normalizeShellSection(baseSection)!]
    const edited = [applyShellFieldChange(canonical[0], { field: 'title', value: 'Neu' })]
    expect(hasDraftChanges(canonical, canonical)).toBe(false)
    expect(hasDraftChanges(edited, canonical)).toBe(true)
    expect(computeDirtyIds(edited, canonical)).toEqual({ s1: true })
  })

  it('marks every section dirty when the order changed', () => {
    const a = normalizeShellSection({ ...baseSection, id: 'a' })!
    const b = normalizeShellSection({ ...baseSection, id: 'b' })!
    expect(computeDirtyIds([b, a], [a, b])).toEqual({ a: true, b: true })
  })
})

describe('applyShellFieldChange', () => {
  it('delegates to the shared visualEditorContract semantics', () => {
    const section = normalizeShellSection(baseSection)!
    expect(applyShellFieldChange(section, { field: 'eyebrow', value: 'Neu' }).eyebrow).toBe('Neu')
    const withButton = applyShellFieldChange(section, { field: 'button_text', value: 'Los', buttonIndex: 0 })
    expect(withButton.buttons?.[0]).toMatchObject({ text: 'Los', variant: 'primary' })
  })

  it('reconciles draftMediaItems when the media list changes', () => {
    const section = normalizeShellSection({
      ...baseSection,
      media: [
        { url: '/a.jpg', alt: '', type: 'image' },
        { url: '/b.jpg', alt: '', type: 'image' },
      ],
      draftMediaItems: [
        { assetId: 1, fileField: 'media_file', fileName: 'a.jpg', url: '/a.jpg' },
        { assetId: 2, fileField: 'media_file', fileName: 'b.jpg', url: '/b.jpg' },
      ],
    })!
    const next = applyShellFieldChange(section, {
      field: 'mediaItems',
      value: [{ url: '/b.jpg', alt: '', type: 'image' }],
    })
    expect(next.media).toHaveLength(1)
    expect(next.draftMediaItems).toHaveLength(1)
    expect(next.draftMediaItems?.[0]).toMatchObject({ assetId: 2 })
  })

  it('exposes the iframe echo notifications per changed field', () => {
    const section = normalizeShellSection({ ...baseSection, video: { url: '/v', title: 'V' } })!
    expect(sectionUpdateNotifications('title', section).map((n) => n.field)).toEqual(['title'])
    expect(sectionUpdateNotifications('component', section).map((n) => n.field)).toEqual(['component', 'config'])
    expect(sectionUpdateNotifications('videoTitle', section).map((n) => n.field)).toEqual(['video', 'videoTitle'])
    expect(sectionUpdateNotifications('mediaItems', section).map((n) => n.field)).toEqual(['media', 'images', 'image'])
    expect(sectionUpdateNotifications('button_text', section).map((n) => n.field)).toEqual(['buttons'])
  })
})

describe('draft media selection', () => {
  const file = { assetId: 9, assetTitle: 'Bild', fileField: 'media_file', fileName: 'c.jpg', url: '/c.jpg' }

  it('applyDraftMedia replaces the section image with a pending library ref', () => {
    const next = applyDraftMedia(normalizeShellSection(baseSection)!, file, 'section_image')
    expect(next.image).toBe('/c.jpg')
    expect(next.draftMedia).toMatchObject({ assetId: 9, targetField: 'section_image' })
    expect(next.media).toEqual([{ url: '/c.jpg', alt: next.imageAlt || '', type: 'image' }])
  })

  it('appendDraftMedia adds to a gallery without dropping existing media', () => {
    const withOne = applyDraftMedia(normalizeShellSection(baseSection)!, file, 'section_image')
    const next = appendDraftMedia(withOne, { ...file, assetId: 10, url: '/d.jpg', fileName: 'd.jpg' }, 'section_images')
    expect(next.media).toHaveLength(2)
    expect(next.draftMediaItems?.map((m) => m.assetId)).toContain(10)
  })
})

describe('single-layer draft persistence (sessionStorage)', () => {
  const canonical = [normalizeShellSection(baseSection)!]
  const edited = [applyShellFieldChange(canonical[0], { field: 'title', value: 'Neu' })]

  it('persists exactly one key per page and clears it when the draft is clean', () => {
    const storage = memoryStorage()
    persistDraft(storage, { pageId: 1, path: '/abos', baseFingerprint: 'fp', sections: edited })
    expect(storage.size()).toBe(1)
    expect(storage.getItem(draftStorageKey(1, '/abos'))).toBeTruthy()
    clearDraft(storage, 1, '/abos')
    expect(storage.size()).toBe(0)
  })

  it('restores a draft only when the fingerprint still matches', () => {
    const storage = memoryStorage()
    persistDraft(storage, { pageId: 1, path: '/abos', baseFingerprint: 'fp', sections: edited })

    const hit = restoreDraft(storage, { pageId: 1, path: '/abos', sections: canonical, fingerprint: 'fp' })
    expect(hit.restored).toBe(true)
    expect(hit.message).toBe(STRINGS.statusDraftRestored)
    expect(hit.sections[0].title).toBe('Neu')

    const stale = restoreDraft(storage, { pageId: 1, path: '/abos', sections: canonical, fingerprint: 'fp-2' })
    expect(stale.restored).toBe(false)
    expect(stale.message).toBe(STRINGS.statusStaleDraftDiscarded)
    expect(stale.sections[0].title).toBe('Titel')
    // stale draft is discarded from storage
    expect(storage.getItem(draftStorageKey(1, '/abos'))).toBeNull()
  })

  it('is a no-op restore when nothing is stored', () => {
    const storage = memoryStorage()
    const miss = restoreDraft(storage, { pageId: 1, path: '/abos', sections: canonical, fingerprint: 'fp' })
    expect(miss).toMatchObject({ restored: false, message: '' })
  })
})
