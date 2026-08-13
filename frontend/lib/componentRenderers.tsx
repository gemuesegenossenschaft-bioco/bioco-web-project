import { ContactForm } from '@/components/forms/ContactForm'
import { MembershipForm } from '@/components/forms/MembershipForm'
import { SubscribeForm } from '@/components/forms/SubscribeForm'
import { VisitDayForm } from '@/components/forms/VisitDayForm'
import { WaitingListForm } from '@/components/forms/WaitingListForm'
import { PricingCalculator } from '@/components/PricingCalculator'
import { EventsBanner } from '@/components/EventsBanner'
import { EventsSection } from '@/components/EventsSection'
import { SchnuppertageSection } from '@/components/SchnuppertageSection'
import { GroupCardsSection } from '@/components/GroupCardsSection'
import { DepotMap } from '@/components/DepotMap'
import { GeisshofMap } from '@/components/GeisshofMap'
import { Saisonkalender } from '@/components/Saisonkalender'
import { Gallery } from '@/components/Gallery'
import type { ReactNode } from 'react'
import {
  AccordionItemBlock,
  CardsGridBlock,
  CtaBandBlock,
  GalleryStripBlock,
  LinkTilesBlock,
  MediaTextBlock,
  PageIntroBlock,
  PricingTableBlock,
  StepsBlock,
  TextColumnsBlock,
  TimelineHeaderBlock,
  TimelineItemBlock,
} from '@/components/sections/RegisteredSectionComponents'
import { getResolvedComponentConfig, resolveComponentRegistryEntry } from '@/lib/componentRegistry'
import type { ContentSection } from '@/lib/processwire-types'

type ComponentRenderer = (section: ContentSection, visualEditor?: boolean) => ReactNode

const layoutOwnedKeys = new Set([
  'page_intro',
  'media_text',
  'cards_grid',
  'gallery_strip',
  'text_columns',
  'timeline_header',
  'timeline_item',
  'cta_band',
  'pricing_table',
  'accordion_item',
  'steps',
  'link_tiles',
  'schnuppertage',
])

export const componentRenderers: Record<string, ComponentRenderer> = {
  contact_form: () => <ContactForm />,
  membership_form: () => <MembershipForm />,
  subscribe_form: () => <SubscribeForm />,
  visit_day_form: () => <VisitDayForm />,
  waiting_list_form: () => <WaitingListForm />,
  pricing_calculator: () => <PricingCalculator />,
  events_feed: (section) => {
    // config.variant 'banner' preserves the former hardcoded /kundenportal
    // rendering (EventsBanner); default 'standard' keeps EventsSection.
    const config = getResolvedComponentConfig(section.component, section.config)
    const archiveUrl = String(config.archiveUrl || '')
    if (String(config.variant || 'standard') === 'banner') {
      const limit = Number(config.limit)
      return <EventsBanner
        limit={Number.isFinite(limit) && limit > 0 ? limit : 3}
        archiveUrl={archiveUrl}
        title={String(config.title || '')}
        archiveLabel={String(config.archiveLabel || '')}
        loadingMessage={String(config.loadingMessage || '')}
        emptyMessage={String(config.emptyMessage || '')}
      />
    }
    return <EventsSection
      archiveUrl={archiveUrl}
      upcomingTitle={String(config.upcomingTitle || '')}
      archiveLabel={String(config.archiveLabel || '')}
      loadingMessage={String(config.loadingMessage || '')}
      emptyMessage={String(config.emptyMessage || '')}
      pastTitle={String(config.pastTitle || '')}
      pastDescription={String(config.pastDescription || '')}
      pastLinkLabel={String(config.pastLinkLabel || '')}
    />
  },
  schnuppertage: (section, visualEditor) => <SchnuppertageSection section={section} visualEditor={visualEditor} />,
  group_cards: () => <GroupCardsSection />,
  depot_map: () => <DepotMap />,
  geisshof_map: () => <GeisshofMap />,
  saisonkalender: () => <Saisonkalender />,
  gallery: () => <Gallery />,
  page_intro: (section, visualEditor) => <PageIntroBlock section={section} visualEditor={visualEditor} />,
  pricing_table: (section, visualEditor) => <PricingTableBlock section={section} visualEditor={visualEditor} />,
  media_text: (section, visualEditor) => <MediaTextBlock section={section} visualEditor={visualEditor} />,
  cards_grid: (section, visualEditor) => <CardsGridBlock section={section} visualEditor={visualEditor} />,
  gallery_strip: (section, visualEditor) => <GalleryStripBlock section={section} visualEditor={visualEditor} />,
  text_columns: (section, visualEditor) => <TextColumnsBlock section={section} visualEditor={visualEditor} />,
  timeline_header: (section, visualEditor) => <TimelineHeaderBlock section={section} visualEditor={visualEditor} />,
  timeline_item: (section, visualEditor) => <TimelineItemBlock section={section} visualEditor={visualEditor} />,
  cta_band: (section, visualEditor) => <CtaBandBlock section={section} visualEditor={visualEditor} />,
  accordion_item: (section, visualEditor) => <AccordionItemBlock section={section} visualEditor={visualEditor} />,
  steps: (section, visualEditor) => <StepsBlock section={section} visualEditor={visualEditor} />,
  link_tiles: (section, visualEditor) => <LinkTilesBlock section={section} visualEditor={visualEditor} />,
} as const

export const componentRendererKeys = Object.keys(componentRenderers)

export function renderRegisteredComponent(section: ContentSection, visualEditor = false) {
  const rawKey = section.component
  const resolved = resolveComponentRegistryEntry(rawKey)
  const Component = resolved && resolved.entry.kind === 'renderable'
    ? componentRenderers[resolved.canonicalKey as keyof typeof componentRenderers]
    : null

  return {
    node: Component ? Component(section, visualEditor) : null,
    ownsLayout: !!(resolved && layoutOwnedKeys.has(resolved.canonicalKey)),
  }
}
