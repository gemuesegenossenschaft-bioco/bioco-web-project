'use client'

import { useState } from 'react'
import Link from 'next/link'
import { getAktuellesItems, AktuellesItem } from './AktuellesData'
import { AktuellesItemComponent } from './AktuellesItem'
import { ItemDetailModal } from './ItemDetailModal'
import { useEventsFeed } from '@/hooks/useEventsFeed'

export function AktuellesTabs() {
  const aktuellesItems = getAktuellesItems()
  const { upcoming: eventItems, isLoading } = useEventsFeed(3)
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

  return (
    <>
      <div className="aktuelles-events-row">
        <section id="A-07" className="bento-card">
          <div className="plant-pattern"></div>
          <div className="card-header">
            <h3>Aktuelles</h3>
          </div>
          <div className="card-body">
            <div className="aktuelles-list">
              {aktuellesItems.map((item, index) => (
                <AktuellesItemComponent 
                  key={item.id || index} 
                  item={item} 
                  variant="aktuelles"
                  onClick={handleItemClick}
                />
              ))}
            </div>
            <Link href="/aktuelles" className="btn btn-primary" style={{ marginTop: '16px', display: 'inline-block' }}>
              Alle Neuigkeiten ansehen
            </Link>
          </div>
        </section>

        <section id="A-08" className="bento-card events-card">
          <div className="plant-pattern"></div>
          <div className="card-header">
            <h3>Nächste Events</h3>
          </div>
          <div className="card-body">
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
                  <p style={{ color: 'var(--text-secondary)' }}>Keine Events geplant.</p>
                )}
              </div>
            )}
            <Link href="/aktuelles" className="btn btn-primary" style={{ marginTop: '16px', display: 'inline-block' }}>
              Alle Events ansehen
            </Link>
          </div>
        </section>
      </div>
      <ItemDetailModal 
        item={selectedItem} 
        isOpen={isModalOpen} 
        onClose={handleCloseModal} 
      />
    </>
  )
}
