import Image from 'next/image'
import { CTA } from '@/components/CTA'
import { ContactForm } from '@/components/forms/ContactForm'
import { MembershipForm } from '@/components/forms/MembershipForm'
import { SubscribeForm } from '@/components/forms/SubscribeForm'
import { VisitDayForm } from '@/components/forms/VisitDayForm'
import { WaitingListForm } from '@/components/forms/WaitingListForm'
import { PricingCalculator } from '@/components/PricingCalculator'
import { EventsSection } from '@/components/EventsSection'
import { SchnuppertageSection } from '@/components/SchnuppertageSection'
import { DepotMap } from '@/components/DepotMap'
import { GeisshofMap } from '@/components/GeisshofMap'
import { Saisonkalender } from '@/components/Saisonkalender'
import { Gallery } from '@/components/Gallery'
import { EditableSection } from '@/components/sections/EditableSection'
import type { ContentSection, ContentMedia } from '@/lib/processwire-types'

interface SectionRendererProps {
  sections: ContentSection[]
  isEditing?: boolean
}

const componentMap: Record<string, React.ReactNode> = {
  contact_form: <ContactForm />,
  membership_form: <MembershipForm />,
  subscribe_form: <SubscribeForm />,
  visit_day_form: <VisitDayForm />,
  waiting_list_form: <WaitingListForm />,
  pricing_calculator: <PricingCalculator />,
  events_feed: <EventsSection />,
  schnuppertage: <SchnuppertageSection />,
  depot_map: <DepotMap />,
  geisshof_map: <GeisshofMap />,
  saisonkalender: <Saisonkalender />,
  gallery: <Gallery />,
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

function SectionHeader({ section }: { section: ContentSection }) {
  const headingAlreadyInText = hasHeadingHtml(section.text)
  return (
    <>
      {section.eyebrow ? <p className="cms-section-eyebrow">{section.eyebrow}</p> : null}
      {section.title && !headingAlreadyInText ? <h2>{section.title}</h2> : null}
    </>
  )
}

function SectionText({ section }: { section: ContentSection }) {
  if (!section.text) return null
  return <div className="cms-section-text" dangerouslySetInnerHTML={{ __html: section.text }} />
}

function SectionButtons({ section }: { section: ContentSection }) {
  if (!section.buttons || section.buttons.length === 0) return null
  return (
    <div className="cms-section-actions">
      {section.buttons.map((btn, i) => (
        <CTA key={`${section.id}-btn-${i}`} text={btn.text} href={btn.href} variant={btn.variant as 'primary' | 'secondary'} />
      ))}
    </div>
  )
}

function SplitSection({ section, mediaFirst }: { section: ContentSection; mediaFirst: boolean }) {
  const media = getSectionMedia(section)
  const overlayClass = section.imageOverlay && section.imageOverlay !== 'none' ? `image-overlay-${section.imageOverlay}` : ''
  return (
    <section className="cms-section cms-split">
      <div className={`cms-split-media ${mediaFirst ? 'is-first' : 'is-last'} ${overlayClass}`}>
        {media ? (
          <div className="cms-media-frame" style={getImageFilterStyle(section)}>
            <Image src={media.url} alt={media.alt || section.title} fill sizes="(max-width: 768px) 100vw, 50vw" style={{ objectFit: 'cover' }} />
          </div>
        ) : null}
      </div>
      <div className="cms-split-content">
        <SectionHeader section={section} />
        <SectionText section={section} />
        <SectionButtons section={section} />
      </div>
    </section>
  )
}

function BannerSection({ section }: { section: ContentSection }) {
  return (
    <section className="cms-section cms-banner">
      <div className="cms-banner-inner">
        <SectionHeader section={section} />
        <SectionText section={section} />
        <SectionButtons section={section} />
      </div>
    </section>
  )
}

function MediaGridSection({ section }: { section: ContentSection }) {
  const mediaItems = section.media || []
  return (
    <section className="cms-section cms-media-grid">
      <div className="cms-media-grid-text">
        <SectionHeader section={section} />
        <SectionText section={section} />
      </div>
      <div className="cms-media-grid-items">
        {mediaItems.map((item, index) => (
          <div key={`${section.id}-media-${index}`} className="cms-media-frame">
            <Image src={item.url} alt={item.alt || section.title} fill sizes="(max-width: 768px) 100vw, 33vw" style={{ objectFit: 'cover' }} />
          </div>
        ))}
      </div>
      <SectionButtons section={section} />
    </section>
  )
}

function VideoSection({ section }: { section: ContentSection }) {
  if (!section.video?.url) return null
  const embedUrl = getVideoEmbedUrl(section.video.url)
  const isFile = embedUrl.endsWith('.mp4') || embedUrl.endsWith('.webm')
  return (
    <section className="cms-section cms-video">
      <SectionHeader section={section} />
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
      <SectionText section={section} />
      <SectionButtons section={section} />
    </section>
  )
}

function RichTextSection({ section }: { section: ContentSection }) {
  return (
    <section className="cms-section cms-rich-text">
      <SectionHeader section={section} />
      <SectionText section={section} />
      <SectionButtons section={section} />
    </section>
  )
}

function ComponentSection({ section }: { section: ContentSection }) {
  const componentKey = section.component || ''
  const component = componentMap[componentKey] || null
  return (
    <section className="cms-section cms-component">
      <SectionHeader section={section} />
      <SectionText section={section} />
      {component}
      <SectionButtons section={section} />
    </section>
  )
}

export function SectionRenderer({ sections, isEditing = false }: SectionRendererProps) {
  function wrapEditable(section: ContentSection, content: React.ReactNode) {
    if (!isEditing) return content
    return (
      <EditableSection section={section} isEditing={isEditing}>
        {content}
      </EditableSection>
    )
  }

  return (
    <div className="cms-sections">
      {sections.map((section) => {
        const layout = section.layout || (section.image || section.media ? 'split_media_text' : 'rich_text')
        const theme = section.theme ? `cms-theme-${section.theme}` : 'cms-theme-default'
        const bgColor = section.bgColor && section.bgColor !== 'none' ? `bg-${section.bgColor}` : ''
        const wrapperClasses = [theme, bgColor].filter(Boolean).join(' ')
        let inner: React.ReactNode
        switch (layout) {
          case 'split_text_media':
            inner = <SplitSection section={section} mediaFirst={false} />
            break
          case 'full_width_banner':
            inner = <BannerSection section={section} />
            break
          case 'media_grid':
            inner = <MediaGridSection section={section} />
            break
          case 'video_embed':
            inner = <VideoSection section={section} />
            break
          case 'component':
            inner = <ComponentSection section={section} />
            break
          case 'split_media_text':
            inner = <SplitSection section={section} mediaFirst={true} />
            break
          case 'rich_text':
          default:
            inner = <RichTextSection section={section} />
            break
        }
        return (
          <div key={section.id} className={wrapperClasses}>
            {wrapEditable(section, inner)}
          </div>
        )
      })}
    </div>
  )
}
