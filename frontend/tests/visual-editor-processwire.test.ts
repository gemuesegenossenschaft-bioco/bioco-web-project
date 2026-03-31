import { describe, expect, it } from 'vitest'
import { getProcessWireFocusFields, serializeProcessWireFocusFields } from '@/lib/visualEditorProcessWire'
import type { ContentSection } from '@/lib/processwire-types'

describe('visual editor -> ProcessWire focus mapping', () => {
  it('expands section-level component focus to registry cms fields', () => {
    const section: ContentSection = {
      id: 'section-1',
      pwId: 123,
      title: 'Media Text',
      text: '<p>Body</p>',
      layout: 'component',
      component: 'media_text',
    }

    expect(getProcessWireFocusFields(section)).toEqual([
      'section_component',
      'section_title',
      'section_eyebrow',
      'section_text',
      'section_image',
      'section_images',
      'image_alt',
      'button_text',
      'button_href',
      'button_variant',
      'button2_text',
      'button2_href',
      'button2_variant',
      'section_config',
    ])
  })

  it('maps second button focus to button2 ProcessWire fields', () => {
    const section: ContentSection = {
      id: 'section-1',
      pwId: 123,
      title: 'CTA',
      text: '<p>Body</p>',
      layout: 'rich_text',
      buttons: [
        { text: 'One', href: '/one', variant: 'primary' },
        { text: 'Two', href: '/two', variant: 'secondary' },
      ],
    }

    expect(getProcessWireFocusFields(section, { field: 'button', buttonIndex: 1 })).toEqual([
      'button2_text',
      'button2_href',
      'button2_variant',
    ])
  })

  it('maps media focus to target field plus alt text', () => {
    const section: ContentSection = {
      id: 'section-1',
      pwId: 123,
      title: 'Gallery',
      text: '<p>Body</p>',
      layout: 'media_grid',
    }

    expect(getProcessWireFocusFields(section, { field: 'media', targetField: 'section_images' })).toEqual([
      'section_images',
      'image_alt',
    ])
  })

  it('maps hero media focus to page-level hero fields', () => {
    const section: ContentSection = {
      id: '__hero__',
      pwId: 88,
      title: 'Hero',
      text: '',
      layout: 'hero',
    }

    expect(getProcessWireFocusFields(section, { field: 'media', targetField: 'hero_image' })).toEqual([
      'hero_image',
      'image_alt',
    ])
  })

  it('serializes unique focus fields for query params', () => {
    expect(serializeProcessWireFocusFields(['section_title', 'section_text', 'section_title'])).toBe('section_title,section_text')
  })
})
