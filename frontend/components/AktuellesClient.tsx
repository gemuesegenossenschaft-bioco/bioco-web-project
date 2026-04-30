'use client'

import { useState } from 'react'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import Image from 'next/image'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import type { ContentSection } from '@/lib/processwire-types'
import type { AktuellesItem } from '@/components/AktuellesData'
import { groupEventsByType } from '@/components/AktuellesData'

function hasHeadingHtml(html?: string | null): boolean {
  return /<h[1-6]\b[^>]*>/i.test(String(html || ''))
}

interface AktuellesClientProps {
  sections?: ContentSection[]
  aktuellesItems: AktuellesItem[]
}

export function AktuellesClient({ sections, aktuellesItems }: AktuellesClientProps) {
  const allAktuellesItems = aktuellesItems
  const { upcoming: eventItems, past, isLoading: eventsLoading } = useEventsFeed()
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

  const upcomingGroups = groupEventsByType(eventItems)
  const pastGroups = groupEventsByType(past)
  const upcomingItems = [...upcomingGroups.general, ...upcomingGroups.schnuppertage]
  const pastItems = [...pastGroups.general, ...pastGroups.schnuppertage]
  
  // Get CMS content if available
  const introSection = sections?.find(s => s.id === 'intro')

  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          <section id="G-01" style={{ marginBottom: 'clamp(24px, 4vw, 48px)' }}>
            {!hasHeadingHtml(introSection?.text) && (
              <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>
                {introSection?.title || 'Aktuelles'}
              </h1>
            )}
            {introSection?.text && (
              <div 
                style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}
                dangerouslySetInnerHTML={{ __html: introSection.text }}
              />
            )}
            <div style={{ marginTop: '24px' }}>
              <h2>Beiträge</h2>
              <div className="aktuelles-list">
                {allAktuellesItems.map((item, index) => (
                  <AktuellesItemComponent 
                    key={item.id || index} 
                      item={item} 
                      variant="aktuelles"
                      onClick={handleItemClick}
                    />
                  ))}
                {allAktuellesItems.length === 0 && (
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Keine Beiträge verfügbar.</p>
                )}
              </div>
            </div>
          </section>

          <section id="G-02" style={{ marginBottom: 'clamp(24px, 4vw, 48px)' }}>
            <h2>Events</h2>
            <div style={{ marginTop: '16px' }}>
              <h3>Kommende Events</h3>
              {eventsLoading ? (
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Events werden geladen…</p>
              ) : (
                <>
                  {upcomingItems.length > 0 ? (
                    <div className="events-list">
                      {upcomingItems.map((item, index) => (
                        <AktuellesItemComponent
                          key={item.id || index}
                          item={item}
                          variant="event"
                          onClick={handleItemClick}
                        />
                      ))}
                    </div>
                  ) : (
                    <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Aktuell sind keine Events geplant.</p>
                  )}
                </>
              )}
            </div>
          </section>

          {past.length > 0 && (
            <section id="G-03" className="bento-card past-events-card">
              <div className="card-header">
                <h3>Vergangene Events</h3>
              </div>
              <div className="card-body past-events-grid">
                {pastItems.map((item, index) => (
                    <button
                      key={item.id || index}
                      className="past-event-tile"
                      onClick={() => handleItemClick(item)}
                    >
                      {item.cardImage && (
                        <div className="past-event-media">
                          <Image
                              src={item.cardImage}
                              alt={item.cardImageAlt || item.title}
                              fill
                              sizes="(max-width: 768px) 100vw, 25vw"
                              style={{ objectFit: 'cover' }}
                            />
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
          <section id="B-06" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Möchtest du uns kennenlernen?</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
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
