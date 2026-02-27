'use client'

import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { CTA } from '@/components/CTA'
import Image from 'next/image'
import Link from 'next/link'
import type { ContentSection } from '@/lib/processwire-types'

interface TimelineItem {
  year: string
  title: string
  description: string
  links?: Array<{ text: string; href: string }>
}

interface WirClientProps {
  intro: {
    title: string
    text: string
  }
  sections: ContentSection[]
  timeline?: TimelineItem[]
}


export function WirClient({ intro, sections, timeline }: WirClientProps) {
  const getSection = (id: string) => sections.find(s => s.id === id)
  const stripHtml = (value?: string | null) => String(value || '').replace(/<[^>]*>/g, '').trim()
  const extractYear = (value?: string | null): string => {
    const text = String(value || '')
    const match = text.match(/\b(19|20)\d{2}(?:\s*[-–]\s*(19|20)?\d{2})?\b/)
    return match ? match[0].replace(/\s+/g, '') : ''
  }
  const getTeamDisplayName = (alt: string, index: number) => {
    const raw = String(alt || '').trim()
    if (!raw) return `Team ${index + 1}`
    const firstChunk = raw.split(/[|,–-]/)[0]?.trim() || raw
    return firstChunk || `Team ${index + 1}`
  }

  function getSectionImages(section?: ContentSection | null, fallbackAlt?: string) {
    if (!section) return []
    var images: Array<{ url: string; alt: string }> = []
    var seen = new Set<string>()
    var baseTitle = section.title || ''

    function push(url?: string | null, alt?: string | null) {
      const safeUrl = String(url || '').trim()
      if (!safeUrl || seen.has(safeUrl)) return
      seen.add(safeUrl)
      images.push({ url: safeUrl, alt: String(alt || fallbackAlt || baseTitle || '').trim() || 'Bild' })
    }

    if (Array.isArray(section.images)) {
      section.images.forEach((img) => push(img?.url, img?.alt))
    }
    if (Array.isArray(section.media)) {
      section.media
        .filter((m) => m && m.type === 'image')
        .forEach((m) => push(m.url, m.alt))
    }
    push(section.image, section.imageAlt)

    return images
  }
  
  const wirSection = getSection('wir')
  const alleMitgliederSection = getSection('alle_mitglieder')
  const betriebsgruppeSection = getSection('betriebsgruppe')
  const hofTeamSection = getSection('hof_team') || getSection('team')
  const hofSection = getSection('geisshof')
  const missionSection = getSection('mission')
  const solidaritaetSection = getSection('solidaritaet')
  const nachhaltigkeitSection = getSection('nachhaltigkeit')
  const gemeinschaftSection = getSection('gemeinschaft')
  const regionalitaetSection = getSection('regionalitaet')
  const gottiSection = getSection('gotti')
  const geschichteSection = getSection('geschichte')
  const timelineHeaderSection =
    getSection('timeline') ||
    sections.find((s) => ['timeline', 'timeline_header'].includes(String(s.component || '').toLowerCase())) ||
    sections.find((s) => ['timeline', 'timeline_header'].includes(String(s.layout || '').toLowerCase())) ||
    sections.find((s) => String(s.title || '').toLowerCase() === 'timeline')
  
  // Parse timeline items from CMS in a flexible way:
  // - id starts with timeline_...
  // - component/layout marked as timeline item
  // - year is present in eyebrow/title/id (editor-friendly)
  const cmsTimelineItems: TimelineItem[] = sections
    .filter((s) => {
      const id = String(s.id || '').toLowerCase()
      const component = String(s.component || '').toLowerCase()
      const layout = String(s.layout || '').toLowerCase()
      const title = stripHtml(s.title).toLowerCase()
      const hasTimelineMarker =
        id.startsWith('timeline_') ||
        id.startsWith('timeline-') ||
        component === 'timeline_item' ||
        component === 'timeline-item' ||
        layout === 'timeline_item' ||
        layout === 'timeline-item'
      const hasYearSignal = !!extractYear(s.eyebrow || s.title || s.id)
      const looksLikeHeaderOnly = title === 'timeline'
      return (hasTimelineMarker || hasYearSignal) && !looksLikeHeaderOnly
    })
    .map(s => {
      const year =
        extractYear(s.eyebrow) ||
        extractYear(s.id?.replace(/^timeline[_-]?/i, '')) ||
        extractYear(s.title) ||
        ''
      const title = stripHtml(s.title) || (year ? `Eintrag ${year}` : 'Timeline-Eintrag')
      const item: TimelineItem = {
        year,
        title,
        description: stripHtml(s.text),
      }
      // Add links from buttons if present
      if (s.buttons && s.buttons.length > 0) {
        item.links = s.buttons.map(b => ({ text: b.text, href: b.href }))
      }
      return item
    })
    .sort((a, b) => {
      // Sort by year (handle ranges like "2019-2023")
      const yearA = parseInt(a.year.split('-')[0])
      const yearB = parseInt(b.year.split('-')[0])
      if (Number.isNaN(yearA) && Number.isNaN(yearB)) return a.title.localeCompare(b.title, 'de')
      if (Number.isNaN(yearA)) return 1
      if (Number.isNaN(yearB)) return -1
      return yearA - yearB
    })
  
  const timelineItems = cmsTimelineItems.length > 0 ? cmsTimelineItems : (timeline || [])

  // Get images with fallbacks
  const alleMitgliederImg = alleMitgliederSection?.images?.[0]?.url || alleMitgliederSection?.imageData?.url || alleMitgliederSection?.image || ''
  const alleMitgliederAlt = alleMitgliederSection?.images?.[0]?.alt || alleMitgliederSection?.imageAlt || 'Mitglieder der Gemüsegenossenschaft biocò'

  const betriebsgruppeImg = betriebsgruppeSection?.images?.[0]?.url || betriebsgruppeSection?.imageData?.url || betriebsgruppeSection?.image || ''
  const betriebsgruppeAlt = betriebsgruppeSection?.images?.[0]?.alt || betriebsgruppeSection?.imageAlt || 'Betriebsgruppe der Gemüsegenossenschaft biocò'
  
  // Hof-Team images from section.images array or fallback
  const hofTeamImageList =
    getSectionImages(hofTeamSection, 'Hof-Team').length > 0
      ? getSectionImages(hofTeamSection, 'Hof-Team')
      : getSectionImages(wirSection, 'Hof-Team')
  const hofTeamImages = hofTeamImageList.length
    ? hofTeamImageList.map((img, i) => ({
        url: img.url,
        alt: img.alt,
        name: getTeamDisplayName(img.alt, i),
      }))
    : []
  
  // Geisshof images from section.images array or fallback
  const geisshofImages = getSectionImages(hofSection, 'Geisshof')

  return (
    <>
      <Header />
      <main className="main-content">
        <div style={{ maxWidth: '1400px', margin: '0 auto', padding: 'clamp(48px, 8vw, 96px) clamp(16px, 6vw, 96px)' }}>
          
          {/* Page Header */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h1 style={{ fontSize: '2.5rem', margin: '0 0 16px 0' }}>biocò:<br />Die Gemüse-<br />genossenschaft</h1>
            {intro.text ? (
              <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                   dangerouslySetInnerHTML={{ __html: intro.text }} />
            ) : (
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof in Gebenstorf. 
                Lerne unser Team, unsere Geschichte und die Werte kennen, die unsere <Link href="/solawi">solidarische Landwirtschaft</Link> prägen.
              </p>
            )}
          </section>

          {/* Wir Section */}
          <section id="F-01" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>{wirSection?.title || 'Wir'}</h2>
            <h3 style={{ fontSize: '1.5rem', marginTop: '16px', marginBottom: '12px' }}>Team & Hof</h3>
            {wirSection?.text ? (
              <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}
                   dangerouslySetInnerHTML={{ __html: wirSection.text }} />
            ) : (
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
                biocò ist eine Gemeinschaft von engagierten Menschen, die gemeinsam für frisches, regionales <Link href="/gemuese">Demeter-Gemüse</Link> sorgen.
              </p>
            )}
            
            {/* Team Grid */}
            <div className="wir-top-row" style={{ marginTop: '24px', marginBottom: '48px' }}>
              <div>
                {alleMitgliederImg && (
                  <div style={{ position: 'relative', width: '100%', aspectRatio: '4/3', marginBottom: '16px', borderRadius: '24px', overflow: 'hidden' }}>
                    <Image
                      src={alleMitgliederImg}
                      alt={alleMitgliederAlt}
                      fill
                      style={{ objectFit: 'cover', objectPosition: 'center top' }}
                    />
                  </div>
                )}
                <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>{alleMitgliederSection?.title || 'Alle Mitglieder'}</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  {alleMitgliederSection?.text ? (
                    <span dangerouslySetInnerHTML={{ __html: alleMitgliederSection.text }} />
                  ) : (
                    'Jede(r) Genossenschafter/in bringt sich ein – ob bei der Feldarbeit, in der Logistik oder bei Events.'
                  )}
                </p>
              </div>
              <div>
                {betriebsgruppeImg && (
                  <div style={{ position: 'relative', width: '100%', aspectRatio: '4/3', marginBottom: '16px', borderRadius: '24px', overflow: 'hidden' }}>
                    <Image
                      src={betriebsgruppeImg}
                      alt={betriebsgruppeAlt}
                      fill
                      style={{ objectFit: 'cover', objectPosition: 'center top' }}
                    />
                  </div>
                )}
                <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>{betriebsgruppeSection?.title || 'Betriebsgruppe (BG)'}</h3>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  {betriebsgruppeSection?.text ? (
                    <span dangerouslySetInnerHTML={{ __html: betriebsgruppeSection.text }} />
                  ) : (
                    'Die Betriebsgruppe koordiniert den Anbau, die Logistik und die Organisation der Genossenschaft.'
                  )}
                </p>
              </div>
            </div>

            {/* Hof-Team */}
            <div style={{ background: 'var(--surface-secondary, #f8f8f6)', borderRadius: '24px', padding: '24px' }}>
              <h4 style={{ fontSize: '1.1rem', color: 'var(--text-secondary)', marginBottom: '20px', fontWeight: '500' }}>
                {hofTeamSection?.title || 'Hof-Team'}
              </h4>
              {hofTeamImages.length > 0 ? (
                <div className="hof-team-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px', width: '100%', maxWidth: '500px' }}>
                  {hofTeamImages.map((member, i) => (
                    <div key={i}>
                      <div style={{ position: 'relative', width: '100%', aspectRatio: '3/4', marginBottom: '12px', borderRadius: '16px', overflow: 'hidden', background: 'var(--bg-tertiary, #eee)' }}>
                        <Image src={member.url} alt={member.alt} fill style={{ objectFit: 'contain', objectPosition: 'center' }} />
                      </div>
                      <h3 style={{ fontSize: '1.25rem' }}>{member.name}</h3>
                    </div>
                  ))}
                </div>
              ) : (
                <p style={{ color: 'var(--text-secondary)', margin: 0 }}>Noch keine Teamfotos hinterlegt.</p>
              )}
            </div>
          </section>

          {/* Der Geisshof */}
          <section id="F-01b" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>{hofSection?.title || 'Der Geisshof'}</h2>
            {hofSection?.text ? (
              <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}
                   dangerouslySetInnerHTML={{ __html: hofSection.text }} />
            ) : (
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
                Wir bewirtschaften einen Bio Bauernhof in Baden – genauer gesagt den Geisshof in Gebenstorf im Aargau. 
                Seit 2014 ist dieser Ort das Herzstück von biocò, wo wir Bio-Gemüse in Demeter-Qualität anbauen.
              </p>
            )}
              
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '24px', marginBottom: '24px' }}>
              {geisshofImages.map((img, i) => (
                <div key={i} style={{ position: 'relative', width: '100%', aspectRatio: '4/3', borderRadius: '24px', overflow: 'hidden' }}>
                  <Image src={img.url} alt={img.alt} fill style={{ objectFit: 'cover' }} />
                </div>
              ))}
            </div>
              
            <p style={{ marginTop: '16px' }}>
              <Link href="/standorte-depots" className="btn btn-secondary btn-organic" style={{ display: 'inline-block' }}>
                Anfahrtsweg zum Geisshof
              </Link>
            </p>
          </section>

          {/* Mission & Leitbild */}
          <section id="F-02" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>{missionSection?.title || 'Mission & Leitbild'}</h2>
            {missionSection?.text && (
              <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginTop: '16px' }}
                   dangerouslySetInnerHTML={{ __html: missionSection.text }} />
            )}
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '24px', marginTop: '24px' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>{solidaritaetSection?.title || 'Solidarität'}</h3>
                {solidaritaetSection?.text ? (
                  <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}
                       dangerouslySetInnerHTML={{ __html: solidaritaetSection.text }} />
                ) : (
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '12px' }}>
                    Wir teilen Arbeit und Ertrag. Solidarische Landwirtschaft bedeutet, dass Produzentinnen und Konsumentinnen zusammenarbeiten.
                  </p>
                )}
                <p style={{ marginTop: '12px' }}>
                  <Link href="/solawi" className="btn btn-secondary btn-organic" style={{ display: 'inline-block', fontSize: '0.875rem' }}>
                    → Mehr über solidarische Landwirtschaft
                  </Link>
                </p>
              </div>
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>{nachhaltigkeitSection?.title || 'Nachhaltigkeit'}</h3>
                {nachhaltigkeitSection?.text ? (
                  <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                       dangerouslySetInnerHTML={{ __html: nachhaltigkeitSection.text }} />
                ) : (
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                    Wir arbeiten nach biologisch-dynamischen Prinzipien (Demeter) und fördern Biodiversität, Kreislaufwirtschaft und gesunde Böden.
                  </p>
                )}
              </div>
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>{gemeinschaftSection?.title || 'Gemeinschaft'}</h3>
                {gemeinschaftSection?.text ? (
                  <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                       dangerouslySetInnerHTML={{ __html: gemeinschaftSection.text }} />
                ) : (
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                    biocò lebt von der Gemeinschaft. Jede(r) bringt sich ein, lernt voneinander und gestaltet die Genossenschaft aktiv mit.
                  </p>
                )}
              </div>
              <div>
                <h3 style={{ fontSize: '1.25rem', marginBottom: '12px' }}>{regionalitaetSection?.title || 'Regionalität'}</h3>
                {regionalitaetSection?.text ? (
                  <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                       dangerouslySetInnerHTML={{ __html: regionalitaetSection.text }} />
                ) : (
                  <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                    Unser Gemüse wächst direkt in der Region Baden-Brugg. Kurze Wege, frische Ernte, lokale Verbundenheit.
                  </p>
                )}
              </div>
            </div>

            <div style={{ marginTop: '32px' }}>
              <h3 style={{ fontSize: '1.5rem', marginBottom: '12px' }}>{gottiSection?.title || 'Gotti-System'}</h3>
              {gottiSection?.text ? (
                <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}
                     dangerouslySetInnerHTML={{ __html: gottiSection.text }} />
              ) : (
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Neumitglieder werden von einem &quot;Gotti&quot; oder &quot;Götti&quot; (Paten) begleitet. Dieses System hilft neuen Mitgliedern, 
                  sich in der Genossenschaft zurechtzufinden.
                </p>
              )}
            </div>
          </section>

          {/* Geschichte */}
          <section id="F-03" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>{geschichteSection?.title || 'Geschichte'}</h2>
            {geschichteSection?.text ? (
              <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}
                   dangerouslySetInnerHTML={{ __html: geschichteSection.text }} />
            ) : (
              <>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                  Die Gemüsegenossenschaft biocò wurde 2014 in Gebenstorf im Aargau gegründet.
                </p>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                  Gestartet wurde auf dem Geisshof in Gebenstorf, wo wir bis heute unser Gemüse anbauen.
                </p>
                <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)' }}>
                  Heute versorgen wir Mitglieder in der Region Baden-Brugg wöchentlich mit frischem Demeter-Gemüse.
                </p>
              </>
            )}
          </section>

          {/* Timeline */}
          <section id="F-04" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>{timelineHeaderSection?.title || 'Timeline'}</h2>
            {timelineHeaderSection?.text && (
              <div style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginTop: '16px' }}
                   dangerouslySetInnerHTML={{ __html: timelineHeaderSection.text }} />
            )}
            <div className="timeline" style={{ marginTop: '24px' }}>
              {timelineItems.map((item, i) => (
                <div key={i} className="timeline-item">
                  <div className="timeline-year">{item.year || '•'}</div>
                  <div className="timeline-content">
                    <h3>{item.title}</h3>
                    <p>{item.description}</p>
                    {item.links && (
                      <div style={{ marginTop: '12px', display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                        {item.links.map((link, j) => (
                          <a key={j} href={link.href} target="_blank" rel="noopener noreferrer" 
                             className="btn btn-secondary btn-organic" style={{ display: 'inline-block', fontSize: '0.875rem' }}>
                            {link.text}
                          </a>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </section>

          {/* Mitmachen CTA */}
          <section style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Mitmachen?</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Werde Teil unserer Gemeinschaft und unterstütze die solidarische Landwirtschaft.
            </p>
            <CTA text="Jetzt Mitglied werden" href="/mitmachen" variant="primary" />
          </section>

          {/* Kennenlernen */}
          <section id="B-06" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
            <h2>Möchtest du uns kennenlernen?</h2>
            <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '24px' }}>
              Es können viele Fragen auftauchen. Du hast die Möglichkeit, den Hof und uns an den Schnuppertagen kennenzulernen 
              oder dich via Kontaktformular bei uns zu melden.
            </p>
            <div style={{ marginTop: '16px', display: 'flex', gap: 'var(--spacing-md)', flexWrap: 'wrap' }}>
              <CTA text="Nimm Kontakt auf" href="/kontakt" variant="primary" />
              <CTA text="Zu uns finden" href="/standorte-depots" variant="secondary" />
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  )
}
