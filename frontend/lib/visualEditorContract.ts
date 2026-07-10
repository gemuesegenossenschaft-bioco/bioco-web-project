import { getResolvedComponentConfig } from '@/lib/componentRegistry'
import type { ButtonVariant } from '@/components/ui/Button'
import type { ContentButton, ContentMedia, ContentSection, SectionConfigObject, SectionConfigValue } from '@/lib/processwire-types'

type DraftContentSection = ContentSection & {
  mediaItems?: ContentMedia[]
  draftMedia?: unknown
  draftMediaItems?: unknown[]
}

type SectionBgColor = NonNullable<ContentSection['bgColor']>
type SectionImageOverlay = NonNullable<ContentSection['imageOverlay']>

export interface VisualEditorFieldChange {
  field: string
  value: unknown
  buttonIndex?: number
  configKey?: string
}

export interface VisualEditorSavePayload {
  section_title: string
  section_text: string
  section_eyebrow: string
  section_layout: string
  section_theme: string
  section_bg_color: string
  section_image_overlay: string
  section_component: string
  image_alt: string
  section_video_url: string
  section_video_title: string
  section_image_brightness: number
  section_image_contrast: number
  section_image_saturate: number
  button_text: string
  button_href: string
  button_variant: string
  button2_text: string
  button2_href: string
  button2_variant: string
  section_config: SectionConfigObject
}

function clone<T>(value: T): T {
  if (value == null) return value
  return JSON.parse(JSON.stringify(value)) as T
}

function asString(value: unknown, fallback = ''): string {
  return String(value ?? fallback)
}

function asNumber(value: unknown, fallback: number): number {
  const next = Number(value)
  return Number.isFinite(next) ? next : fallback
}

function normalizeButtons(buttons: unknown): ContentButton[] {
  if (!Array.isArray(buttons)) return []
  return buttons
    .slice(0, 2)
    .map((button, index) => ({
      text: asString((button as Partial<ContentButton> | null)?.text),
      href: asString((button as Partial<ContentButton> | null)?.href),
      variant: asString((button as Partial<ContentButton> | null)?.variant, index === 0 ? 'primary' : 'secondary') as ButtonVariant,
    }))
    .filter((button) => button.text.trim() || button.href.trim())
}

function normalizeMedia(items: unknown): ContentMedia[] {
  if (!Array.isArray(items)) return []
  return items
    .map((item) => {
      const media = item as Partial<ContentMedia> | null
      if (!media?.url) return null
      return {
        url: asString(media.url),
        alt: asString(media.alt),
        type: (asString(media.type, 'image') || 'image') as ContentMedia['type'],
      }
    })
    .filter(Boolean) as ContentMedia[]
}

export function normalizeVisualEditorSection(section: Partial<ContentSection>): ContentSection {
  const component = asString(section.component)
  const normalized: ContentSection = {
    id: asString(section.id),
    title: asString(section.title),
    text: asString(section.text),
    layout: asString(section.layout, 'rich_text') || 'rich_text',
    theme: asString(section.theme, 'default') || 'default',
  }

  if (section.pwId) normalized.pwId = Number(section.pwId)
  if (typeof section.sort === 'number') normalized.sort = section.sort
  if (section.eyebrow) normalized.eyebrow = asString(section.eyebrow)
  if (component) normalized.component = component
  if (component || section.config) normalized.config = getResolvedComponentConfig(component, clone(section.config || {}))
  if (section.bgColor) normalized.bgColor = asString(section.bgColor) as SectionBgColor
  if (section.imageOverlay) normalized.imageOverlay = asString(section.imageOverlay) as SectionImageOverlay
  if (section.imageBrightness != null) normalized.imageBrightness = asNumber(section.imageBrightness, 1)
  if (section.imageContrast != null) normalized.imageContrast = asNumber(section.imageContrast, 1)
  if (section.imageSaturate != null) normalized.imageSaturate = asNumber(section.imageSaturate, 1)
  if (section.image) normalized.image = asString(section.image)
  if (section.imageAlt != null) normalized.imageAlt = asString(section.imageAlt)
  if (section.imageData) normalized.imageData = clone(section.imageData)
  if (section.video) normalized.video = { url: asString(section.video.url), title: asString(section.video.title) }

  const buttons = normalizeButtons(section.buttons)
  if (buttons.length) normalized.buttons = buttons

  const media = normalizeMedia(section.media)
  if (media.length) {
    normalized.media = media
    ;(normalized as DraftContentSection).mediaItems = clone(media)
    normalized.images = media
      .filter((item) => (item.type || 'image') === 'image')
      .map((item) => ({ url: item.url, alt: item.alt || '' }))
  }

  const draftSection = section as Partial<DraftContentSection>
  if (Array.isArray(draftSection.draftMediaItems)) (normalized as DraftContentSection).draftMediaItems = clone(draftSection.draftMediaItems)
  if (draftSection.draftMedia) (normalized as DraftContentSection).draftMedia = clone(draftSection.draftMedia)

  return normalized
}

