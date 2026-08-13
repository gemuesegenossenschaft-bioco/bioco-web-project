'use client'

import { useState } from 'react'
import Link from 'next/link'
import { AktuellesItem } from './AktuellesData'
import { AktuellesItemComponent } from './AktuellesItem'
import { ItemDetailModal } from './ItemDetailModal'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import { safeSitePath } from '@/lib/safeHref'

interface EventsBannerProps {
  title?: string
  showTitle?: boolean
  variant?: 'default' | 'embedded'
  limit?: number
  archiveUrl?: string
  archiveLabel?: string
  loadingMessage?: string
  emptyMessage?: string
}

export function EventsBanner({
  title = '',
  showTitle = true,
  variant = 'default',
  limit = 3,
  archiveUrl = '',
  archiveLabel = '',
  loadingMessage = '',
  emptyMessage = '',
}: EventsBannerProps) {
  const archiveHref = safeSitePath(archiveUrl)
  const { upcoming: eventItems, isLoading, error } = useEventsFeed(limit)
  const [selectedItem, setSelectedItem] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)

  const handleItemClick = (item: AktuellesItem) => {
    setSelectedItem(item)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setSelectedItem(null)
  }
  
  const containerClassName =
    variant === 'embedded' ? 'events-banner events-banner-embedded' : 'events-banner'

  return (
    <>
      <section className={containerClassName}>
        {showTitle && title ? <h2>{title}</h2> : null}
        {isLoading ? (
          loadingMessage ? <p style={{ color: 'var(--text-secondary)' }}>{loadingMessage}</p> : null
        ) : (
          <div className="events-list">
            {eventItems.map((item, index) => (
              <AktuellesItemComponent 
                key={item.id || index} 
                item={item} 
                variant="event"
                onClick={handleItemClick}
              />
            ))}
            {eventItems.length === 0 && emptyMessage && (
              <p style={{ color: 'var(--text-secondary)' }}>
                {emptyMessage}
              </p>
            )}
          </div>
        )}
        {archiveHref && archiveLabel ? <p style={{ marginTop: 'var(--spacing-md)' }}>
          <Link href={archiveHref}>{archiveLabel}</Link>
        </p> : null}
      </section>
      <ItemDetailModal 
        item={selectedItem} 
        isOpen={isModalOpen} 
        onClose={handleCloseModal} 
      />
    </>
  )
}
