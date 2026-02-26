import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'

// Mock cmsClient before importing
vi.mock('@/lib/cmsClient', () => ({
  fetchCmsJsonSafe: vi.fn(),
}))

// Mock next/image
vi.mock('next/image', () => ({
  default: (props: Record<string, unknown>) => {
    const { fill, priority, ...rest } = props
    return <img {...rest} data-fill={fill ? 'true' : undefined} data-priority={priority ? 'true' : undefined} />
  },
}))

import { getGalleryImages } from '@/lib/processwire'
import { fetchCmsJsonSafe } from '@/lib/cmsClient'
import { Gallery } from '@/components/Gallery'

const mockFetch = vi.mocked(fetchCmsJsonSafe)

describe('getGalleryImages', () => {
  beforeEach(() => {
    mockFetch.mockReset()
  })

  it('returns images from CMS gallery endpoint', async () => {
    mockFetch.mockResolvedValueOnce({
      success: true,
      images: [
        { id: 'ernte-1', src: 'https://cms.bioco.ch/site/assets/files/1234/bohnen.jpg', alt: 'Bohnen', category: 'feld' },
        { id: 'ernte-2', src: 'https://cms.bioco.ch/site/assets/files/1234/mais.jpg', alt: 'Mais', category: 'feld' },
      ],
    })

    const images = await getGalleryImages()

    expect(mockFetch).toHaveBeenCalledWith('/content/gallery', expect.any(Object))
    expect(images).toHaveLength(2)
    expect(images[0]).toMatchObject({ id: 'ernte-1', src: expect.stringContaining('bohnen.jpg') })
  })

  it('returns empty array when CMS returns null', async () => {
    mockFetch.mockResolvedValueOnce(null)

    const images = await getGalleryImages()

    expect(images).toEqual([])
  })
})

describe('Gallery component', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('renders CMS images when fetch succeeds', async () => {
    const cmsImages = [
      { id: 'cms-1', src: 'https://cms.bioco.ch/site/assets/files/100/feld.jpg', alt: 'Feld vom CMS', category: 'feld' },
      { id: 'cms-2', src: 'https://cms.bioco.ch/site/assets/files/100/mais.jpg', alt: 'Mais vom CMS', category: 'feld' },
    ]
    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve({ success: true, images: cmsImages }),
    })

    render(<Gallery />)

    await waitFor(() => {
      expect(screen.getByAltText('Feld vom CMS')).toBeDefined()
      expect(screen.getByAltText('Mais vom CMS')).toBeDefined()
    })
  })

  it('renders fallback images when fetch fails', async () => {
    globalThis.fetch = vi.fn().mockRejectedValueOnce(new Error('network error'))

    render(<Gallery />)

    // Fallback images should remain (initial state)
    await waitFor(() => {
      expect(screen.getByAltText('Bohnen-Ernte auf dem Geisshof')).toBeDefined()
    })
    // CMS images should NOT be present
    expect(screen.queryByAltText('Feld vom CMS')).toBeNull()
  })
})

describe('Fallback content', () => {
  it('uses CMS image URLs, not static /images/ paths', async () => {
    const { FALLBACK_HERO, FALLBACK_HOMEPAGE_SECTIONS, FALLBACK_MITMACHEN_SECTIONS, FALLBACK_GROUP_CARDS } = await import('@/lib/fallback-content')

    // Hero image should be a CMS URL
    if (FALLBACK_HERO.image) {
      expect(FALLBACK_HERO.image).toMatch(/^https?:\/\/cms\.bioco\.ch\//)
      expect(FALLBACK_HERO.image).not.toMatch(/^\/images\//)
    }

    // All section images should be CMS URLs
    for (const section of [...FALLBACK_HOMEPAGE_SECTIONS, ...FALLBACK_MITMACHEN_SECTIONS]) {
      if (section.image) {
        expect(section.image, `Section "${section.id}" has static image path`).toMatch(/^https?:\/\/cms\.bioco\.ch\//)
        expect(section.image).not.toMatch(/^\/images\//)
      }
    }

    // Group card images should be CMS URLs
    for (const group of FALLBACK_GROUP_CARDS) {
      if (group.image) {
        expect(group.image, `Group "${group.id}" has static image path`).toMatch(/^https?:\/\/cms\.bioco\.ch\//)
        expect(group.image).not.toMatch(/^\/images\//)
      }
    }
  })
})
