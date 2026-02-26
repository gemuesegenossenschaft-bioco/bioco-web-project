import { describe, it, expect, vi } from 'vitest'

// Mock fetch globally since the module uses it
vi.stubGlobal('fetch', vi.fn())

describe('AktuellesData', () => {
  it('mapEventFromApi maps eventType from API response', async () => {
    const apiEvent = {
      id: 42,
      title: 'Erntedankfest',
      description: 'Ein Fest',
      fullDescription: 'Ein tolles Fest',
      location: 'Geisshof',
      startDate: '2026-09-20T10:00:00+02:00',
      endDate: '2026-09-20T16:00:00+02:00',
      dateLabel: '20.09.2026',
      timeLabel: '10:00 - 16:00 Uhr',
      signupEnabled: true,
      signupNotes: '',
      status: 'upcoming' as const,
      media: [],
      url: '/events/erntedankfest/',
      parentTitle: 'Events',
      eventType: 'schnuppertag',
    }

    // fetchEventsFromCms calls fetch then maps via mapEventFromApi
    const mockResponse = {
      ok: true,
      json: async () => ({
        success: true,
        generatedAt: '2026-02-26T00:00:00Z',
        upcoming: [apiEvent],
        past: [],
      }),
    }
    vi.mocked(fetch).mockResolvedValueOnce(mockResponse as Response)

    const { fetchEventsFromCms } = await import('@/components/AktuellesData')
    const feed = await fetchEventsFromCms()

    expect(feed.upcoming[0]).toHaveProperty('eventType', 'schnuppertag')
  })

  it('mapEventFromApi defaults eventType to general when missing', async () => {
    const apiEvent = {
      id: 43,
      title: 'Generalversammlung',
      description: 'GV',
      status: 'upcoming' as const,
      media: [],
      // no eventType field
    }

    const mockResponse = {
      ok: true,
      json: async () => ({
        success: true,
        generatedAt: '2026-02-26T00:00:00Z',
        upcoming: [apiEvent],
        past: [],
      }),
    }
    vi.mocked(fetch).mockResolvedValueOnce(mockResponse as Response)

    const { fetchEventsFromCms } = await import('@/components/AktuellesData')
    const feed = await fetchEventsFromCms()

    expect(feed.upcoming[0]).toHaveProperty('eventType', 'general')
  })
})
