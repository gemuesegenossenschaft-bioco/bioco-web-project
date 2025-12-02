'use client'

import { useState, useEffect } from 'react'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { getAktuellesItems, getEventItems, getAllAktuellesItems, AktuellesItem } from '@/components/AktuellesData'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import Link from 'next/link'

export default function AktuellesPage() {
  const staticAktuellesItems = getAktuellesItems()
  const eventItems = getEventItems()
  const [allAktuellesItems, setAllAktuellesItems] = useState<AktuellesItem[]>(staticAktuellesItems)
  const [selectedItem, setSelectedItem] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(true)

  // Load Instagram posts on mount
  useEffect(() => {
    const loadInstagramPosts = async () => {
      try {
        const items = await getAllAktuellesItems()
        setAllAktuellesItems(items)
      } catch (error) {
        console.error('Error loading Instagram posts:', error)
        // Fallback to static items
        setAllAktuellesItems(staticAktuellesItems)
      } finally {
        setIsLoading(false)
      }
    }
    
    loadInstagramPosts()
  }, [])

  const handleItemClick = (item: AktuellesItem) => {
    // Don't open modal for Instagram posts (they open in new tab)
    if (item.type === 'instagram') {
      return
    }
    setSelectedItem(item)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setSelectedItem(null)
  }

  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <div className="aktuelles-events-row">
            <section id="G-01" className="bento-card">
              <div className="plant-pattern"></div>
              <div className="card-header">
                <h3>Aktuelles</h3>
              </div>
              <div className="card-body">
                {isLoading ? (
                  <p style={{ color: 'var(--text-secondary)' }}>Lade Beiträge...</p>
                ) : (
                  <div className="aktuelles-list">
                    {allAktuellesItems.map((item, index) => (
                      <AktuellesItemComponent 
                        key={item.id || item.instagram_id || index} 
                        item={item} 
                        variant="aktuelles"
                        onClick={handleItemClick}
                      />
                    ))}
                    {allAktuellesItems.length === 0 && (
                      <p style={{ color: 'var(--text-secondary)' }}>Keine Beiträge verfügbar.</p>
                    )}
                  </div>
                )}
              </div>
            </section>

            <section id="G-02" className="bento-card events-card">
              <div className="plant-pattern"></div>
              <div className="card-header">
                <h3>Nächste Events</h3>
              </div>
              <div className="card-body">
                <div className="events-list">
                  {eventItems.map((item, index) => (
                    <AktuellesItemComponent 
                      key={item.id || index} 
                      item={item} 
                      variant="event"
                      onClick={handleItemClick}
                    />
                  ))}
                </div>
              </div>
            </section>
          </div>

          {/* Möchtest du uns kennenlernen - Am Ende */}
          <section id="B-06" className="bento-card bento-card-fullwidth kennenlernen-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Möchtest du uns kennenlernen?</h3>
            </div>
            <div className="card-body">
              <p className="card-text">Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
              <div style={{ marginTop: '16px', display: 'flex', gap: 'var(--spacing-md)', flexWrap: 'wrap' }}>
                <CTA
                  text="Nimm Kontakt auf"
                  href="/kontakt"
                  variant="primary"
                />
                <CTA
                  text="Zu uns finden"
                  href="/standorte-depots"
                  variant="secondary"
                />
              </div>
            </div>
          </section>
        </div>
      </main>
      <Footer />
      <ItemDetailModal 
        item={selectedItem} 
        isOpen={isModalOpen} 
        onClose={handleCloseModal} 
      />
    </>
  )
}