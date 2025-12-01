'use client'

import { useState } from 'react'
import { getEventItems, AktuellesItem } from './AktuellesData'
import { AktuellesItemComponent } from './AktuellesItem'
import { ItemDetailModal } from './ItemDetailModal'
import Link from 'next/link'

interface EventsSectionProps {
  limit?: number
  showAllButton?: boolean
}

export function EventsSection({ limit, showAllButton = true }: EventsSectionProps) {
  const [selectedItem, setSelectedItem] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
  
  const eventItems = getEventItems()
  const displayItems = limit ? eventItems.slice(0, limit) : eventItems

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
          <div className="events-list">
            {displayItems.map((item, index) => (
              <AktuellesItemComponent 
                key={item.id || index} 
                item={item} 
                variant="event"
                onClick={handleItemClick}
              />
            ))}
          </div>
          {showAllButton && (
            <Link href="/aktuelles" className="btn btn-primary" style={{ marginTop: '16px', display: 'inline-block' }}>
              Alle Events ansehen
            </Link>
          )}
        </div>
      </section>

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

