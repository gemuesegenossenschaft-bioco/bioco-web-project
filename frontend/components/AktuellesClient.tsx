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
  const upcomingGeneralItems = upcomingGroups.general
  const upcomingSchnuppertagItems = upcomingGroups.schnuppertage
  const pastItems = past
  
  // CMS-owned content sections (seeded from cms/content-seed/aktuelles.json)
  const introSection = sections?.find(s => s.id === 'intro')
  const kennenlernenSection = sections?.find(s => s.id === 'kennenlernen-cta')

  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(var(--space-7), 8vw, var(--space-9)) clamp(var(--space-4), 6vw, var(--space-9))' }}>
          <section id="G-01" style={{ marginBottom: 'clamp(var(--space-5), 4vw, var(--space-7))' }}>
            {introSection && !hasHeadingHtml(introSection.text) && introSection.title && (
              <h1 style={{ fontSize: '2.5rem', margin: '0 0 var(--space-4) 0' }}>
                {introSection.title}
              </h1>
            )}
            {introSection?.text && (
              <div
                style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: 'var(--space-5)' }}
                dangerouslySetInnerHTML={{ __html: introSection.text }}
              />
            )}
            <div style={{ marginTop: 'var(--space-5)' }}>
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

          <section id="G-02" style={{ marginBottom: 'clamp(var(--space-5), 4vw, var(--space-7))' }}>
            <h2>Events</h2>
            <div style={{ marginTop: 'var(--space-4)' }}>
              <h3>Kommende Events</h3>
              {eventsLoading ? (
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Events werden geladen…</p>
              ) : (
                <>
                  {upcomingGeneralItems.length > 0 ? (
                    <div className="events-list">
                      {upcomingGeneralItems.map((item, index) => (
                        <AktuellesItemComponent
                          key={item.id || index}
                          item={item}
                          variant="event"
                          onClick={handleItemClick}
                        />
                      ))}
                    </div>
                  ) : (
                    <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Aktuell sind keine allgemeinen Events geplant.</p>
                  )}
                </>
              )}
            </div>

            <div style={{ marginTop: 'var(--space-6)' }}>
              <h3>Schnuppertage</h3>
              {eventsLoading ? (
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Events werden geladen…</p>
              ) : (
                <>
                  {upcomingSchnuppertagItems.length > 0 ? (
                    <div className="events-list">
                      {upcomingSchnuppertagItems.map((item, index) => (
                        <AktuellesItemComponent
                          key={item.id || index}
                          item={item}
                          variant="event"
                          onClick={handleItemClick}
                        />
                      ))}
                    </div>
                  ) : (
                    <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Aktuell sind keine Schnuppertage geplant.</p>
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

          {/* CMS section 'kennenlernen-cta' - Am Ende */}
          {kennenlernenSection && (
            <section id="B-06" style={{ marginBottom: 'clamp(var(--space-7), 8vw, var(--space-9))' }}>
              {!hasHeadingHtml(kennenlernenSection.text) && (
                <h2>{kennenlernenSection.title}</h2>
              )}
              {kennenlernenSection.text && (
                <div
                  style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: 'var(--space-5)' }}
                  dangerouslySetInnerHTML={{ __html: kennenlernenSection.text }}
                />
              )}
              {kennenlernenSection.buttons?.length ? (
                <div style={{ marginTop: 'var(--space-4)', display: 'flex', gap: 'var(--spacing-md)', flexWrap: 'wrap' }}>
                  {kennenlernenSection.buttons.map((btn, i) => (
                    <CTA
                      key={i}
                      text={btn.text}
                      href={btn.href}
                      variant={btn.variant as 'primary' | 'secondary'}
                    />
                  ))}
                </div>
              ) : null}
            </section>
          )}
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
