import Image from 'next/image'
import { CTA } from '@/components/CTA'
import { getVeFieldAttrs } from '@/components/visual-editor/fieldAttrs'
import { renderRegisteredComponent } from '@/lib/componentRenderers'
import type { ContentSection, ContentMedia } from '@/lib/processwire-types'

interface SectionRendererProps {
  sections: ContentSection[]
  isEditing?: boolean
  visualEditor?: boolean
}

function hasHeadingHtml(html?: string | null): boolean {
  return /<h[1-6]\b[^>]*>/i.test(String(html || ''))
}

function getImageFilterStyle(section: ContentSection): React.CSSProperties | undefined {
  const b = section.imageBrightness
  const c = section.imageContrast
  const s = section.imageSaturate
  if (b == null && c == null && s == null) return undefined
  const parts: string[] = []
  if (b != null && b !== 1) parts.push(`brightness(${b})`)
  if (c != null && c !== 1) parts.push(`contrast(${c})`)
  if (s != null && s !== 1) parts.push(`saturate(${s})`)
  if (parts.length === 0) return undefined
  return { filter: parts.join(' ') }
}

function getSectionMedia(section: ContentSection): ContentMedia | null {
  if (section.media && section.media.length > 0) {
    return section.media[0]
  }
  if (section.image) {
    return {
      url: section.image,
      alt: section.imageAlt || section.title,
      type: 'image',
    }
  }
  return null
}

function getVideoEmbedUrl(url: string): string {
  if (!url) return ''
  if (url.includes('youtube.com') || url.includes('youtu.be')) {
    let id: string | null = null
    if (url.includes('youtu.be')) {
      id = url.split('youtu.be/')[1]?.split('?')[0] || null
    } else {
      try {
        id = new URL(url).searchParams.get('v')
      } catch (error) {
        id = null
      }
    }
    return id ? `https://www.youtube.com/embed/${id}` : url
  }
  if (url.includes('vimeo.com')) {
    const id = url.split('vimeo.com/')[1]?.split('?')[0]
    return id ? `https://player.vimeo.com/video/${id}` : url
  }
  return url
}

