'use client'

import { useState } from 'react'
import Image from 'next/image'

type FilterType = 'all' | 'körbe' | 'feld' | 'portraits'

interface GalleryImage {
  id: string
  src: string
  alt: string
  category: FilterType
}

// Gallery images from public/images/ernte/
const GALLERY_IMAGES: GalleryImage[] = [
  {
    id: 'ernte-bohnen',
    src: '/images/ernte/bioco_ernte_bohnen.JPG',
    alt: 'Bohnen-Ernte auf dem Geisshof',
    category: 'feld'
  },
  {
    id: 'ernte-mais',
    src: '/images/ernte/bioco_ernte_mais.JPG',
    alt: 'Mais-Ernte auf dem Geisshof',
    category: 'feld'
  },
  {
    id: 'ernte-paprika',
    src: '/images/ernte/bioco_ernte_paprika.JPG',
    alt: 'Paprika-Ernte auf dem Geisshof',
    category: 'feld'
  },
  {
    id: 'ernte-zwiebel',
    src: '/images/ernte/bioco_ernte_zwiebel.JPG',
    alt: 'Zwiebel-Ernte auf dem Geisshof',
    category: 'feld'
  },
  {
    id: 'ernte-kürbis',
    src: '/images/ernte/bioco_ernte-kürbis-hoch.JPG',
    alt: 'Kürbis-Ernte auf dem Geisshof',
    category: 'feld'
  },
  {
    id: 'ernte-salat',
    src: '/images/ernte/bioco_ertne_Salat.JPG',
    alt: 'Salat-Ernte auf dem Geisshof',
    category: 'feld'
  },
  {
    id: 'feld-himmel',
    src: '/images/ernte/bioco_feld-mit-himmel.JPG',
    alt: 'Feld mit Himmel auf dem Geisshof',
    category: 'feld'
  }
]

export function Gallery() {
  const [activeFilter, setActiveFilter] = useState<FilterType>('all')
  const [showAll, setShowAll] = useState(false)

  const filters: { id: FilterType; label: string }[] = [
    { id: 'all', label: 'Alles' },
    { id: 'körbe', label: 'Körbe' },
    { id: 'feld', label: 'Feld' },
    { id: 'portraits', label: 'Portraits' },
  ]

  const filteredImages = activeFilter === 'all' 
    ? GALLERY_IMAGES 
    : GALLERY_IMAGES.filter(img => img.category === activeFilter)

  const displayedImages = showAll ? filteredImages : filteredImages.slice(0, 4)
  const hasMoreImages = filteredImages.length > 4

  return (
    <div className="gallery-container">
      {/* Desktop: Buttons, Mobile: Dropdown */}
      <div className="gallery-filters">
        {filters.map(filter => (
          <button
            key={filter.id}
            className={`gallery-filter ${activeFilter === filter.id ? 'active' : ''}`}
            onClick={() => {
              setActiveFilter(filter.id)
              setShowAll(false) // Reset showAll when filter changes
            }}
          >
            {filter.label}
          </button>
        ))}
      </div>
      <select
        className="gallery-select"
        value={activeFilter}
        onChange={(e) => {
          setActiveFilter(e.target.value as FilterType)
          setShowAll(false)
        }}
      >
        {filters.map(filter => (
          <option key={filter.id} value={filter.id}>
            {filter.label}
          </option>
        ))}
      </select>

      {GALLERY_IMAGES.length > 0 ? (
        <>
          <div className="gallery-grid">
            {displayedImages.map(image => (
              <div key={image.id} className="gallery-item">
                <Image
                  src={image.src}
                  alt={image.alt}
                  width={400}
                  height={300}
                  style={{ objectFit: 'cover', width: '100%', height: '100%', borderRadius: '8px' }}
                />
              </div>
            ))}
          </div>
          {hasMoreImages && (
            <div style={{ marginTop: 'var(--spacing-md)', textAlign: 'center' }}>
              <button
                className="btn btn-secondary"
                onClick={() => setShowAll(!showAll)}
              >
                {showAll ? 'Weniger anzeigen' : 'Mehr sehen'}
              </button>
            </div>
          )}
        </>
      ) : (
        <div className="gallery-placeholder">
          <p>Galerie-Bilder werden geladen...</p>
          <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
            Die Galerie wird in Kürze mit Bildern aus dem Geisshof gefüllt.
          </p>
        </div>
      )}
    </div>
  )
}
