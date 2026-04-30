import { describe, it, expect } from 'vitest'
import type { AktuellesItem } from '@/components/AktuellesData'
import { filterGeneralEvents, filterSchnuppertage, groupEventsByType } from '@/components/AktuellesData'

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
    const schnuppertage = filterSchnuppertage(events)
    expect(schnuppertage).toHaveLength(1)
    expect(schnuppertage[0].id).toBe(1)

    const other = filterGeneralEvents(events)
    expect(other).toHaveLength(2)
    expect(other.map((e: AktuellesItem) => e.id)).toEqual([2, 3])
  })

  it('preserves chronological input order inside each event section', () => {
    const chronologicallySortedEvents: AktuellesItem[] = [
      {
        id: 10,
        date: '10.05.2026',
        title: 'General Event Mai',
        description: 'A',
        type: 'event',
        eventType: 'general',
      },
      {
        id: 11,
        date: '20.05.2026',
        title: 'Schnuppertag Mai',
        description: 'B',
        type: 'event',
        eventType: 'schnuppertag',
      },
      {
        id: 12,
        date: '02.06.2026',
        title: 'General Event Juni',
        description: 'C',
        type: 'event',
        eventType: 'general',
      },
      {
        id: 13,
        date: '15.06.2026',
        title: 'Schnuppertag Juni',
        description: 'D',
        type: 'event',
        eventType: 'schnuppertag',
      },
    ]

    const grouped = groupEventsByType(chronologicallySortedEvents)

    expect(grouped.general.map((e) => e.id)).toEqual([10, 12])
    expect(grouped.schnuppertage.map((e) => e.id)).toEqual([11, 13])
  })
})
