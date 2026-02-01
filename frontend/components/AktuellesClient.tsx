'use client'

import { useState } from 'react'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import Link from 'next/link'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import type { ContentSection } from '@/lib/processwire-types'
import type { AktuellesItem } from '@/components/AktuellesData'

interface AktuellesClientProps {
  sections?: ContentSection[]
  aktuellesItems: AktuellesItem[]
}

export function AktuellesClient({ sections, aktuellesItems }: AktuellesClientProps) {
  const allAktuellesItems = aktuellesItems
  const { upcoming: eventItems, past, isLoading: eventsLoading, error: eventsError } = useEventsFeed()
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

  const schnuppertageEvents = eventItems.filter((item) =>
    (item.title || '').toLowerCase().includes('schnuppertag')
  )
  const otherEvents = eventItems.filter(
    (item) => !(item.title || '').toLowerCase().includes('schnuppertag')
  )
  
  // Get CMS content if available
  const introSection = sections?.find(s => s.id === 'intro')

  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          <section id="G-01" style={{ marginBottom: 'clamp(24px, 4vw, 48px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>
              {introSection?.title || 'Aktuelles'}
            </h1>
            {introSection?.text && (
              <div 
                style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}
                dangerouslySetInnerHTML={{ __html: introSection.text }}
              />
            )}
            <div style={{ marginTop: '24px' }}>
              <div className="aktuelles-list">
                {allAktuellesItems.slice(0, 3).map((item, index) => (
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
                {allAktuellesItems.length > 3 && (
                  <div style={{ marginTop: '24px', textAlign: 'center' }}>
                    <Link href="/aktuelles" className="btn btn-secondary btn-organic" style={{ display: 'inline-block' }}>
                      Alle Neuigkeiten
                    </Link>
                  </div>
                )}
              </div>
            </div>
          </section>

          <section id="G-02" style={{ marginBottom: 'clamp(24px, 4vw, 48px)' }}>
            <h2>Schnuppertage</h2>
            <div style={{ marginTop: '16px' }}>
              {eventsLoading ? (
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Events werden geladen…</p>
              ) : (
                <div className="events-list">
                  {schnuppertageEvents.slice(0, 3).map((item, index) => (
                    <AktuellesItemComponent 
                      key={item.id || index} 
                      item={item} 
                      variant="event"
                      onClick={handleItemClick}
                    />
                  ))}
                  {schnuppertageEvents.length === 0 && (
                    <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Aktuell sind keine Schnuppertage geplant.</p>
                  )}
                  {schnuppertageEvents.length > 3 && (
                    <div style={{ marginTop: '24px', textAlign: 'center' }}>
                      <Link href="/aktuelles" className="btn btn-secondary btn-organic" style={{ display: 'inline-block' }}>
                        Alle Schnuppertage
                      </Link>
                    </div>
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
                  border: 'none',
                  boxShadow: 'var(--shadow-sm)',
                }}
              >
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', marginBottom: '8px', color: '#fff' }}>
                  Neugierig? Schau vorbei und mach mit. Erlebe an unseren Schnuppertagen, wie solidarische Landwirtschaft funktioniert – direkt auf dem Geisshof in Gebenstorf, umgeben von Natur, Wildpflanzen, auf unserem sonnigen Feld. Als Dank für deine Mithilfe erhältst du eine Tasche frisch geerntetes Demeter-Gemüse und ein kleines zVieri spendiert.
                </p>
                <Link
                  href="/mitmachen"
                  className="btn btn-secondary btn-organic"
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

          <section id="G-02b" style={{ marginBottom: 'clamp(24px, 4vw, 48px)' }}>
            <h2>Weitere Events</h2>
            <div style={{ marginTop: '16px' }}>
              {eventsLoading ? (
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Events werden geladen…</p>
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
                    <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>Aktuell sind keine weiteren Events geplant.</p>
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
