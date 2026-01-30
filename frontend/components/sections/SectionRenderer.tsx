import Image from 'next/image'
import { CTA } from '@/components/CTA'
import { ContactForm } from '@/components/forms/ContactForm'
import { MembershipForm } from '@/components/forms/MembershipForm'
import { SubscribeForm } from '@/components/forms/SubscribeForm'
import { VisitDayForm } from '@/components/forms/VisitDayForm'
import { WaitingListForm } from '@/components/forms/WaitingListForm'
import { EventsSection } from '@/components/EventsSection'
import { SchnuppertageSection } from '@/components/SchnuppertageSection'
import { DepotMap } from '@/components/DepotMap'
import { Gallery } from '@/components/Gallery'
import type { ContentSection, ContentMedia } from '@/lib/processwire-types'

interface SectionRendererProps {
  sections: ContentSection[]
}

const componentMap: Record<string, React.ReactNode> = {
  contact_form: <ContactForm />,
  membership_form: <MembershipForm />,
  subscribe_form: <SubscribeForm />,
  visit_day_form: <VisitDayForm />,
  waiting_list_form: <WaitingListForm />,
  events_feed: <EventsSection />,
  schnuppertage: <SchnuppertageSection />,
  depot_map: <DepotMap />,
  gallery: <Gallery />,
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
  return (
    <>
      {section.eyebrow ? <p className="cms-section-eyebrow">{section.eyebrow}</p> : null}
      {section.title ? <h2>{section.title}</h2> : null}
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
  return (
    <section className="cms-section cms-split">
      <div className={`cms-split-media ${mediaFirst ? 'is-first' : 'is-last'}`}>
        {media ? (
          <div className="cms-media-frame">
            <Image src={media.url} alt={media.alt || section.title} fill style={{ objectFit: 'cover' }} />
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
            <Image src={item.url} alt={item.alt || section.title} fill style={{ objectFit: 'cover' }} />
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

export function SectionRenderer({ sections }: SectionRendererProps) {
  return (
    <div className="cms-sections">
      {sections.map((section) => {
        const layout = section.layout || (section.image || section.media ? 'split_media_text' : 'rich_text')
        const theme = section.theme ? `cms-theme-${section.theme}` : 'cms-theme-default'
        switch (layout) {
          case 'split_text_media':
            return (
              <div key={section.id} className={theme}>
                <SplitSection section={section} mediaFirst={false} />
              </div>
            )
          case 'full_width_banner':
            return (
              <div key={section.id} className={theme}>
                <BannerSection section={section} />
              </div>
            )
          case 'media_grid':
            return (
              <div key={section.id} className={theme}>
                <MediaGridSection section={section} />
              </div>
            )
          case 'video_embed':
            return (
              <div key={section.id} className={theme}>
                <VideoSection section={section} />
              </div>
            )
          case 'component':
            return (
              <div key={section.id} className={theme}>
                <ComponentSection section={section} />
              </div>
            )
          case 'split_media_text':
            return (
              <div key={section.id} className={theme}>
                <SplitSection section={section} mediaFirst={true} />
              </div>
            )
          case 'rich_text':
          default:
            return (
              <div key={section.id} className={theme}>
                <RichTextSection section={section} />
              </div>
            )
        }
      })}
    </div>
  )
}
