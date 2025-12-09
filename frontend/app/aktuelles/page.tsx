'use client'

import { useState, useEffect } from 'react'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { getAktuellesItems, getAllAktuellesItems, AktuellesItem } from '@/components/AktuellesData'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import Link from 'next/link'
import { useEventsFeed } from '@/hooks/useEventsFeed'

export default function AktuellesPage() {
  const staticAktuellesItems = getAktuellesItems()
  const { upcoming: eventItems, past, isLoading: eventsLoading, error: eventsError } = useEventsFeed()
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

  const schnuppertageEvents = eventItems.filter((item) =>
    (item.title || '').toLowerCase().includes('schnuppertag')
  )
  const otherEvents = eventItems.filter(
    (item) => !(item.title || '').toLowerCase().includes('schnuppertag')
  )

  return (
    <>
      <Header />
      <main className="main-content">
        <div className="bento-grid">
          <section id="G-01" className="bento-card bento-card-flat">
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

          <section id="G-02" className="bento-card bento-card-flat events-card">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Schnuppertage</h3>
            </div>
            <div className="card-body">
              {eventsLoading ? (
                <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
              ) : (
                <div className="events-list">
                  {schnuppertageEvents.map((item, index) => (
                    <AktuellesItemComponent 
                      key={item.id || index} 
                      item={item} 
                      variant="event"
                      onClick={handleItemClick}
                    />
                  ))}
                  {schnuppertageEvents.length === 0 && (
                    <p style={{ color: 'var(--text-secondary)' }}>Aktuell sind keine Schnuppertage geplant.</p>
                  )}
                </div>
              )}
              <div
                style={{
                  marginTop: '16px',
                  background: 'var(--bioco-green)',
                  color: '#fff',
                  padding: '14px',
                  borderRadius: '10px',
                  border: '1px solid var(--bioco-green)',
                  boxShadow: 'var(--shadow-sm)',
                }}
              >
                <p className="card-text" style={{ marginBottom: '8px', color: '#fff' }}>
                  Neugierig? Schau vorbei und mach mit. Erlebe an unseren Schnuppertagen, wie solidarische Landwirtschaft funktioniert – direkt auf dem Geisshof in Gebenstorf, umgeben von Natur, Wildpflanzen, auf unserem sonnigen Feld. Als Dank für deine Mithilfe erhältst du eine Tasche frisch geerntetes Demeter-Gemüse und ein kleines zVieri spendiert.
                </p>
                <Link
                  href="/mitmachen"
                  className="btn btn-secondary"
                  style={{
                    display: 'inline-block',
                    marginTop: '4px',
                    background: '#fff',
                    color: 'var(--bioco-green)',
                    borderColor: '#fff',
                  }}
                >
                  Zur Mitmachen-Seite
                </Link>
              </div>
            </div>
          </section>

          <section id="G-02b" className="bento-card bento-card-flat">
            <div className="plant-pattern"></div>
            <div className="card-header">
              <h3>Weitere Events</h3>
            </div>
            <div className="card-body">
              {eventsLoading ? (
                <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
              ) : (
                <div className="events-list">
                  {otherEvents.map((item, index) => (
                    <AktuellesItemComponent 
                      key={item.id || index} 
                      item={item} 
                      variant="event"
                      onClick={handleItemClick}
                    />
                  ))}
                  {otherEvents.length === 0 && (
                    <p style={{ color: 'var(--text-secondary)' }}>Aktuell sind keine weiteren Events geplant.</p>
                  )}
                </div>
              )}
            </div>
          </section>

          {past.length > 0 && (
            <section id="G-03" className="bento-card past-events-card">
              <div className="card-header">
                <h3>Vergangene Events</h3>
              </div>
              <div className="card-body past-events-grid">
                {past.map((item, index) => (
                  <button
                    key={item.id || index}
                    className="past-event-tile"
                    onClick={() => handleItemClick(item)}
                  >
                    {item.media?.[0] && (
                      <div className="past-event-media">
                        {item.media[0].type === 'image' ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={item.media[0].url} alt={item.media[0].description || item.title} />
                        ) : (
                          <video src={item.media[0].url} muted playsInline />
                        )}
                      </div>
                    )}
                    <div className="past-event-meta">
                      <p className="past-event-date">{item.date}</p>
                      <p className="past-event-title">{item.title}</p>
                      <span className="past-event-cta">Rückblick ansehen →</span>
                    </div>
                  </button>
                ))}
              </div>
            </section>
          )}

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
