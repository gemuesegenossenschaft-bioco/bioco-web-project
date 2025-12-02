// Shared data source for Aktuelles and Events
// Structure designed to be easily replaceable with ProcessWire API data
export interface AktuellesItem {
  id?: string | number // For ProcessWire: page ID
  date: string
  title: string
  description: string
  type: 'aktuelles' | 'event' | 'instagram'
  // Extended fields for detail view
  fullDescription?: string
  location?: string
  time?: string
  signupRequired?: boolean
  signupUrl?: string
  imageUrl?: string
  // ProcessWire fields (for future API integration)
  body?: string // Full content from ProcessWire
  url?: string // ProcessWire page URL
  // Instagram-specific fields
  instagram_id?: string
  instagram_url?: string
}

export const aktuellesData: AktuellesItem[] = [
  {
    id: 1,
    date: '24. Oktober 2025',
    title: 'Letzter biocò Schnuppertag des Jahres',
    description: 'Auf dem Geisshof in Gebenstorf',
    type: 'event',
    fullDescription: 'Komm vorbei und lerne biocò kennen! An diesem Schnuppertag kannst du den Geisshof besichtigen, unser Gärtnerteam treffen und mehr über die solidarische Landwirtschaft erfahren. Für alle Interessierten, die biocò kennenlernen möchten.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '14:00 - 17:00 Uhr',
    signupRequired: true
  },
  {
    id: 2,
    date: '15. November 2025',
    title: 'Fondue-Abend',
    description: 'Gemeinsamer Anlass für alle Mitglieder',
    type: 'event',
    fullDescription: 'Gemeinsamer Fondue-Abend für alle Mitglieder der Genossenschaft. Ein gemütlicher Abend zum Austausch und Kennenlernen. Bring dein eigenes Fondue-Set mit oder teile mit anderen.',
    location: 'Geisshof, Geisslistrasse, 5412 Gebenstorf',
    time: '18:00 - 22:00 Uhr',
    signupRequired: true
  },
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
    signupRequired: true
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
    signupRequired: true
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
    signupRequired: true
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
    signupRequired: true
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
    signupRequired: true
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
    signupRequired: true
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
    signupRequired: true
  }
]

export function getAktuellesItems(): AktuellesItem[] {
  return aktuellesData.filter(item => item.type === 'aktuelles')
}

export function getEventItems(): AktuellesItem[] {
  return aktuellesData.filter(item => item.type === 'event')
}

/**
 * Fetch Instagram posts from API
 * Returns Instagram posts formatted as AktuellesItem
 */
export async function getInstagramPosts(): Promise<AktuellesItem[]> {
  try {
    const response = await fetch('/api/instagram', {
      next: { revalidate: 3600 } // Revalidate every hour
    })
    
    if (!response.ok) {
      return []
    }
    
    const data = await response.json()
    
    if (!data.success || !data.posts) {
      return []
    }
    
    // Convert Instagram posts to AktuellesItem format
    return data.posts.map((post: any) => ({
      id: post.instagram_id || post.id,
      date: post.date,
      title: post.title,
      description: post.body ? post.body.substring(0, 150) + (post.body.length > 150 ? '...' : '') : '',
      type: 'instagram' as const,
      fullDescription: post.body,
      imageUrl: post.imageUrl,
      instagram_id: post.instagram_id,
      instagram_url: post.instagram_url || post.url,
      url: post.instagram_url || post.url
    }))
  } catch (error) {
    console.error('Error fetching Instagram posts:', error)
    return []
  }
}

/**
 * Get all Aktuelles items including Instagram posts
 */
export async function getAllAktuellesItems(): Promise<AktuellesItem[]> {
  const staticItems = getAktuellesItems()
  const instagramPosts = await getInstagramPosts()
  
  // Combine and sort by date (newest first)
  const allItems = [...staticItems, ...instagramPosts]
  
  // Sort by date (parse date strings)
  allItems.sort((a, b) => {
    const dateA = parseDate(a.date)
    const dateB = parseDate(b.date)
    return dateB.getTime() - dateA.getTime()
  })
  
  return allItems
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

