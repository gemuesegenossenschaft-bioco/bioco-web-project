'use client'

import { useState } from 'react'
import Link from 'next/link'
import { AktuellesItem } from './AktuellesData'
import { AktuellesItemComponent } from './AktuellesItem'
import { ItemDetailModal } from './ItemDetailModal'
import { useEventsFeed } from '@/hooks/useEventsFeed'

interface EventsBannerProps {
  title?: string
  showTitle?: boolean
  variant?: 'default' | 'embedded'
}

export function EventsBanner({
  title = 'Nächste Events',
  showTitle = true,
  variant = 'default',
}: EventsBannerProps) {
  const { upcoming: eventItems, isLoading, error } = useEventsFeed(3)
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
        {showTitle && <h2>{title}</h2>}
        {isLoading ? (
          <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
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
            {eventItems.length === 0 && (
              <p style={{ color: 'var(--text-secondary)' }}>
                Aktuell keine Events geplant.
              </p>
            )}
          </div>
        )}
        <p style={{ marginTop: 'var(--spacing-md)' }}>
          <Link href="/aktuelles">Alle Events ansehen →</Link>
        </p>
      </section>
      <ItemDetailModal 
        item={selectedItem} 
        isOpen={isModalOpen} 
        onClose={handleCloseModal} 
      />
    </>
  )
}