function SectionHeader({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  const headingAlreadyInText = hasHeadingHtml(section.text)
  return (
    <>
      {section.eyebrow ? (
        <p className="cms-section-eyebrow" {...getVeFieldAttrs(visualEditor, section.id, 'eyebrow', 'text', true)}>
          {section.eyebrow}
        </p>
      ) : null}
      {section.title && !headingAlreadyInText ? (
        <h2 {...getVeFieldAttrs(visualEditor, section.id, 'title', 'text', true)}>{section.title}</h2>
      ) : null}
    </>
  )
}

function SectionText({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  if (!section.text) return null
  return (
    <div
      className="cms-section-text"
      {...getVeFieldAttrs(visualEditor, section.id, 'text', 'richtext', true)}
      dangerouslySetInnerHTML={{ __html: section.text }}
    />
  )
}

function SectionButtons({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  if (!section.buttons || section.buttons.length === 0) return null
  return (
    <div className="cms-section-actions">
      {section.buttons.map((btn, i) => (
        <span key={`${section.id}-btn-${i}`} {...getVeFieldAttrs(visualEditor, section.id, 'button', 'button', true, { buttonIndex: i })}>
          <CTA text={btn.text} href={btn.href} variant={btn.variant as 'primary' | 'secondary'} />
        </span>
      ))}
    </div>
  )
}

function SplitSection({ section, mediaFirst, visualEditor }: { section: ContentSection; mediaFirst: boolean; visualEditor: boolean }) {
  const media = getSectionMedia(section)
  const overlayClass = section.imageOverlay && section.imageOverlay !== 'none' ? `image-overlay-${section.imageOverlay}` : ''
  return (
    <section className="cms-section cms-split">
      <div
        className={`cms-split-media ${mediaFirst ? 'is-first' : 'is-last'} ${overlayClass}`}
        {...getVeFieldAttrs(visualEditor, section.id, 'media', 'media', false, { targetField: 'section_image' })}
      >
        {media ? (
          <div className="cms-media-frame" style={getImageFilterStyle(section)}>
            <Image src={media.url} alt={media.alt || section.title} fill sizes="(max-width: 768px) 100vw, 50vw" style={{ objectFit: 'cover' }} />
          </div>
        ) : null}
      </div>
      <div className="cms-split-content">
        <SectionHeader section={section} visualEditor={visualEditor} />
        <SectionText section={section} visualEditor={visualEditor} />
        <SectionButtons section={section} visualEditor={visualEditor} />
      </div>
    </section>
  )
}

function BannerSection({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  return (
    <section className="cms-section cms-banner">
      <div className="cms-banner-inner">
        <SectionHeader section={section} visualEditor={visualEditor} />
        <SectionText section={section} visualEditor={visualEditor} />
        <SectionButtons section={section} visualEditor={visualEditor} />
      </div>
    </section>
  )
}

function MediaGridSection({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  const mediaItems = section.media || []
  return (
    <section className="cms-section cms-media-grid">
      <div className="cms-media-grid-text">
        <SectionHeader section={section} visualEditor={visualEditor} />
        <SectionText section={section} visualEditor={visualEditor} />
      </div>
      <div className="cms-media-grid-items">
        {mediaItems.map((item, index) => (
          <div
            key={`${section.id}-media-${index}`}
            className="cms-media-frame"
            {...getVeFieldAttrs(visualEditor, section.id, 'media', 'media', false, { targetField: 'section_images' })}
          >
            <Image src={item.url} alt={item.alt || section.title} fill sizes="(max-width: 768px) 100vw, 33vw" style={{ objectFit: 'cover' }} />
          </div>
        ))}
      </div>
      <SectionButtons section={section} visualEditor={visualEditor} />
    </section>
  )
}

function VideoSection({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  if (!section.video?.url) return null
  const embedUrl = getVideoEmbedUrl(section.video.url)
  const isFile = embedUrl.endsWith('.mp4') || embedUrl.endsWith('.webm')
  return (
    <section className="cms-section cms-video">
      <SectionHeader section={section} visualEditor={visualEditor} />
      {section.video.title ? <p className="cms-section-caption">{section.video.title}</p> : null}
      <div className="cms-video-frame">
        {isFile ? (
          <video controls preload="metadata">
            <source src={embedUrl} />
          </video>
        ) : (
          <iframe src={embedUrl} title={section.video.title || section.title} allow="autoplay; encrypted-media" allowFullScreen />
        )}
      </div>
      <SectionText section={section} visualEditor={visualEditor} />
      <SectionButtons section={section} visualEditor={visualEditor} />
    </section>
  )
}

function RichTextSection({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  return (
    <section className="cms-section cms-rich-text">
      <SectionHeader section={section} visualEditor={visualEditor} />
      <SectionText section={section} visualEditor={visualEditor} />
      <SectionButtons section={section} visualEditor={visualEditor} />
    </section>
  )
}

function ComponentSection({ section, visualEditor }: { section: ContentSection; visualEditor: boolean }) {
  const component = renderRegisteredComponent(section.component)
  return (
    <section className="cms-section cms-component">
      <SectionHeader section={section} visualEditor={visualEditor} />
      <SectionText section={section} visualEditor={visualEditor} />
      <div {...getVeFieldAttrs(visualEditor, section.id, 'component', 'structured', false)}>
        {component}
      </div>
      <SectionButtons section={section} visualEditor={visualEditor} />
    </section>
  )
}

export function SectionRenderer({ sections, isEditing = false, visualEditor = false }: SectionRendererProps) {
  function resolveLayout(section: ContentSection): string {
    return section.layout || (section.image || section.media ? 'split_media_text' : 'rich_text')
  }

  return (
    <div className="cms-sections">
      {sections.map((section) => {
        const layout = resolveLayout(section)
        const theme = section.theme ? `cms-theme-${section.theme}` : 'cms-theme-default'
        const bgColor = section.bgColor && section.bgColor !== 'none' ? `bg-${section.bgColor}` : ''
        const wrapperClasses = [theme, bgColor].filter(Boolean).join(' ')
        let inner: React.ReactNode
        switch (layout) {
          case 'split_text_media':
            inner = <SplitSection section={section} mediaFirst={false} visualEditor={visualEditor} />
            break
          case 'full_width_banner':
            inner = <BannerSection section={section} visualEditor={visualEditor} />
            break
          case 'media_grid':
            inner = <MediaGridSection section={section} visualEditor={visualEditor} />
            break
          case 'video_embed':
            inner = <VideoSection section={section} visualEditor={visualEditor} />
            break
          case 'component':
            inner = <ComponentSection section={section} visualEditor={visualEditor} />
            break
          case 'split_media_text':
            inner = <SplitSection section={section} mediaFirst={true} visualEditor={visualEditor} />
            break
          case 'rich_text':
          default:
            inner = <RichTextSection section={section} visualEditor={visualEditor} />
            break
        }

        const veAttrs = visualEditor ? {
          'data-section-id': section.id,
          'data-ve-section-id': section.id,
          'data-section-layout': layout,
        } : {}

        return (
          <div key={section.id} className={wrapperClasses} {...veAttrs}>
            {inner}
          </div>
        )
      })}
    </div>
  )
}
