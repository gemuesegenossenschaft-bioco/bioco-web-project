'use client'

import Image from 'next/image'
import Link from 'next/link'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import { getAktuellesItems } from '@/components/AktuellesData'
import { AktuellesItemComponent } from '@/components/AktuellesItem'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import { PeaBullet } from '@/components/PeaBullet'

export default function Home() {
  const { upcoming: eventItems, isLoading: eventsLoading } = useEventsFeed(6)
  const schnuppertageEvents = eventItems.filter((item) =>
    (item.title || '').toLowerCase().includes('schnuppertag')
  )

  return (
    <div className="home-bleed">
      <Header />

      {/* Bleed Hero */}
      <section className="bleed-hero" aria-labelledby="hero-title">
        <div className="bleed-hero-image">
          <Image
            src="/images/hero/header_homepage.JPG"
            alt="Solidarische Landwirtschaft auf dem Feld in Gebenstorf"
            fill
            priority
            style={{ objectFit: 'cover' }}
          />
          <div className="bleed-hero-overlay" />
        </div>
        <div className="bleed-hero-text">
          <h1 id="hero-title"># Echt. Nah. Dein Anteil.</h1>
          <p>
            Gemeinsam Gemüse anbauen und geniessen – solidarische Landwirtschaft
            in der Region Baden-Brugg.
          </p>
        </div>
      </section>

      <main className="home-main">
        <div className="single-column">
          <section className="text-block">
            <h2>Willkommen bei biocò</h2>
            <p>
              Seit 2014 bewirtschaften wir den <Link href="/wir">Geisshof in Gebenstorf</Link> nach
              biologisch-dynamischen Prinzipien und liefern <Link href="/gemuese">Demeter-Gemüse</Link>{' '}
              in höchster Bio-Qualität. Hier wächst Woche für Woche eine vielfältige Auswahl an
              saisonalem Gemüse aus <Link href="/solawi">solidarischer Landwirtschaft</Link>, das wir
              gemeinsam anbauen, pflegen und ernten. Jedes <Link href="/mitmachen">Mitglied</Link>{' '}
              bringt sich ein, ob auf dem <Link href="/mitmachen">Feld</Link>, in der{' '}
              <Link href="/mitmachen">Logistik</Link> oder bei der <Link href="/mitmachen">Organisation</Link>.
            </p>
            <p>
              Bei uns teilen wir nicht nur die Ernte, sondern auch die Verantwortung und die Freude an
              der Arbeit. Produzentinnen und Konsumentinnen arbeiten Hand in Hand, gestalten gemeinsam
              den <Link href="/gemuese">Anbau</Link> und erleben, wie aus einem Samen frisches
              Bio-Gemüse wird, das ab 16:00 Uhr abholbereit in den{' '}
              <Link href="/standorte-depots">Depots in Baden, Brugg und Gebenstorf</Link> abgeholt
              werden kann.
            </p>
          </section>

          <section className="text-block">
            <h2>Das ist drin: Saisonal &amp; Demeter</h2>
            <p>
              Wöchentlich erhalten unsere Mitglieder ein <Link href="/abos">Gemüseabo</Link> mit
              frischem, saisonalem <Link href="/gemuese">Demeter-Gemüse</Link>.
            </p>
            <ul className="pea-bullet-list">
              <PeaBullet>Wöchentlicher Gemüsekorb</PeaBullet>
              <PeaBullet>Saisonalität – das Gemüse der Jahreszeit</PeaBullet>
              <PeaBullet>Demeter-Qualität – höchste Bio-Standards</PeaBullet>
            </ul>
          </section>

          <section className="text-block">
            <h2>Gemeinschaft &amp; Solidarität</h2>
            <div className="torn-image-frame" style={{ marginBottom: '16px' }}>
              <Image
                src="/images/gemeinschaft/bioco_kinder.JPG"
                alt="Kinder bei solidarischer Landwirtschaft auf dem Geisshof Gebenstorf"
                width={1200}
                height={800}
                className="torn-image"
              />
            </div>
            <p>
              biocò basiert auf den Prinzipien der <Link href="/solawi">Solidarischen Landwirtschaft</Link>.
            </p>
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

          {/* Aktuelles - allowed 2-column cards */}
          <section className="text-block">
            <h2>Aktuelles</h2>
            <div className="card-grid two-col">
              {getAktuellesItems().map((item, index) => (
                <div key={item.id || index} className="wobbly-card clickable">
                  <AktuellesItemComponent item={item} variant="aktuelles" />
                </div>
              ))}
            </div>
            <div className="cta-inline">
              <Link href="/aktuelles" className="btn btn-primary">
                Alle Neuigkeiten ansehen
              </Link>
            </div>
          </section>

          {/* Schnuppertage - allowed 2-column cards */}
          <section className="text-block">
            <h2>Schnuppertage</h2>
            {eventsLoading ? (
              <p style={{ color: 'var(--text-secondary)' }}>Events werden geladen…</p>
            ) : (
              <div className="card-grid two-col">
                {schnuppertageEvents.length > 0 ? (
                  schnuppertageEvents.map((item, index) => (
                    <div key={item.id || index} className="wobbly-card clickable">
                      <AktuellesItemComponent item={item} variant="event" />
                    </div>
                  ))
                ) : (
                  <p style={{ color: 'var(--text-secondary)' }}>
                    Aktuell sind keine Schnuppertage geplant.
                  </p>
                )}
              </div>
            )}
            <div className="cta-inline">
              <Link href="/mitmachen" className="btn btn-primary">
                Alle Schnuppertage ansehen
              </Link>
            </div>
          </section>

          {/* Ablauf */}
          <section className="text-block">
            <h2>Wie funktioniert&apos;s?</h2>
            <ol className="steps-list">
              <li>
                <strong>Anmelden als Mitglied oder Schnupperabo.</strong> Entscheide dich für ein{' '}
                <Link href="/abos">Abo</Link> oder teste mit einem <Link href="/mitmachen">Schnupperabo</Link>.
              </li>
              <li>
                <strong>Rechnung bezahlen.</strong> Du erhältst eine Rechnung und bezahlst den Beitrag
                für dein Abo.
              </li>
              <li>
                <strong>Arbeitseinsätze planen.</strong> Organisiere deine{' '}
                <Link href="/mitmachen">Mitarbeit auf dem Feld</Link> oder in der Logistik.
              </li>
              <li>
                <strong>Gemüse abholen.</strong> Wöchentlich holst du deinen Gemüsekorb in einem der{' '}
                <Link href="/standorte-depots">Standorte</Link> ab.
              </li>
              <li>
                <strong>Geniessen und teilen.</strong> Geniesse dein frisches Gemüse und teile deine
                Erlebnisse mit uns auf{' '}
                <a href="https://www.instagram.com/bioco.ch" target="_blank" rel="noopener noreferrer">
                  Instagram
                </a>.
              </li>
            </ol>
          </section>

          {/* Kennenlernen */}
          <section className="text-block">
            <h2>Möchtest du uns kennenlernen?</h2>
            <p>
              Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten
              können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen
              kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir
              beantworten deine Fragen persönlich.
            </p>
            <div className="cta-inline">
              <CTA text="Nimm Kontakt auf" href="/kontakt" variant="primary" />
              <CTA text="Zu uns finden" href="/standorte-depots" variant="secondary" />
            </div>
          </section>
        </div>
      </main>

      <Footer />
    </div>
  )
}
