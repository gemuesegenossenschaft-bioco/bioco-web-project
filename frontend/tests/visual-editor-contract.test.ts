import { describe, expect, it } from 'vitest'
import { getComponentRegistry } from '@/lib/componentRegistry'
import { getProcessWireFocusFields } from '@/lib/visualEditorProcessWire'
import type { ContentSection } from '@/lib/processwire-types'
import {
  applyVisualEditorFieldChange,
  buildVisualEditorSavePayload,
  normalizeVisualEditorSection,
} from '@/lib/visualEditorContract'

describe('visual editor section contract', () => {
  it('normalizes draft sections without dropping editable fields', () => {
    expect(normalizeVisualEditorSection({
      id: 'draft-1',
      title: 'Title',
      text: '<p>Text</p>',
      layout: '',
      component: 'media_text',
      config: { mediaSide: 'right' },
      buttons: [
        { text: 'One', href: '/one', variant: 'primary' },
        { text: 'Two', href: '/two', variant: 'secondary' },
        { text: 'Three', href: '/three', variant: 'secondary' },
      ],
      media: [{ url: '/a.jpg', alt: 'A', type: 'image' }],
      video: { url: 'https://example.com/v.mp4', title: 'Video' },
    })).toMatchObject({
      id: 'draft-1',
      title: 'Title',
      text: '<p>Text</p>',
      layout: 'rich_text',
      theme: 'default',
      component: 'media_text',
      buttons: [
        { text: 'One', href: '/one', variant: 'primary' },
        { text: 'Two', href: '/two', variant: 'secondary' },
      ],
      media: [{ url: '/a.jpg', alt: 'A', type: 'image' }],
      video: { url: 'https://example.com/v.mp4', title: 'Video' },
    })
  })

  it('applies field changes immutably for text, buttons, video, media, and config', () => {
    const section: ContentSection = {
      id: 'section-1',
      title: 'Old',
      text: '<p>Old</p>',
      layout: 'component',
      component: 'media_text',
      buttons: [{ text: 'Old', href: '/old', variant: 'primary' }],
      config: { mediaSide: 'left' },
    }

    const changed = applyVisualEditorFieldChange(section, { field: 'button_href', buttonIndex: 1, value: '/two' })
    const withVideo = applyVisualEditorFieldChange(changed, { field: 'videoTitle', value: 'Clip' })
    const withConfig = applyVisualEditorFieldChange(withVideo, { field: 'config', configKey: 'mediaSide', value: 'right' })
    const withMedia = applyVisualEditorFieldChange(withConfig, {
      field: 'mediaItems',
      value: [{ url: '/new.jpg', alt: 'Alt', type: 'image' }],
    })

    expect(section.buttons).toHaveLength(1)
    expect(withMedia.buttons?.[1]).toMatchObject({ href: '/two', variant: 'secondary' })
    expect(withMedia.video).toMatchObject({ title: 'Clip' })
    expect(withMedia.config).toMatchObject({ mediaSide: 'right' })
    expect(withMedia.image).toBe('/new.jpg')
    expect(withMedia.media).toEqual([{ url: '/new.jpg', alt: 'Alt', type: 'image' }])
  })

  it('builds complete ProcessWire save payload for each editable section field', () => {
    const payload = buildVisualEditorSavePayload({
      id: 'section-1',
      title: 'Title',
      text: '<p>Text</p>',
      eyebrow: 'Eye',
      layout: 'video_embed',
      theme: 'dark',
      bgColor: 'green',
      imageOverlay: 'dark',
      component: 'media_text',
      imageAlt: 'Alt',
      video: { url: 'https://example.com/v.mp4', title: 'Video' },
      buttons: [{ text: 'One', href: '/one', variant: 'primary' }, { text: 'Two', href: '/two', variant: 'secondary' }],
      config: { mediaSide: 'right' },
      imageBrightness: 1.2,
      imageContrast: 1.1,
      imageSaturate: 0.9,
    })

    expect(payload).toEqual(expect.objectContaining({
      section_title: 'Title',
      section_text: '<p>Text</p>',
      section_eyebrow: 'Eye',
      section_layout: 'video_embed',
      section_theme: 'dark',
      section_bg_color: 'green',
      section_image_overlay: 'dark',
      section_component: 'media_text',
      image_alt: 'Alt',
      section_video_url: 'https://example.com/v.mp4',
      section_video_title: 'Video',
      button_text: 'One',
      button2_text: 'Two',
      section_config: { mediaSide: 'right' },
      section_image_brightness: 1.2,
      section_image_contrast: 1.1,
      section_image_saturate: 0.9,
    }))
  })

  it('keeps every registry component focusable in ProcessWire', () => {
    for (const entry of getComponentRegistry()) {
      const section: ContentSection = {
        id: `section-${entry.key}`,
        pwId: 123,
        title: entry.label,
        text: '<p>Body</p>',
        layout: 'component',
        component: entry.key,
      }

      expect(getProcessWireFocusFields(section)).toEqual(expect.arrayContaining(entry.cmsFields))
    }
  })
})
