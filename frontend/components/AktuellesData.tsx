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
  time?: string
  timeLabel?: string
  signupEnabled?: boolean
  signupNotes?: string
  status?: EventStatus
  media?: EventMediaItem[]
  cardImage?: string
  cardImageAlt?: string
  url?: string
  parentTitle?: string
  eventType?: unknown
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

function formatDateCompact(date: Date): string {
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  return `${day}.${month}.${year}`
}

function parseGermanDateFromText(value?: string): Date | null {
  const text = (value || '').trim()
  if (!text) return null

  const dotDate = text.match(/(\d{1,2})\.(\d{1,2})\.(\d{4})/)
  if (dotDate) {
    const day = Number(dotDate[1])
    const month = Number(dotDate[2]) - 1
    const year = Number(dotDate[3])
    const parsed = new Date(year, month, day, 12, 0, 0)
    return isNaN(parsed.getTime()) ? null : parsed
  }

  const monthDate = text.match(/(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)\s*(\d{4})/)
  if (!monthDate) return null

  const day = Number(monthDate[1])
  const monthRaw = monthDate[2].toLowerCase()
  const year = Number(monthDate[3])
  const monthMap: Record<string, number> = {
    januar: 0,
    februar: 1,
    maerz: 2,
    märz: 2,
    april: 3,
    mai: 4,
    juni: 5,
    juli: 6,
    august: 7,
    september: 8,
    oktober: 9,
    november: 10,
    dezember: 11,
  }

  const month = monthMap[monthRaw]
  if (month === undefined) return null

  const parsed = new Date(year, month, day, 12, 0, 0)
  return isNaN(parsed.getTime()) ? null : parsed
}

function resolveEventDate(event: EventsApiEvent): Date | null {
  if (event.startDate) {
    const parsed = new Date(event.startDate)
    if (!isNaN(parsed.getTime())) return parsed
  }

  return (
    parseGermanDateFromText(event.dateLabel) ||
    parseGermanDateFromText(event.title) ||
    parseGermanDateFromText(event.description)
  )
}

function getSortTimestamp(item: Pick<AktuellesItem, 'startDate' | 'date' | 'title'>): number | null {
  if (item.startDate) {
    const parsed = new Date(item.startDate)
    if (!isNaN(parsed.getTime())) return parsed.getTime()
  }

  return (
    parseGermanDateFromText(item.date)?.getTime() ||
    parseGermanDateFromText(item.title)?.getTime() ||
    null
  )
}

function sortEventsAsc(a: AktuellesItem, b: AktuellesItem): number {
  const aTime = getSortTimestamp(a)
  const bTime = getSortTimestamp(b)

  if (aTime === null && bTime === null) return a.title.localeCompare(b.title, 'de')
  if (aTime === null) return 1
  if (bTime === null) return -1
  return aTime - bTime
}

function sortEventsDesc(a: AktuellesItem, b: AktuellesItem): number {
  return sortEventsAsc(b, a)
}


const DEFAULT_SITE_URL = SITE_URL || 'https://bioco.ch'

export async function fetchEventsFromCms(): Promise<EventsFeed> {
  const endpoints =
    typeof window === 'undefined'
      ? [`${DEFAULT_SITE_URL}/api/events`, `${DEFAULT_SITE_URL}/cms/api/events/`, 'https://cms.bioco.ch/api/events/']
      : ['/api/events', '/cms/api/events/', 'https://cms.bioco.ch/api/events/']

  let data: EventsApiResponse | null = null

  for (const endpoint of endpoints) {
    try {
      const response = await fetch(endpoint, { cache: 'no-store' })
      if (!response.ok) continue
      const candidate = (await response.json()) as EventsApiResponse
      if (!isValidEventsApiResponse(candidate) || !candidate.success) continue
      data = candidate
      break
    } catch {
      // Try the next endpoint.
    }
  }

  if (!data) {
    throw new Error('Failed to fetch events from all endpoints')
  }

  return {
    upcoming: data.upcoming.map(mapEventFromApi).sort(sortEventsAsc),
    past: data.past.map(mapEventFromApi).sort(sortEventsDesc),
    generatedAt: data.generatedAt,
  }
}

function isValidEventsApiResponse(data: unknown): data is EventsApiResponse {
  if (!data || typeof data !== 'object') return false
  const candidate = data as Partial<EventsApiResponse>
  return Array.isArray(candidate.upcoming) && Array.isArray(candidate.past)
}

function mapEventFromApi(event: EventsApiEvent): AktuellesItem {
  const resolvedDate = resolveEventDate(event)
  const resolvedStartDate = resolvedDate ? resolvedDate.toISOString() : event.startDate
  const fallbackDate = resolvedDate ? formatDateCompact(resolvedDate) : ''

  const previewImage = event.media?.find(media => media.type === 'image')

  return {
    id: event.id,
    date: event.dateLabel || fallbackDate,
    title: event.title,
    description: event.description,
    type: 'event',
    fullDescription: event.fullDescription || event.description,
    location: event.location,
    time: event.timeLabel || event.time,
    timeLabel: event.timeLabel || event.time,
    startDate: resolvedStartDate,
    endDate: event.endDate,
    status: event.status ?? 'upcoming',
    signupEnabled: event.signupEnabled,
    signupRequired: event.signupEnabled,
    signupNotes: event.signupNotes,
    imageUrl: event.cardImage || previewImage?.url,
    media: event.media,
    url: event.url,
    parentTitle: event.parentTitle,
    eventType: normalizeEventType(event.eventType, event.title, event.description),
  }
}

function normalizeEventType(value: unknown, title?: string, description?: string): string {
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase()
    if (normalized) return normalized
  }

  if (value && typeof value === 'object') {
    const candidate = value as Record<string, unknown>
    for (const key of ['name', 'value', 'title']) {
      const raw = candidate[key]
      if (typeof raw === 'string' && raw.trim()) {
        return raw.trim().toLowerCase()
      }
    }
  }

  const text = `${title || ''} ${description || ''}`.toLowerCase()
  if (text.includes('schnuppertag')) return 'schnuppertag'
  return 'general'
}

export function getFallbackEventsFeed(): EventsFeed {
  return {
    upcoming: [],
    past: [],
  }
}
