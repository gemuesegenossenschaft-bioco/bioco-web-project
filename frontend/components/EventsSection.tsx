'use client'

import { useState } from 'react'
import { AktuellesItem } from './AktuellesData'
import { AktuellesItemComponent } from './AktuellesItem'
import { ItemDetailModal } from './ItemDetailModal'
import Image from 'next/image'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import { Button } from '@/components/ui/Button'

interface EventsSectionProps {
  limit?: number
  showAllButton?: boolean
}

export function EventsSection({ limit, showAllButton = true }: EventsSectionProps) {
  const [selectedItem, setSelectedItem] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)

  const { upcoming, past, isLoading, error } = useEventsFeed(limit)
  const displayItems = upcoming

  const handleItemClick = (item: AktuellesItem) => {
    setSelectedItem(item)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setSelectedItem(null)
  }

  return (
    <>
      <section className="bento-card events-card bento-card-fullwidth">
        <div className="plant-pattern"></div>
        <div className="card-header">
          <h3>Nächste Events</h3>
        </div>
        <div className="card-body">
          {isLoading && displayItems.length === 0 ? (
            <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
          ) : (
            <div className="events-list">
              {displayItems.map((item, index) => (
                <AktuellesItemComponent
                  key={item.id || index}
                  item={item}
                  variant="event"
                  onClick={handleItemClick}
                />
              ))}
              {displayItems.length === 0 && (
                <p style={{ color: 'var(--text-secondary)' }}>
                  Aktuell sind keine Events geplant.
                </p>
              )}
            </div>
          )}
          {showAllButton && (
            <Button as="a" href="/aktuelles" variant="primary" style={{ marginTop: '16px', display: 'inline-block' }}>
              Alle Events ansehen
            </Button>
          )}
        </div>
      </section>

      {past.length > 0 && (
        <section className="bento-card past-events-card">
          <div className="card-header">
            <h3>Vergangene Events & Eindrücke</h3>
            <p style={{ marginTop: '4px', color: 'var(--text-secondary)' }}>
              Rückblicke mit Fotos und Videos aus unserer Community.
            </p>
          </div>
          <div className="card-body past-events-grid">
            {past.slice(0, 4).map((item, index) => {
              return (
                <button
                  key={item.id || index}
                  className="past-event-tile"
                  onClick={() => handleItemClick(item)}
                >
                  {item.cardImage && (
                    <div className="past-event-media">
                      <Image src={item.cardImage} alt={item.cardImageAlt || item.title} fill sizes="(max-width: 768px) 100vw, 25vw" style={{ objectFit: 'cover' }} />
                    </div>
                  )}
                  <div className="past-event-meta">
                    <p className="past-event-date">{item.date}</p>
                    <p className="past-event-title">{item.title}</p>
                    {item.location && (
                      <p className="past-event-location">{item.location}</p>
                    )}
                    <span className="past-event-cta">Rückblick ansehen →</span>
                  </div>
                </button>
              )
            })}
          </div>
        </section>
      )}

      {selectedItem && (
        <ItemDetailModal
          item={selectedItem}
          isOpen={isModalOpen}
          onClose={handleCloseModal}
        />
      )}
    </>
  )
}
