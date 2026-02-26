import { describe, it, expect, vi } from 'vitest'
import type { AktuellesItem } from '@/components/AktuellesData'

// Mock React hooks to allow importing the client component
vi.mock('react', async () => {
  const actual = await vi.importActual('react')
  return { ...actual, useState: vi.fn((init: unknown) => [init, vi.fn()]) }
})
vi.mock('@/hooks/useEventsFeed', () => ({
  useEventsFeed: () => ({ upcoming: [], past: [], isLoading: false, error: null }),
}))
vi.mock('@/components/Header', () => ({ Header: () => null }))
vi.mock('@/components/Footer', () => ({ Footer: () => null }))
vi.mock('@/components/CTA', () => ({ CTA: () => null }))
vi.mock('@/components/AktuellesItem', () => ({ AktuellesItemComponent: () => null }))
vi.mock('@/components/ItemDetailModal', () => ({ ItemDetailModal: () => null }))
vi.mock('next/link', () => ({ default: () => null }))

describe('Event filtering by eventType', () => {
  const events: AktuellesItem[] = [
    {
      id: 1,
      date: '20.04.2026',
      title: 'Schnuppertag April',
      description: 'Lerne biocò kennen',
      type: 'event',
      eventType: 'schnuppertag',
    },
    {
      id: 2,
      date: '15.05.2026',
      title: 'Erntedankfest',
      description: 'Feier mit uns',
      type: 'event',
      eventType: 'general',
    },
    {
      id: 3,
      date: '01.06.2026',
      title: 'Workshop Schnuppertag-Vorbereitung',
      description: 'Vorbereitung',
      type: 'event',
      eventType: 'general', // NOT a Schnuppertag despite title
    },
  ]

  it('filters schnuppertage by eventType, not title', async () => {
    const { filterSchnuppertage, filterOtherEvents } = await import('@/components/AktuellesClient')

    const schnuppertage = filterSchnuppertage(events)
    expect(schnuppertage).toHaveLength(1)
    expect(schnuppertage[0].id).toBe(1)

    const other = filterOtherEvents(events)
    expect(other).toHaveLength(2)
    expect(other.map((e: AktuellesItem) => e.id)).toEqual([2, 3])
  })
})
