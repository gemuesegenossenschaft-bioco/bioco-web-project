'use client'

import { useState } from 'react'
import { AktuellesItem } from './AktuellesData'
import { AktuellesItemComponent } from './AktuellesItem'
import { ItemDetailModal } from './ItemDetailModal'
import Link from 'next/link'
import Image from 'next/image'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import { safeSitePath } from '@/lib/safeHref'

interface EventsSectionProps {
  limit?: number
  showAllButton?: boolean
  archiveUrl?: string
  upcomingTitle?: string
  archiveLabel?: string
  loadingMessage?: string
  emptyMessage?: string
  pastTitle?: string
  pastDescription?: string
  pastLinkLabel?: string
}

export function EventsSection({ limit, showAllButton = true, archiveUrl = '', upcomingTitle = '', archiveLabel = '', loadingMessage = '', emptyMessage = '', pastTitle = '', pastDescription = '', pastLinkLabel = '' }: EventsSectionProps) {
  const archiveHref = safeSitePath(archiveUrl)
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
          {upcomingTitle ? <h3>{upcomingTitle}</h3> : null}
        </div>
        <div className="card-body">
          {isLoading && displayItems.length === 0 ? (
            loadingMessage ? <p style={{ color: 'var(--text-secondary)' }}>{loadingMessage}</p> : null
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
              {displayItems.length === 0 && emptyMessage && (
                <p style={{ color: 'var(--text-secondary)' }}>
                  {emptyMessage}
                </p>
              )}
            </div>
          )}
          {showAllButton && archiveHref && archiveLabel && (
            <Link href={archiveHref} className="btn btn-primary" style={{ marginTop: '16px', display: 'inline-block' }}>
              {archiveLabel}
            </Link>
          )}
        </div>
      </section>

      {past.length > 0 && (
        <section className="bento-card past-events-card">
          <div className="card-header">
            {pastTitle ? <h3>{pastTitle}</h3> : null}
            {pastDescription ? <p style={{ marginTop: '4px', color: 'var(--text-secondary)' }}>{pastDescription}</p> : null}
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
                    {pastLinkLabel ? <span className="past-event-cta">{pastLinkLabel}</span> : null}
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
