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
import { resolveComponentRegistryEntry } from '@/lib/componentRegistry'

export const componentRenderers = {
  contact_form: ContactForm,
  membership_form: MembershipForm,
  subscribe_form: SubscribeForm,
  visit_day_form: VisitDayForm,
  waiting_list_form: WaitingListForm,
  pricing_calculator: PricingCalculator,
  events_feed: EventsSection,
  schnuppertage: SchnuppertageSection,
  depot_map: DepotMap,
  geisshof_map: GeisshofMap,
  saisonkalender: Saisonkalender,
  gallery: Gallery,
} as const

export const componentRendererKeys = Object.keys(componentRenderers)

export function renderRegisteredComponent(rawKey?: string | null) {
  const resolved = resolveComponentRegistryEntry(rawKey)
  const Component = resolved && resolved.entry.kind === 'renderable'
    ? componentRenderers[resolved.canonicalKey as keyof typeof componentRenderers]
    : null

  return Component ? <Component /> : null
}
