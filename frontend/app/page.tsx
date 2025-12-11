'use client'

import Image from 'next/image'
import Link from 'next/link'
import { useState } from 'react'
import { UtilityNavigation } from '@/components/UtilityNavigation'
import { PrimaryNavigation } from '@/components/SecondaryNavigation'
import { MobileMenu } from '@/components/MobileMenu'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { getAktuellesItems, AktuellesItem } from '@/components/AktuellesData'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import { ScrollToTopLink } from '@/components/ScrollToTopLink'
import { useEventsFeed } from '@/hooks/useEventsFeed'

export default function Home() {
  const [selectedItem, setSelectedItem] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)

  const { upcoming: eventItems, isLoading: eventsLoading } = useEventsFeed(6)
  const schnuppertageEvents = eventItems.filter((item) =>
    (item.title || '').toLowerCase().includes('schnuppertag')
  )

  const handleItemClick = (item: AktuellesItem) => {
    setSelectedItem(item)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setSelectedItem(null)
  }

  return (
    <div className="page-shell">
      {/* Secondary Navigation - Above hero */}
      <div className="hero-utility-nav">
        <UtilityNavigation />
      </div>
      {/* Hero */}
      <section className="hero-bleed">
        <div className="navbar-overlay">
          <PrimaryNavigation />
          <MobileMenu />
        </div>
        <div className="hero-bg">
          <Image
            src="/images/hero/bioco_hero-junge-mit-kuerbis.JPG"
            alt="Solidarische Landwirtschaft auf dem Feld"
            fill
            priority
            style={{ objectFit: 'cover' }}
          />
          <div className="hero-overlay" />
          <div className="hero-content">
            <h1 className="hero-headline">
              Gemeinsam Gemüse<br />
              anbauen und geniessen<br />
              <span className="hero-title-secondary">
                Solidarische Landwirtschaft<br />
                in der Region Baden-Brugg
              </span>
            </h1>
          </div>
        </div>
      </section>

      {/* Main content */}
      <main className="home-container">
        {/* Willkommen - Row 1, Two Columns */}
        <section className="two-column-section">
            <div className="two-column-text">
              <h2>Willkommen bei biocò</h2>
              <p>
                Bei der biocò Gemüsegenossenschaft teilen wir nicht nur die Ernte, sondern auch die Verantwortung und die Freude an der Arbeit. Das ist <Link href="/solawi">solidarische Landwirtschaft</Link> in der Region Baden: Produzentinnen und Konsumentinnen arbeiten Hand in Hand, gestalten gemeinsam den Anbau und erleben, wie aus einem Samen frisches Bio-Gemüse wird, das wöchentlich abholbereit in den <Link href="/standorte-depots">Depots in Baden, Brugg und Gebenstorf</Link> abgeholt werden kann.
              </p>
            </div>
            <div className="two-column-image">
              <Image
                src="/images/mitmachen/zusammen-arbeiten.JPG"
                alt="Gemeinschaft bei solidarischer Landwirtschaft biocò Baden-Brugg"
                fill
                priority
                style={{ objectFit: 'cover', borderRadius: '24px' }}
              />
            </div>
          </section>

          {/* Gemeinsam, solidarisch, frisch - Row 2, Two Columns */}
          <section className="two-column-section">
            <div className="two-column-image">
              <Image
                src="/images/ernte/bioco_ernte-kürbis-hoch.JPG"
                alt="Frisch geerntetes Demeter-Gemüse vom Geisshof"
                fill
                priority
                style={{ objectFit: 'cover', borderRadius: '24px' }}
              />
            </div>
            <div className="two-column-text">
              <h2>Gemeinsam, solidarisch, frisch</h2>
              <p>
                Seit 2014 bewirtschaften wir den <Link href="/wir">Geisshof in Gebenstorf</Link> nach biologisch-dynamischen Prinzipien und liefern <Link href="/gemuese">Demeter-Gemüse</Link> in höchster Bio-Qualität. Hier wächst Woche für Woche eine vielfältige Auswahl an saisonalem Gemüse aus <Link href="/solawi">solidarischer Landwirtschaft</Link>, das wir gemeinsam anbauen, pflegen und ernten. Jedes Mitglied bringt sich ein, ob auf dem Feld, in der Logistik oder bei der Organisation.
              </p>
              <div style={{ marginTop: '16px' }}>
                <CTA text="was bei uns wächst" href="/gemuese" variant="secondary" />
              </div>
            </div>
        </section>

        <div className="home-grid-12">
          {/* Aktuelles */}
          <section className="home-block col-span-12" style={{ marginTop: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Aktuelles</h2>
            <div className="aktuelles-list">
              {getAktuellesItems().slice(0, 3).map((item, index) => (
                <AktuellesItemComponent
                  key={item.id || index}
                  item={item}
                  variant="aktuelles"
                  onClick={handleItemClick}
                />
              ))}
            </div>
            <ScrollToTopLink href="/aktuelles" className="btn btn-primary btn-organic" style={{ marginTop: '16px', marginBottom: '16px', display: 'inline-block' }}>
              Alle Neuigkeiten ansehen
            </ScrollToTopLink>
          </section>

          {/* Schnuppertage */}
          <section className="home-block col-span-12" style={{ marginTop: 'clamp(24px, 4vw, 48px)' }}>
            <h2>Schnuppertage</h2>
            {eventsLoading ? (
              <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
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
                  <p style={{ color: 'var(--text-secondary)' }}>Aktuell sind keine Schnuppertage geplant.</p>
                )}
              </div>
            )}
            <ScrollToTopLink
              href="/mitmachen"
              className="btn btn-primary btn-organic"
              style={{ marginTop: '16px', marginBottom: '16px', display: 'inline-block' }}
            >
              Alle Schnuppertage ansehen
            </ScrollToTopLink>
          </section>

          {/* Kennenlernen */}
          <section className="home-block col-span-12" style={{ marginTop: 'clamp(24px, 4vw, 48px)' }}>
            <h2>Möchtest du uns kennenlernen?</h2>
            <p>
              Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.
            </p>
            <div className="cta-row">
              <CTA text="Nimm Kontakt auf" href="/kontakt" variant="primary" />
              <CTA text="Zu uns finden" href="/standorte-depots" variant="secondary" />
            </div>
          </section>
        </div>
      </main>

      <Footer />
      <ItemDetailModal item={selectedItem} isOpen={isModalOpen} onClose={handleCloseModal} />
    </div>
  )
}
