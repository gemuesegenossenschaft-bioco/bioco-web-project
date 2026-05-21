import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ContentSection } from '@/lib/processwire-types'

const cmsSectionsByPage = vi.hoisted(() => ({
  'standorte-depots': [
    { id: 'standorte-intro', title: 'CMS Standorte', text: '<p>Intro</p>', layout: 'rich_text' },
    { id: 'geisshof-map', title: 'Geisshof', text: '<p>Map</p>', layout: 'component', component: 'geisshof_map' },
    { id: 'depot-map', title: 'Depots', text: '<p>Map</p>', layout: 'component', component: 'depot_map' },
  ] satisfies ContentSection[],
  'bioco-werden': [
    { id: 'pricing', title: 'CMS Mitglied werden', text: '<p>Abo wählen</p>', layout: 'component', component: 'pricing_calculator' },
  ] satisfies ContentSection[],
}))

vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => <img data-testid="next-image" {...props} />,
}))
vi.mock('next/link', () => ({
  default: ({ href, children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => <a href={String(href)} {...props}>{children}</a>,
}))
vi.mock('next/navigation', () => ({
  useSearchParams: () => ({ get: (key: string) => (key === '_visual' ? '1' : null) }),
  usePathname: () => '/standorte-depots',
}))
vi.mock('@/lib/processwire', () => ({
  getPageSectionsWithSeo: vi.fn(async (pageName: string) => ({
    sections: cmsSectionsByPage[pageName as keyof typeof cmsSectionsByPage] || [],
    seo: null,
  })),
}))
vi.mock('@/components/Header', () => ({ Header: () => null }))
vi.mock('@/components/Footer', () => ({ Footer: () => null }))
vi.mock('@/components/UtilityNavigation', () => ({ UtilityNavigation: () => null }))
vi.mock('@/components/SecondaryNavigation', () => ({ PrimaryNavigation: () => null }))
vi.mock('@/components/MobileMenu', () => ({ MobileMenu: () => null }))
vi.mock('@/components/CTA', () => ({ CTA: ({ text }: { text: string }) => <span>{text}</span> }))
vi.mock('@/components/forms/ContactForm', () => ({ ContactForm: () => null }))
vi.mock('@/components/forms/MembershipForm', () => ({ MembershipForm: () => null }))
vi.mock('@/components/forms/SubscribeForm', () => ({ SubscribeForm: () => null }))
vi.mock('@/components/forms/VisitDayForm', () => ({ VisitDayForm: () => null }))
vi.mock('@/components/forms/WaitingListForm', () => ({ WaitingListForm: () => null }))
vi.mock('@/components/PricingCalculator', () => ({ PricingCalculator: () => <div data-testid="pricing-calculator" /> }))
vi.mock('@/components/EventsSection', () => ({ EventsSection: () => null }))
vi.mock('@/components/SchnuppertageSection', () => ({ SchnuppertageSection: () => null }))
vi.mock('@/components/DepotMap', () => ({ DepotMap: () => <div data-testid="depot-map" /> }))
vi.mock('@/components/GeisshofMap', () => ({ GeisshofMap: () => <div data-testid="geisshof-map" /> }))
vi.mock('@/components/Saisonkalender', () => ({ Saisonkalender: () => null }))
vi.mock('@/components/Gallery', () => ({ Gallery: () => null }))

describe('visual editor route availability', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders standorte-depots fallback content instead of CMS sections', async () => {
    const { default: StandortePage } = await import('@/app/standorte-depots/page')
    const element = await StandortePage()
    render(element)

    expect(screen.getByText('Unsere Standorte & Depots')).toBeInTheDocument()
    expect(screen.queryByText('CMS Standorte')).not.toBeInTheDocument()
    expect(screen.getByTestId('geisshof-map')).toBeInTheDocument()
    expect(screen.getByTestId('depot-map')).toBeInTheDocument()
  })

  it('renders bioco-werden fallback content instead of CMS sections', async () => {
    const { default: BiocoWerdenPage } = await import('@/app/bioco-werden/page')
    const element = await BiocoWerdenPage()
    render(element)

    expect(screen.getByText('biocò werden')).toBeInTheDocument()
    expect(screen.queryByText('CMS Mitglied werden')).not.toBeInTheDocument()
    expect(screen.getByTestId('pricing-calculator')).toBeInTheDocument()
  })
})
