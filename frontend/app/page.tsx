'use client'

import { useState } from 'react'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { getAktuellesItems, AktuellesItem } from '@/components/AktuellesData'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import { PeaBullet } from '@/components/PeaBullet'
import Image from 'next/image'
import Link from 'next/link'
import { useEventsFeed } from '@/hooks/useEventsFeed'

export default function Home() {
  const [selectedItem, setSelectedItem] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)

  const { upcoming: eventItems, isLoading: eventsLoading } = useEventsFeed(3)
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

  // Illustration assets for scattering
  const illustrations = [
    '/images/illustrations/aubergine.svg',
    '/images/illustrations/blumenkohl_mit_vogel.svg',
    '/images/illustrations/fenchel.svg',
    '/images/illustrations/kohlrabi.svg',
    '/images/illustrations/lauch_mit_schnecke.svg',
    '/images/illustrations/radieschen.svg',
    '/images/illustrations/ruebli.svg',
    '/images/illustrations/schmetterling.svg',
    '/images/illustrations/schmetterling2.svg',
    '/images/illustrations/zwiebel.svg',
  ]

  return (
    <>
      {/* Full-Bleed Hero Section */}
      <section className="home-bleed-hero">
        <div className="hero-bg-image">
          <Image
            src="/images/hero/header_homepage.JPG"
            alt="Geisshof in Gebenstorf"
            fill
            priority
            style={{ objectFit: 'cover' }}
          />
          <div className="hero-gradient-overlay"></div>
        </div>
        <div className="hero-content">
          <h1 className="hero-headline">
            Gemeinsam Gemüse anbauen und geniessen.<br />
            <span className="hero-title-secondary">Solidarische Landwirtschaft in der Region Baden-Brugg.</span>
          </h1>
        </div>
        <div className="hero-torn-edge"></div>
      </section>

      {/* Navbar Overlay */}
      <div className="navbar-overlay">
        <Header />
      </div>

      {/* Main Content - 2 Column Grid */}
      <main className="home-main-content">
        {/* Scattered Illustrations Background */}
        <div className="illustrations-scatter">
          {illustrations.map((src, index) => (
            <img
              key={index}
              src={src}
              alt=""
              className="scattered-illustration"
              style={{
                position: 'absolute',
                left: `${10 + (index * 9)}%`,
                top: `${15 + (index * 8)}%`,
                width: `${100 + (index % 4) * 60}px`,
                opacity: 0.12 + (index % 3) * 0.05,
                transform: `rotate(${(index % 7) * 15 - 30}deg)`,
                zIndex: 1,
                pointerEvents: 'none',
              }}
            />
          ))}
        </div>

        <div className="home-grid">
          
          {/* Welcome Section - Full Width */}
          <section className="home-section home-section-full">
            <h2 className="home-section-title">Willkommen bei biocò</h2>
            <div className="home-text-content">
              <p>
                Seit 2014 bewirtschaften wir den <Link href="/wir">Geisshof in Gebenstorf</Link> nach biologisch-dynamischen 
                Prinzipien und liefern <Link href="/gemuese">Demeter-Gemüse</Link> in höchster Bio-Qualität. Hier wächst Woche für 
                Woche eine vielfältige Auswahl an saisonalem Gemüse aus <Link href="/solawi">solidarischer Landwirtschaft</Link>, 
                das wir gemeinsam anbauen, pflegen und ernten. Jedes <Link href="/mitmachen">Mitglied</Link> bringt sich ein, ob auf 
                dem <Link href="/mitmachen">Feld</Link>, in der <Link href="/mitmachen">Logistik</Link> oder bei der <Link href="/mitmachen">Organisation</Link>.
              </p>
              <p>
                Bei uns teilen wir nicht nur die Ernte, sondern auch die Verantwortung und die Freude 
                an der Arbeit. Das ist <Link href="/solawi">solidarische Landwirtschaft</Link> in der Region Baden: Produzentinnen 
                und Konsumentinnen arbeiten Hand in Hand, gestalten gemeinsam den <Link href="/gemuese">Anbau</Link> und erleben, 
                wie aus einem Samen frisches Bio-Gemüse wird, das ab 16:00 uhr abholbereit in den 
                <Link href="/standorte-depots"> Depots in Baden, Brugg und Gebenstorf</Link> abgeholt werden kann.
              </p>
            </div>
          </section>

          {/* Das ist drin - 2 Column */}
          <section className="home-section home-section-left">
            <h2 className="home-section-title">Das ist drin: Saisonal & Demeter</h2>
            <p>Wöchentlich erhalten unsere Mitglieder ein <Link href="/abos">Gemüseabo</Link> mit frischem, saisonalem <Link href="/gemuese">Demeter-Gemüse</Link>.</p>
            <ul className="pea-bullet-list">
              <PeaBullet>Wöchentlicher Gemüsekorb</PeaBullet>
              <PeaBullet>Saisonalität – das Gemüse der Jahreszeit</PeaBullet>
              <PeaBullet>Demeter-Qualität – höchste Bio-Standards</PeaBullet>
            </ul>
          </section>

          <div className="home-section home-section-right">
            {/* Empty visual column or illustration */}
          </div>

          {/* Gemeinschaft - 2 Column with Image */}
          <section className="home-section home-section-left">
            <h2 className="home-section-title">Gemeinschaft & Solidarität</h2>
            <p>biocò basiert auf den Prinzipien der <Link href="/solawi">Solidarischen Landwirtschaft</Link>.</p>
            <ul className="pea-bullet-list">
              <PeaBullet>
                <strong>Mitarbeit</strong> – <Link href="/mitmachen">Mitmachen auf dem Feld</Link>
              </PeaBullet>
              <PeaBullet>
                <strong>Transparenz</strong> – <Link href="/solawi">Solidarische Landwirtschaft</Link>
              </PeaBullet>
              <PeaBullet>
                <strong>Gemeinschaft</strong> – Jede(r) bringt sich ein
              </PeaBullet>
              <PeaBullet>
                <strong>Lokal/Region</strong> – <Link href="/wir">Hof: Geisshof</Link>
              </PeaBullet>
            </ul>
          </section>

          <div className="home-section home-section-right torn-edge-element">
            <Image
              src="/images/gemeinschaft/bioco_kinder.JPG"
              alt="Kinder bei solidarischer Landwirtschaft auf dem Geisshof Gebenstorf"
              width={800}
              height={600}
              style={{ width: '100%', height: 'auto' }}
            />
          </div>

          {/* Aktuelles - Wobbly Card */}
          <section className="home-section home-section-left wobbly-card torn-edge-element">
            <h2 className="home-section-title">Aktuelles</h2>
            <div className="aktuelles-list">
              {getAktuellesItems().map((item, index) => (
                <AktuellesItemComponent 
                  key={item.id || index} 
                  item={item} 
                  variant="aktuelles"
                  onClick={handleItemClick}
                />
              ))}
            </div>
            <Link href="/aktuelles" className="btn btn-primary btn-organic torn-edge-element" style={{ marginTop: '16px', display: 'inline-block' }}>
              Alle Neuigkeiten ansehen
            </Link>
          </section>

          {/* Schnuppertage - Wobbly Card */}
          <section className="home-section home-section-right wobbly-card torn-edge-element">
            <h2 className="home-section-title">Schnuppertage</h2>
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
                  <p style={{ color: 'var(--text-secondary)' }}>
                    Aktuell sind keine Schnuppertage geplant.
                  </p>
                )}
              </div>
            )}
            <Link
              href="/mitmachen"
              className="btn btn-primary btn-organic torn-edge-element"
              style={{ marginTop: '16px', display: 'inline-block' }}
            >
              Alle Schnuppertage ansehen
            </Link>
          </section>

          {/* Wie funktioniert's - Full Width */}
          <section className="home-section home-section-full">
            <h2 className="home-section-title">Wie funktioniert's?</h2>
            <div className="procedure-steps">
              <div className="procedure-step">
                <div className="step-icon">1</div>
                <div className="step-content">
                  <h3>Anmelden als Mitglied oder Schnupperabo</h3>
                  <p>Entscheide dich für ein <Link href="/abos">Abo</Link> oder teste mit einem <Link href="/mitmachen">Schnupperabo</Link></p>
                </div>
              </div>
              <div className="procedure-step">
                <div className="step-icon">2</div>
                <div className="step-content">
                  <h3>Rechnung bezahlen</h3>
                  <p>Du erhältst eine Rechnung und bezahlst den Beitrag für dein Abo</p>
                </div>
              </div>
              <div className="procedure-step">
                <div className="step-icon">3</div>
                <div className="step-content">
                  <h3>Arbeitseinsätze planen</h3>
                  <p>Organisiere deine <Link href="/mitmachen">Mitarbeit auf dem Feld</Link> oder in der Logistik</p>
                </div>
              </div>
              <div className="procedure-step">
                <div className="step-icon">4</div>
                <div className="step-content">
                  <h3>Gemüse abholen</h3>
                  <p>Wöchentlich holst du deinen Gemüsekorb in einem der <Link href="/standorte-depots">Standorte</Link> ab</p>
                </div>
              </div>
              <div className="procedure-step">
                <div className="step-icon">5</div>
                <div className="step-content">
                  <h3>Geniessen und teilen</h3>
                  <p>Geniesse dein frisches Gemüse und teile deine Erlebnisse mit uns auf <a href="https://www.instagram.com/bioco.ch" target="_blank" rel="noopener noreferrer">Instagram</a></p>
                </div>
              </div>
            </div>
          </section>

          {/* Kennenlernen - Full Width */}
          <section className="home-section home-section-full">
            <h2 className="home-section-title">Möchtest du uns kennenlernen?</h2>
            <p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>
            <div style={{ marginTop: '16px', display: 'flex', gap: 'var(--spacing-md)', flexWrap: 'wrap' }}>
              <div className="torn-edge-element" style={{ display: 'inline-block' }}>
                <CTA
                  text="Nimm Kontakt auf"
                  href="/kontakt"
                  variant="primary"
                />
              </div>
              <div className="torn-edge-element" style={{ display: 'inline-block' }}>
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
