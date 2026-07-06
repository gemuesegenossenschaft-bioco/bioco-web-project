'use client'

import Image from 'next/image'
import { useMemo, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import { UtilityNavigation } from '@/components/UtilityNavigation'
import { PrimaryNavigation } from '@/components/SecondaryNavigation'
import { MobileMenu } from '@/components/MobileMenu'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import { ScrollToTopLink } from '@/components/ScrollToTopLink'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import { getVeFieldAttrs } from '@/components/visual-editor/fieldAttrs'
import { InlineVisualEditorRuntime } from '@/components/visual-editor/InlineVisualEditorRuntime'
import { SectionRenderer } from '@/components/sections/SectionRenderer'
import { useVisualEditor } from '@/hooks/useVisualEditor'
import type { ContentSection, HeroContent } from '@/lib/processwire-types'
import type { AktuellesItem } from '@/components/AktuellesData'
import { groupEventsByType } from '@/components/AktuellesData'

interface HomeClientProps {
  hero: HeroContent
  sections: ContentSection[]
  aktuellesItems: AktuellesItem[]
}

function sectionImage(s?: ContentSection): string | null {
  return s?.images?.[0]?.url || s?.imageData?.url || s?.image || null
}

function sectionImageAlt(s?: ContentSection): string {
  return s?.images?.[0]?.alt || s?.imageAlt || ''
}

function hasHeadingHtml(html?: string | null): boolean {
  return /<h[1-6]\b[^>]*>/i.test(String(html || ''))
}

const HERO_SECTION_ID = '__hero__'

export function HomeClient({ hero, sections, aktuellesItems }: HomeClientProps) {
  const searchParams = useSearchParams()
  const isVisualEditor = searchParams.get('_visual') === '1'
  const [selectedItem, setSelectedItem] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const initialSections = useMemo(() => {
    if (!isVisualEditor) return sections
    const heroSection: ContentSection = {
      id: HERO_SECTION_ID,
      title: hero.headline || 'Hero',
      text: '',
      eyebrow: hero.subtitle || '',
      layout: 'hero',
      image: hero.image,
      imageAlt: hero.imageAlt || '',
      theme: 'default',
    }
    return [heroSection, ...sections]
  }, [hero, isVisualEditor, sections])
  const { sections: liveSections, highlightedSectionId } = useVisualEditor({
    enabled: isVisualEditor,
    sections: initialSections,
  })

  const { upcoming: eventItems, isLoading: eventsLoading } = useEventsFeed()
  const eventGroups = groupEventsByType(eventItems)
  const visibleGeneralEventItems = eventGroups.general.slice(0, 3)
  const visibleSchnuppertagItems = eventGroups.schnuppertage.slice(0, 3)

  const handleItemClick = (item: AktuellesItem) => {
    setSelectedItem(item)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setSelectedItem(null)
  }

  // Get sections by ID with fallback
  const getSection = (id: string): ContentSection | undefined => {
    return liveSections.find(s => s.id === id)
  }

  const getVisualAttrs = (section?: ContentSection): Record<string, string> => {
    if (!isVisualEditor || !section) return {}
    return {
      'data-section-id': section.id,
      'data-ve-section-id': section.id,
      'data-section-layout': section.layout || 'rich_text',
    }
  }

  const willkommenSection = getSection('willkommen')
  const gemeinsamSection = getSection('gemeinsam')
  const kennenlernenSection = getSection('kennenlernen')
  const heroSection = getSection(HERO_SECTION_ID)
  const genericSections = liveSections.filter((section) => (
    section.id !== HERO_SECTION_ID &&
    section.id !== 'willkommen' &&
    section.id !== 'gemeinsam' &&
    section.id !== 'kennenlernen'
  ))
  const heroHeadline = heroSection?.title || hero.headline || ''
  const heroSubtitle = heroSection?.eyebrow || hero.subtitle || ''
  const displayHeroHeadline = heroHeadline || (isVisualEditor ? 'Hero Titel' : '')
  const displayHeroSubtitle = heroSubtitle || (isVisualEditor ? 'Hero Untertitel' : '')
  const heroImage = heroSection?.image || hero.image
  const heroImageAlt = heroSection?.imageAlt || hero.imageAlt || ''

  return (
    <div className="page-shell">
      <InlineVisualEditorRuntime enabled={isVisualEditor} sections={liveSections} />
      {isVisualEditor ? (
        <style dangerouslySetInnerHTML={{ __html: `
          [data-section-id] {
            position: relative;
            cursor: pointer;
            transition: outline 0.15s;
          }
          [data-section-id]:hover {
            outline: 2px dashed rgba(74, 124, 89, 0.6);
            outline-offset: -2px;
          }
          [data-section-id]:hover::after {
            content: attr(data-section-layout);
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(74, 124, 89, 0.9);
            color: #fff;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            z-index: 10;
            pointer-events: none;
          }
          ${highlightedSectionId ? `
          [data-section-id="${highlightedSectionId}"] {
            outline: 3px solid #4a7c59 !important;
            outline-offset: -3px !important;
          }
          ` : ''}
        ` }} />
      ) : null}

      {/* Utility Navigation - Above hero */}
      <div className="hero-utility-nav">
        <UtilityNavigation />
      </div>
      
      {/* Hero */}
      <section className="hero-bleed" {...getVisualAttrs(heroSection)}>
        {/* Navbar inside hero - becomes fixed when scrolled past */}
        <div className="navbar-overlay mobile-nav-shell">
          <PrimaryNavigation />
          <MobileMenu />
        </div>
        <div
          className="hero-bg"
          {...getVeFieldAttrs(isVisualEditor, HERO_SECTION_ID, 'media', 'media', false, { targetField: 'hero_image' })}
        >
          {heroImage && <Image
            src={heroImage}
            alt={heroImageAlt}
            fill
            priority
            sizes="100vw"
            style={{ objectFit: 'cover' }}
          />}
          <div className="hero-overlay" />
          <div className="hero-content">
            <h1 className="hero-headline" {...getVeFieldAttrs(isVisualEditor, HERO_SECTION_ID, 'title', 'text', true)}>
              {displayHeroHeadline.split('\n').map((line, i) => (
                <span key={i}>
                  {line}
                  {i < displayHeroHeadline.split('\n').length - 1 && <br />}
                </span>
              ))}
              {(heroSubtitle || isVisualEditor) && (
                <span
                  className="hero-title-secondary"
                  {...getVeFieldAttrs(isVisualEditor, HERO_SECTION_ID, 'eyebrow', 'text', true)}
                >
                  {displayHeroSubtitle.split('\n').map((line, i) => (
                    <span key={i}>
                      {line}
                      {i < displayHeroSubtitle.split('\n').length - 1 && <br />}
                    </span>
                  ))}
                </span>
              )}
            </h1>
          </div>
        </div>
      </section>

      {/* Main content */}
      <main className="home-container">
        {/* Willkommen - Row 1, Two Columns (CMS section 'willkommen') */}
        {willkommenSection && (
          <section className="two-column-section" {...getVisualAttrs(willkommenSection)}>
            <div className="two-column-text">
              {!hasHeadingHtml(willkommenSection.text) && (
                <h2 {...getVeFieldAttrs(isVisualEditor, 'willkommen', 'title', 'text', true)}>
                  {willkommenSection.title}
                </h2>
              )}
              {willkommenSection.text ? (
                <div
                  {...getVeFieldAttrs(isVisualEditor, 'willkommen', 'text', 'richtext', true)}
                  dangerouslySetInnerHTML={{ __html: willkommenSection.text }}
                />
              ) : null}
              {willkommenSection.buttons?.length ? (
                <div style={{ marginTop: '16px' }}>
                  {willkommenSection.buttons.map((btn, i) => (
                    <span key={i} {...getVeFieldAttrs(isVisualEditor, 'willkommen', 'button', 'button', true, { buttonIndex: i })}>
                      <CTA text={btn.text} href={btn.href} variant={btn.variant as 'primary' | 'secondary'} />
                    </span>
                  ))}
                </div>
              ) : null}
            </div>
            <div className="two-column-image" {...getVeFieldAttrs(isVisualEditor, 'willkommen', 'media', 'media', false, { targetField: 'section_image' })}>
              {sectionImage(willkommenSection) && <Image
                src={sectionImage(willkommenSection)!}
                alt={sectionImageAlt(willkommenSection)}
                fill
                sizes="(max-width: 768px) 100vw, 50vw"
                style={{ objectFit: 'cover', borderRadius: '24px' }}
              />}
            </div>
          </section>
        )}

        {/* Row 2, Two Columns (CMS section 'gemeinsam') */}
        {gemeinsamSection && (
          <section className="two-column-section" {...getVisualAttrs(gemeinsamSection)}>
            <div className="two-column-image" {...getVeFieldAttrs(isVisualEditor, 'gemeinsam', 'media', 'media', false, { targetField: 'section_image' })}>
              {sectionImage(gemeinsamSection) && <Image
                src={sectionImage(gemeinsamSection)!}
                alt={sectionImageAlt(gemeinsamSection)}
                fill
                sizes="(max-width: 768px) 100vw, 50vw"
                style={{ objectFit: 'cover', borderRadius: '24px' }}
              />}
            </div>
            <div className="two-column-text">
              {!hasHeadingHtml(gemeinsamSection.text) && (
                <h2 {...getVeFieldAttrs(isVisualEditor, 'gemeinsam', 'title', 'text', true)}>
                  {gemeinsamSection.title}
                </h2>
              )}
              {gemeinsamSection.text ? (
                <div
                  {...getVeFieldAttrs(isVisualEditor, 'gemeinsam', 'text', 'richtext', true)}
                  dangerouslySetInnerHTML={{ __html: gemeinsamSection.text }}
                />
              ) : null}
              {gemeinsamSection.buttons?.length ? (
                <div style={{ marginTop: '16px' }}>
                  {gemeinsamSection.buttons.map((btn, i) => (
                    <span key={i} {...getVeFieldAttrs(isVisualEditor, 'gemeinsam', 'button', 'button', true, { buttonIndex: i })}>
                      <CTA text={btn.text} href={btn.href} variant={btn.variant as 'primary' | 'secondary'} />
                    </span>
                  ))}
                </div>
              ) : null}
            </div>
          </section>
        )}

        <div className="home-grid-12">
          {/* Beiträge */}
          <section className="home-block col-span-12" style={{ marginTop: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Beiträge</h2>
            <div className="aktuelles-list">
              {aktuellesItems.slice(0, 3).map((item, index) => (
                <AktuellesItemComponent
                  key={item.id || index}
                  item={item}
                  variant="aktuelles"
                  onClick={handleItemClick}
                />
              ))}
            </div>
            <ScrollToTopLink href="/aktuelles" className="btn btn-primary btn-organic" style={{ marginTop: '16px', marginBottom: '16px', display: 'inline-block' }}>
              Alle Beiträge ansehen
            </ScrollToTopLink>
          </section>

          {/* Kommende Events */}
          <section className="home-block col-span-12" style={{ marginTop: 'clamp(24px, 4vw, 48px)' }}>
            <h2>Kommende Events</h2>
            {eventsLoading ? (
              <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
            ) : (
              <>
                {visibleGeneralEventItems.length > 0 ? (
                  <div className="events-list">
                    {visibleGeneralEventItems.map((item, index) => (
                      <AktuellesItemComponent
                        key={item.id || index}
                        item={item}
                        variant="event"
                        onClick={handleItemClick}
                      />
                    ))}
                  </div>
                ) : (
                  <p style={{ color: 'var(--text-secondary)' }}>Aktuell sind keine allgemeinen Events geplant.</p>
                )}
              </>
            )}
            <ScrollToTopLink
              href="/aktuelles"
              className="btn btn-primary btn-organic"
              style={{ marginTop: '16px', marginBottom: '16px', display: 'inline-block' }}
            >
              Alle Events ansehen
            </ScrollToTopLink>
          </section>

          <section className="home-block col-span-12" style={{ marginTop: 'clamp(24px, 4vw, 48px)' }}>
            <h2>Schnuppertage</h2>
            {eventsLoading ? (
              <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
            ) : (
              <>
                {visibleSchnuppertagItems.length > 0 ? (
                  <div className="events-list">
                    {visibleSchnuppertagItems.map((item, index) => (
                      <AktuellesItemComponent
                        key={item.id || index}
                        item={item}
                        variant="event"
                        onClick={handleItemClick}
                      />
                    ))}
                  </div>
                ) : (
                  <p style={{ color: 'var(--text-secondary)' }}>Aktuell sind keine Schnuppertage geplant.</p>
                )}
              </>
            )}
          </section>

          {/* CMS section 'kennenlernen' */}
          {kennenlernenSection && (
            <section
              className="home-block col-span-12"
              style={{ marginTop: 'clamp(24px, 4vw, 48px)' }}
              {...getVisualAttrs(kennenlernenSection)}
            >
              {!hasHeadingHtml(kennenlernenSection.text) && (
                <h2 {...getVeFieldAttrs(isVisualEditor, 'kennenlernen', 'title', 'text', true)}>
                  {kennenlernenSection.title}
                </h2>
              )}
              {kennenlernenSection.text ? (
                <div
                  {...getVeFieldAttrs(isVisualEditor, 'kennenlernen', 'text', 'richtext', true)}
                  dangerouslySetInnerHTML={{ __html: kennenlernenSection.text }}
                />
              ) : null}
              {kennenlernenSection.buttons?.length ? (
                <div className="cta-row">
                  {kennenlernenSection.buttons.map((btn, i) => (
                    <span key={i} {...getVeFieldAttrs(isVisualEditor, 'kennenlernen', 'button', 'button', true, { buttonIndex: i })}>
                      <CTA text={btn.text} href={btn.href} variant={btn.variant as 'primary' | 'secondary'} />
                    </span>
                  ))}
                </div>
              ) : null}
            </section>
          )}
        </div>
        {genericSections.length ? (
          <SectionRenderer
            sections={genericSections}
            visualEditor={isVisualEditor}
            pagePath="/"
          />
        ) : null}
      </main>

      <Footer />
      <ItemDetailModal item={selectedItem} isOpen={isModalOpen} onClose={handleCloseModal} />
    </div>
  )
}
