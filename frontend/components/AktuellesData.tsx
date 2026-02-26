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
  eventType?: string // 'general' | 'schnuppertag'
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
  eventType?: string
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

export function getAktuellesItems(): AktuellesItem[] {
  return []
}

export function getAllAktuellesItems(): AktuellesItem[] {
  return []
}

/**
 * Format date to German format: "27. November 2025"
 */
function formatDateFull(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date
  if (isNaN(d.getTime())) return ''
  
  const months = [
    'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
  ]
  
  const day = d.getDate()
  const month = months[d.getMonth()]
  const year = d.getFullYear()
  
  return `${day}. ${month} ${year}`
}


const DEFAULT_SITE_URL = SITE_URL || 'https://bioco.ch'

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
    ? formatDateFull(event.startDate)
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
    eventType: event.eventType || 'general',
  }
}

export function getFallbackEventsFeed(): EventsFeed {
  return {
    upcoming: [],
    past: [],
  }
}

