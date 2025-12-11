import { SITE_URL } from '@/lib/cmsClient'

// Shared data source for Aktuelles and Events
// Structure designed to be easily replaceable with ProcessWire API data
export type EventStatus = 'upcoming' | 'past'

export interface EventMediaItem {
  url: string
  type: 'image' | 'video'
  description?: string
}

export interface AktuellesItem {
  id?: string | number // For ProcessWire: page ID
  date: string
  title: string
  description: string
  type: 'aktuelles' | 'event'
  // Extended fields for detail view
  fullDescription?: string
  location?: string
  time?: string
  timeLabel?: string
  startDate?: string
  endDate?: string
  status?: EventStatus
  signupRequired?: boolean
  signupEnabled?: boolean
  signupUrl?: string
  signupNotes?: string
  imageUrl?: string
  media?: EventMediaItem[]
  // ProcessWire fields (for future API integration)
  body?: string // Full content from ProcessWire
  url?: string // ProcessWire page URL
  parentTitle?: string
}

interface EventsApiEvent {
  id: number
  title: string
  description: string
  fullDescription?: string
  location?: string
  startDate?: string
  endDate?: string
  dateLabel?: string
  timeLabel?: string
  signupEnabled?: boolean
  signupNotes?: string
  status?: EventStatus
  media?: EventMediaItem[]
  url?: string
  parentTitle?: string
}

interface EventsApiResponse {
  success: boolean
  generatedAt?: string
  upcoming: EventsApiEvent[]
  past: EventsApiEvent[]
}

export interface EventsFeed {
  upcoming: AktuellesItem[]
  past: AktuellesItem[]
  generatedAt?: string
}

export const aktuellesData: AktuellesItem[] = [
  {
    id: 3,
    date: '27. November 2025',
    title: 'Außerordentliche Generalversammlung',
    description: 'Wichtige Informationen für alle Genossenschafter/innen',
    type: 'aktuelles',
    fullDescription: 'Wir laden alle Genossenschafter/innen zur außerordentlichen Generalversammlung ein. Es werden wichtige Themen besprochen, die die Zukunft der Genossenschaft betreffen. Deine Teilnahme ist wichtig!',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '19:00 Uhr',
    signupRequired: false
  },
  // Schnuppertage 2026
  {
    id: 4,
    date: '28.04.2026',
    title: 'Schnuppertag April',
    description: 'Lerne biocò und den Geisshof kennen',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    timeLabel: '14:00 - 17:00 Uhr',
    startDate: '2026-04-28T14:00:00+02:00',
    endDate: '2026-04-28T17:00:00+02:00',
    status: 'upcoming',
    signupRequired: true,
    signupEnabled: true
  },
  {
    id: 5,
    date: '29.05.2026',
    title: 'Schnuppertag Mai',
    description: 'Lerne biocò und den Geisshof kennen',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    timeLabel: '14:00 - 17:00 Uhr',
    startDate: '2026-05-29T14:00:00+02:00',
    endDate: '2026-05-29T17:00:00+02:00',
    status: 'upcoming',
    signupRequired: true,
    signupEnabled: true
  },
  {
    id: 6,
    date: '26.06.2026',
    title: 'Schnuppertag Juni',
    description: 'Lerne biocò und den Geisshof kennen',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    timeLabel: '14:00 - 17:00 Uhr',
    startDate: '2026-06-26T14:00:00+02:00',
    endDate: '2026-06-26T17:00:00+02:00',
    status: 'upcoming',
    signupRequired: true,
    signupEnabled: true
  },
  {
    id: 7,
    date: '31.07.2026',
    title: 'Schnuppertag Juli',
    description: 'Lerne biocò und den Geisshof kennen',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    timeLabel: '14:00 - 17:00 Uhr',
    startDate: '2026-07-31T14:00:00+02:00',
    endDate: '2026-07-31T17:00:00+02:00',
    status: 'upcoming',
    signupRequired: true,
    signupEnabled: true
  },
  {
    id: 8,
    date: '28.08.2026',
    title: 'Schnuppertag August',
    description: 'Lerne biocò und den Geisshof kennen',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    timeLabel: '14:00 - 17:00 Uhr',
    startDate: '2026-08-28T14:00:00+02:00',
    endDate: '2026-08-28T17:00:00+02:00',
    status: 'upcoming',
    signupRequired: true,
    signupEnabled: true
  },
  {
    id: 9,
    date: '25.09.2026',
    title: 'Schnuppertag September',
    description: 'Lerne biocò und den Geisshof kennen',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    timeLabel: '14:00 - 17:00 Uhr',
    startDate: '2026-09-25T14:00:00+02:00',
    endDate: '2026-09-25T17:00:00+02:00',
    status: 'upcoming',
    signupRequired: true,
    signupEnabled: true
  },
  {
    id: 10,
    date: '30.10.2026',
    title: 'Schnuppertag Oktober',
    description: 'Lerne biocò und den Geisshof kennen',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    timeLabel: '14:00 - 17:00 Uhr',
    startDate: '2026-10-30T14:00:00+02:00',
    endDate: '2026-10-30T17:00:00+02:00',
    status: 'upcoming',
    signupRequired: true,
    signupEnabled: true
  }
]