function setButton(section: ContentSection, index: number, patch: Partial<ContentButton>): ContentSection {
  const buttons = [...(section.buttons || [])]
  while (buttons.length <= index) {
    buttons.push({ text: '', href: '', variant: buttons.length === 0 ? 'primary' : 'secondary' })
  }
  buttons[index] = { ...buttons[index], ...patch }
  return { ...section, buttons }
}

export function applyVisualEditorFieldChange(section: ContentSection, change: VisualEditorFieldChange): ContentSection {
  switch (change.field) {
    case 'title':
      return { ...section, title: asString(change.value) }
    case 'text':
      return { ...section, text: asString(change.value) }
    case 'eyebrow':
      return { ...section, eyebrow: asString(change.value) }
    case 'layout':
      return { ...section, layout: asString(change.value, 'rich_text') || 'rich_text' }
    case 'theme':
      return { ...section, theme: asString(change.value, 'default') || 'default' }
    case 'bgColor':
      return { ...section, bgColor: asString(change.value) as SectionBgColor }
    case 'imageOverlay':
      return { ...section, imageOverlay: asString(change.value) as SectionImageOverlay }
    case 'component': {
      const component = asString(change.value)
      return { ...section, component, config: getResolvedComponentConfig(component, section.config || {}) }
    }
    case 'config': {
      const base = getResolvedComponentConfig(section.component, section.config || {})
      const next: SectionConfigObject = change.configKey
        ? { ...base, [change.configKey]: change.value as SectionConfigValue }
        : clone((change.value || {}) as SectionConfigObject)
      return { ...section, config: getResolvedComponentConfig(section.component, next) }
    }
    case 'imageAlt':
      return { ...section, imageAlt: asString(change.value) }
    case 'videoUrl':
      return { ...section, video: { ...(section.video || { title: '' }), url: asString(change.value) } }
    case 'videoTitle':
      return { ...section, video: { ...(section.video || { url: '' }), title: asString(change.value) } }
    case 'mediaItems': {
      const media = normalizeMedia(change.value)
      const images = media
        .filter((item) => (item.type || 'image') === 'image')
        .map((item) => ({ url: item.url, alt: item.alt || '' }))
      return {
        ...section,
        media,
        images,
        image: images[0]?.url || '',
        imageAlt: images[0]?.alt || section.imageAlt || '',
        imageData: images[0] ? { url: images[0].url, description: images[0].alt || section.imageAlt || '' } : undefined,
      }
    }
    case 'buttons':
      return { ...section, buttons: normalizeButtons(change.value) }
    case 'video':
      return {
        ...section,
        video: change.value && typeof change.value === 'object'
          ? { url: asString((change.value as { url?: unknown }).url), title: asString((change.value as { title?: unknown }).title) }
          : null,
      }
    case 'media':
      return { ...section, media: normalizeMedia(change.value) }
    case 'images':
      return {
        ...section,
        images: Array.isArray(change.value)
          ? change.value
              .map((item) => {
                const image = item as { url?: unknown; alt?: unknown } | null
                return image?.url ? { url: asString(image.url), alt: asString(image.alt) } : null
              })
              .filter(Boolean) as ContentSection['images']
          : [],
      }
    case 'image':
      return { ...section, image: asString(change.value) }
    case 'imageBrightness':
      return { ...section, imageBrightness: asNumber(change.value, 1) }
    case 'imageContrast':
      return { ...section, imageContrast: asNumber(change.value, 1) }
    case 'imageSaturate':
      return { ...section, imageSaturate: asNumber(change.value, 1) }
    case 'button_text':
      return setButton(section, change.buttonIndex || 0, { text: asString(change.value) })
    case 'button_href':
      return setButton(section, change.buttonIndex || 0, { href: asString(change.value) })
    case 'button_variant':
      return setButton(section, change.buttonIndex || 0, { variant: asString(change.value, (change.buttonIndex || 0) === 0 ? 'primary' : 'secondary') as ButtonVariant })
    default:
      return section
  }
}

function getButton(section: ContentSection, index: number): ContentButton {
  return section.buttons?.[index] || { text: '', href: '', variant: index === 0 ? 'primary' : 'secondary' }
}

export function buildVisualEditorSavePayload(section: ContentSection): VisualEditorSavePayload {
  const button1 = getButton(section, 0)
  const button2 = getButton(section, 1)
  return {
    section_title: section.title || '',
    section_text: section.text || '',
    section_eyebrow: section.eyebrow || '',
    section_layout: section.layout || 'rich_text',
    section_theme: section.theme || 'default',
    section_bg_color: section.bgColor || 'none',
    section_image_overlay: section.imageOverlay || 'none',
    section_component: section.component || '',
    image_alt: section.imageAlt || '',
    section_video_url: section.video?.url || '',
    section_video_title: section.video?.title || '',
    section_image_brightness: section.imageBrightness ?? 1,
    section_image_contrast: section.imageContrast ?? 1,
    section_image_saturate: section.imageSaturate ?? 1,
    button_text: button1.text || '',
    button_href: button1.href || '',
    button_variant: button1.variant || 'primary',
    button2_text: button2.text || '',
    button2_href: button2.href || '',
    button2_variant: button2.variant || 'secondary',
    section_config: clone(section.config || {}),
  }
}
