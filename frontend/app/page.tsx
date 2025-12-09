'use client'

import Image from 'next/image'
import Link from 'next/link'
import { useState } from 'react'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { getAktuellesItems, AktuellesItem } from '@/components/AktuellesData'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { ItemDetailModal } from '@/components/ItemDetailModal'
import { PeaBullet } from '@/components/PeaBullet'
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

  const illustrations = [
    { src: '/images/illustrations/aubergine.svg', left: '6%', top: '18%', size: 120, rotate: -6, opacity: 0.08 },
    { src: '/images/illustrations/blumenkohl_mit_vogel.svg', left: '78%', top: '12%', size: 160, rotate: 4, opacity: 0.06 },
    { src: '/images/illustrations/fenchel.svg', left: '12%', top: '48%', size: 140, rotate: -3, opacity: 0.08 },
    { src: '/images/illustrations/kohlrabi.svg', left: '70%', top: '42%', size: 130, rotate: 8, opacity: 0.07 },
    { src: '/images/illustrations/lauch_mit_schnecke.svg', left: '18%', top: '72%', size: 150, rotate: -5, opacity: 0.06 },
    { src: '/images/illustrations/radieschen.svg', left: '82%', top: '68%', size: 110, rotate: 3, opacity: 0.07 },
    { src: '/images/illustrations/ruebli.svg', left: '44%', top: '30%', size: 130, rotate: -2, opacity: 0.07 },
    { src: '/images/illustrations/schmetterling.svg', left: '56%', top: '16%', size: 80, rotate: 10, opacity: 0.05 },
    { src: '/images/illustrations/schmetterling2.svg', left: '30%', top: '60%', size: 90, rotate: -8, opacity: 0.05 },
    { src: '/images/illustrations/zwiebel.svg', left: '60%', top: '80%', size: 120, rotate: 6, opacity: 0.06 },
  ]

  return (
    <div className="page-shell">
      <Header />

      {/* Hero */}
      <section className="hero-bleed">
        <div className="hero-bg">
          <Image
            src="/images/hero/bioco_hero-junge-mit-kuerbis.JPG"
            alt="Solidarische Landwirtschaft auf dem Feld"
            fill
            priority
            style={{ objectFit: 'cover' }}
          />
          <div className="hero-overlay" />
          <div className="hero-wave" />
        </div>
      </section>

      {/* Headline below wave */}
      <section className="hero-headline-block">
        <h1 className="hero-headline"># Echt. Nah. Dein Anteil.</h1>
        <p className="hero-subline">Solidarische Landwirtschaft in der Region Baden-Brugg.</p>
      </section>

      {/* Scattered illustrations */}
      <div className="scatter-layer" aria-hidden>
        {illustrations.map((item, idx) => (
          <img
            key={idx}
            src={item.src}
            alt=""
            className="scatter-item"
            style={{
              left: item.left,
              top: item.top,
              width: item.size,
              opacity: item.opacity,
              transform: `rotate(${item.rotate}deg)`
            }}
          />
        ))}
      </div>

      {/* Main content */}
      <main className="home-container">
        <div className="home-grid-12">
          {/* Willkommen */}
          <section className="home-block col-span-12">
            <h2>Willkommen bei biocò</h2>
            <div className="text-body">
              <p>
                Seit 2014 bewirtschaften wir den <Link href="/wir">Geisshof in Gebenstorf</Link> nach biologisch-dynamischen Prinzipien und liefern <Link href="/gemuese">Demeter-Gemüse</Link> in höchster Bio-Qualität. Hier wächst Woche für Woche eine vielfältige Auswahl an saisonalem Gemüse aus <Link href="/solawi">solidarischer Landwirtschaft</Link>, das wir gemeinsam anbauen, pflegen und ernten. Jedes <Link href="/mitmachen">Mitglied</Link> bringt sich ein, ob auf dem <Link href="/mitmachen">Feld</Link>, in der <Link href="/mitmachen">Logistik</Link> oder bei der <Link href="/mitmachen">Organisation</Link>.
              </p>
              <p>
                Bei uns teilen wir nicht nur die Ernte, sondern auch die Verantwortung und die Freude an der Arbeit. Das ist <Link href="/solawi">solidarische Landwirtschaft</Link> in der Region Baden: Produzentinnen und Konsumentinnen arbeiten Hand in Hand, gestalten gemeinsam den <Link href="/gemuese">Anbau</Link> und erleben, wie aus einem Samen frisches Bio-Gemüse wird, das ab 16:00 Uhr abholbereit in den <Link href="/standorte-depots">Depots in Baden, Brugg und Gebenstorf</Link> abgeholt werden kann.
              </p>
            </div>
          </section>

          {/* Das ist drin */}
          <section className="home-block col-span-12 md-col-span-6">
            <h2>Das ist drin: Saisonal &amp; Demeter</h2>
            <p>
              Wöchentlich erhalten unsere Mitglieder ein <Link href="/abos">Gemüseabo</Link> mit frischem, saisonalem <Link href="/gemuese">Demeter-Gemüse</Link>.
            </p>
            <ul className="pea-bullet-list">
              <PeaBullet>Wöchentlicher Gemüsekorb</PeaBullet>
              <PeaBullet>Saisonalität – das Gemüse der Jahreszeit</PeaBullet>
              <PeaBullet>Demeter-Qualität – höchste Bio-Standards</PeaBullet>
            </ul>
          </section>
          <div className="home-block col-span-12 md-col-span-6 blob-visual">
            <div className="veg-stack">
              <Image
                src="/images/illustrations/ruebli.svg"
                alt="Rüebli"
                fill
                priority
                className="veg-item veg-carrot"
              />
              <Image
                src="/images/illustrations/radieschen.svg"
                alt="Radieschen"
                fill
                priority
                className="veg-item veg-radieschen"
              />
              <Image
                src="/images/illustrations/kohlrabi.svg"
                alt="Kohlrabi"
                fill
                priority
                className="veg-item veg-kohlrabi"
              />
              <Image
                src="/images/illustrations/fenchel.svg"
                alt="Fenchel"
                fill
                priority
                className="veg-item veg-fenchel"
              />
            </div>
          </div>

          {/* Gemeinschaft */}
          <section className="home-block col-span-12 md-col-span-6">
            <h2>Gemeinschaft &amp; Solidarität</h2>
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
          <div className="home-block col-span-12 md-col-span-6 blob-visual">
            <div className="veg-stack">
              <Image
                src="/images/illustrations/blumenkohl_mit_vogel.svg"
                alt="Blumenkohl mit Vogel"
                fill
                priority
                className="veg-item veg-cauliflower"
              />
              <Image
                src="/images/illustrations/zwiebel.svg"
                alt="Zwiebel"
                fill
                priority
                className="veg-item veg-onion"
              />
              <Image
                src="/images/illustrations/schmetterling2.svg"
                alt="Schmetterling"
                fill
                priority
                className="veg-item veg-butterfly"
              />
            </div>
          </div>

          {/* Aktuelles */}
          <section className="home-block col-span-12 md-col-span-6 wavy-card">
            <h2>Aktuelles</h2>
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
            <Link href="/aktuelles" className="btn btn-primary btn-organic" style={{ marginTop: '16px', display: 'inline-block' }}>
              Alle Neuigkeiten ansehen
            </Link>
          </section>

          {/* Schnuppertage */}
          <section className="home-block col-span-12 md-col-span-6 wavy-card">
            <h2>Schnuppertage</h2>
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
            <Link
              href="/mitmachen"
              className="btn btn-primary btn-organic"
              style={{ marginTop: '16px', display: 'inline-block' }}
            >
              Alle Schnuppertage ansehen
            </Link>
          </section>

          {/* Wie funktioniert's */}
          <section className="home-block col-span-12">
            <h2>Wie funktioniert&apos;s?</h2>
            <ol className="steps-list">
              <li>
                <strong>Anmelden als Mitglied oder Schnupperabo.</strong> Entscheide dich für ein <Link href="/abos">Abo</Link> oder teste mit einem <Link href="/mitmachen">Schnupperabo</Link>.
              </li>
              <li>
                <strong>Rechnung bezahlen.</strong> Du erhältst eine Rechnung und bezahlst den Beitrag für dein Abo.
              </li>
              <li>
                <strong>Arbeitseinsätze planen.</strong> Organisiere deine <Link href="/mitmachen">Mitarbeit auf dem Feld</Link> oder in der Logistik.
              </li>
              <li>
                <strong>Gemüse abholen.</strong> Wöchentlich holst du deinen Gemüsekorb in einem der <Link href="/standorte-depots">Standorte</Link> ab.
              </li>
              <li>
                <strong>Geniessen und teilen.</strong> Geniesse dein frisches Gemüse und teile deine Erlebnisse mit uns auf <a href="https://www.instagram.com/bioco.ch" target="_blank" rel="noopener noreferrer">Instagram</a>.
              </li>
            </ol>
          </section>

          {/* Kennenlernen */}
          <section className="home-block col-span-12">
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