export function getAktuellesItems(): AktuellesItem[] {
  return aktuellesData.filter(item => item.type === 'aktuelles')
}

export function getEventItems(): AktuellesItem[] {
  return getStaticEventItems()
}

export function getStaticEventItems(): AktuellesItem[] {
  const now = new Date()
  
  return aktuellesData
    .filter(item => item.type === 'event')
    .map(item => {
      // Parse the event date to determine if it's past
      const itemDate = item.startDate ? new Date(item.startDate) : null
      const isPast = itemDate && itemDate < now
      
      return {
        ...item,
        // Explicitly set signup to false if event is past
        signupEnabled: isPast ? false : (item.signupEnabled ?? item.signupRequired ?? false),
        media: item.media ?? [],
        status: isPast ? 'past' : (item.status ?? 'upcoming'),
      }
    })
}

/**
 * Get all Aktuelles items
 */
export function getAllAktuellesItems(): AktuellesItem[] {
  return getAktuellesItems()
}

/**
 * Parse German date string to Date object
 */
function parseDate(dateStr: string): Date {
  // Format: "DD.MM.YYYY"
  const parts = dateStr.split('.')
  if (parts.length === 3) {
    return new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]))
  }
  return new Date(dateStr)
}

const DEFAULT_SITE_URL =
  SITE_URL ||
  (process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : 'http://localhost:3000')

export async function fetchEventsFromCms(): Promise<EventsFeed> {
  const endpoint =
    typeof window === 'undefined'
      ? `${DEFAULT_SITE_URL}/api/events`
      : '/api/events'

  const response = await fetch(endpoint, {
    cache: 'force-cache',
  })

  if (!response.ok) {
    throw new Error(`Failed to fetch events: ${response.status}`)
  }

  const data = (await response.json()) as EventsApiResponse
  if (!data.success) {
    throw new Error('Events API returned an error')
  }

  return {
    upcoming: data.upcoming.map(mapEventFromApi),
    past: data.past.map(mapEventFromApi),
    generatedAt: data.generatedAt,
  }
}

function mapEventFromApi(event: EventsApiEvent): AktuellesItem {
  const fallbackDate = event.startDate
    ? new Date(event.startDate).toLocaleDateString('de-CH')
    : ''

  const previewImage = event.media?.find(media => media.type === 'image')

  return {
    id: event.id,
    date: event.dateLabel || fallbackDate,
    title: event.title,
    description: event.description,
    type: 'event',
    fullDescription: event.fullDescription,
    location: event.location,
    time: event.timeLabel,
    timeLabel: event.timeLabel,
    startDate: event.startDate,
    endDate: event.endDate,
    status: event.status ?? 'upcoming',
    signupEnabled: event.signupEnabled,
    signupRequired: event.signupEnabled,
    signupNotes: event.signupNotes,
    imageUrl: previewImage?.url,
    media: event.media,
    url: event.url,
    parentTitle: event.parentTitle,
  }
}

export function getFallbackEventsFeed(): EventsFeed {
  return {
    upcoming: getStaticEventItems(),
    past: [],
  }
}

