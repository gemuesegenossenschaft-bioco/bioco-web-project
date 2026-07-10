import Image from 'next/image'
import type { CSSProperties } from 'react'
import { CTA } from '@/components/CTA'
import { PersonIcons } from '@/components/PersonIcons'
import { getVeFieldAttrs } from '@/components/visual-editor/fieldAttrs'
import { SectionHeading } from '@/components/ui/SectionHeading'
import { Card } from '@/components/ui/Card'
import { getResolvedComponentConfig } from '@/lib/componentRegistry'
import type { ContentMedia, ContentSection, SectionConfigObject } from '@/lib/processwire-types'

interface RegisteredComponentProps {
  section: ContentSection
  visualEditor?: boolean
}

function hasHeadingHtml(html?: string | null): boolean {
  return /<h[1-6]\b[^>]*>/i.test(String(html || ''))
}

function getSectionMedia(section: ContentSection): ContentMedia[] {
  if (section.media?.length) return section.media
  if (section.image) {
    return [{
      url: section.image,
      alt: section.imageAlt || section.title,
      type: 'image',
    }]
  }
  return []
}

function getConfig(section: ContentSection): SectionConfigObject {
  return getResolvedComponentConfig(section.component, section.config)
}

function configValue(config: SectionConfigObject, key: string, fallback: string): string {
  const value = config[key]
  return value == null ? fallback : String(value)
}

function containerMaxWidth(width: string): string {
  switch (width) {
    case 'sm': return '640px'
    case 'md': return '820px'
    case 'lg': return '1040px'
    case 'xl': return '1280px'
    case 'full': return '100%'
    default: return '1040px'
  }
}

function contentWidth(width: string): string {
  switch (width) {
    case 'narrow': return '42rem'
    case 'wide': return '74rem'
    default: return '58rem'
  }
}

function gapSize(size: string): string {
  switch (size) {
    case 'sm': return '16px'
    case 'md': return '24px'
    case 'xl': return '48px'
    default: return '32px'
  }
}

function radiusSize(size: string): string {
  switch (size) {
    case 'none': return '0px'
    case 'sm': return '8px'
    case 'md': return '16px'
    case 'xl': return '32px'
    default: return '24px'
  }
}

function ratioValue(ratio: string): string | undefined {
  switch (ratio) {
    case '1:1': return '1 / 1'
    case '4:3': return '4 / 3'
    case '3:4': return '3 / 4'
    case '16:9': return '16 / 9'
    default: return undefined
  }
}

function alignment(align: string): CSSProperties['textAlign'] {
  return align === 'center' ? 'center' : 'left'
}

function flexAlign(align: string): CSSProperties['alignItems'] {
  if (align === 'start') return 'flex-start'
  if (align === 'end') return 'flex-end'
  return 'center'
}

function renderButtons(section: ContentSection, visualEditor = false) {
  if (!section.buttons?.length) return null
  return (
    <div className="cms-section-actions" style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', marginTop: '24px' }}>
      {section.buttons.map((btn, i) => (
        <span key={`${section.id}-btn-${i}`} {...getVeFieldAttrs(visualEditor, section.id, 'button', 'button', true, { buttonIndex: i })}>
          <CTA text={btn.text} href={btn.href} variant={btn.variant as 'primary' | 'secondary'} />
        </span>
      ))}
    </div>
  )
}

function renderHeader(section: ContentSection, visualEditor = false) {
  const headingAlreadyInText = hasHeadingHtml(section.text)
  return (
    <SectionHeading
      section={section}
      visualEditor={visualEditor}
      variant="block"
      headingAlreadyInText={headingAlreadyInText}
    />
  )
}

function renderText(section: ContentSection, visualEditor = false, style?: CSSProperties) {
  if (!section.text) return null
  return (
    <div
      {...getVeFieldAttrs(visualEditor, section.id, 'text', 'richtext', true)}
      style={style}
      dangerouslySetInnerHTML={{ __html: section.text }}
    />
  )
}

function renderImageFrame(section: ContentSection, visualEditor = false, targetField = 'section_image', aspectRatio?: string, fit = 'cover', radius = '24px') {
  const media = getSectionMedia(section)[0]
  if (!media) return null
  return (
    <div
      {...getVeFieldAttrs(visualEditor, section.id, 'media', 'media', false, { targetField })}
      style={{
        position: 'relative',
        width: '100%',
        aspectRatio: aspectRatio,
        minHeight: aspectRatio ? undefined : '360px',
        overflow: 'hidden',
        borderRadius: radius,
        background: '#e5e7eb',
      }}
    >
      <Image src={media.url} alt={media.alt || section.title} fill sizes="(max-width: 768px) 100vw, 50vw" style={{ objectFit: fit as 'cover' | 'contain' }} />
    </div>
  )
}

const PRICING_TIER_DEFAULTS = [
  { name: 'Halb', shares: '1 Anteilsschein', persons: 1, price: 'CHF 750.-', shareCost: 'CHF 250.-', work: '10 Arbeitseinsätze' },
  { name: 'Standard', shares: '2 Anteilsscheine', persons: 2, price: "CHF 1'280.-", shareCost: 'CHF 500.-', work: '20 Arbeitseinsätze' },
  { name: 'Doppel', shares: '4 Anteilsscheine', persons: 4, price: "CHF 2'350.-", shareCost: "CHF 1'000.-", work: '40 Arbeitseinsätze' },
]

export function PricingTableBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'xl'))
  const workSuffix = configValue(config, 'workSuffix', 'à 2 Stunden')

  const tiers = PRICING_TIER_DEFAULTS.map((def, index) => {
    const t = index + 1
    const persons = Number(configValue(config, `tier${t}_persons`, String(def.persons))) || def.persons
    return {
      name: configValue(config, `tier${t}_name`, def.name),
      shares: configValue(config, `tier${t}_shares`, def.shares),
      persons,
      price: configValue(config, `tier${t}_price`, def.price),
      shareCost: configValue(config, `tier${t}_sharecost`, def.shareCost),
      work: configValue(config, `tier${t}_work`, def.work),
    }
  })

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      {renderHeader(section, visualEditor)}
      {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.06rem', lineHeight: 1.75, marginBottom: '24px' })}
      <div
        className="pricing-table"
        {...getVeFieldAttrs(visualEditor, section.id, 'component', 'structured', false)}
      >
        <table>
          <thead>
            <tr>
              <th>Gemüsekorb</th>
              <th>Personen</th>
              <th>Jahrespreis</th>
              <th>Anteilsscheine Kosten</th>
              <th>Mitarbeit pro Jahr</th>
            </tr>
          </thead>
          <tbody>
            {tiers.map((tier, index) => (
              <tr key={`${section.id}-tier-${index}`}>
                <td>
                  <strong>{tier.name}</strong>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '4px' }}>{tier.shares}</div>
                </td>
                <td>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <PersonIcons count={tier.persons} />
                    {tier.persons >= 2 ? <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>+</span> : null}
                  </div>
                </td>
                <td>{tier.price}</td>
                <td>{tier.shareCost}</td>
                <td>
                  {tier.work}
                  {workSuffix ? (
                    <>
                      <br />
                      <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>{workSuffix}</span>
                    </>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {renderButtons(section, visualEditor)}
    </section>
  )
}

export function AccordionItemBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  // Consecutive accordion_item sections must stack like the former single
  // `.demeter-accordion` wrapper on /gemuese: full container width and no
  // per-item vertical margins (spacing comes from `details { margin-bottom }`
  // in globals.css). Keep margin 0 so the CSS class' margin-top only ever
  // applied to the old grouped wrapper, not to every item.
  return (
    <section className="demeter-accordion" style={{ margin: 0 }}>
      <details>
        <summary {...getVeFieldAttrs(visualEditor, section.id, 'title', 'text', true)}>
          {section.title}
        </summary>
        <div
          {...getVeFieldAttrs(visualEditor, section.id, 'text', 'richtext', true)}
          dangerouslySetInnerHTML={{ __html: section.text || '' }}
        />
      </details>
    </section>
  )
}

const STEPS_MAX = 4

export function StepsBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'lg'))

  const steps = []
  for (let n = 1; n <= STEPS_MAX; n++) {
    const title = configValue(config, `step${n}_title`, '').trim()
    const text = configValue(config, `step${n}_text`, '').trim()
    if (!title && !text) continue
    steps.push({ title, text })
  }

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      {renderHeader(section, visualEditor)}
      {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.06rem', lineHeight: 1.75 })}
      <div
        className="next-steps"
        style={{ marginTop: '32px' }}
        {...getVeFieldAttrs(visualEditor, section.id, 'component', 'structured', false)}
      >
        {steps.map((step, index) => (
          <div key={`${section.id}-step-${index}`} className="step-item">
            <div className="step-number">{index + 1}</div>
            <div>
              {step.title ? <h3>{step.title}</h3> : null}
              {step.text ? <p>{step.text}</p> : null}
            </div>
          </div>
        ))}
      </div>
    </section>
  )
}

const LINK_TILES_MAX = 4

export function LinkTilesBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'lg'))

  const tiles = []
  for (let n = 1; n <= LINK_TILES_MAX; n++) {
    const title = configValue(config, `tile${n}_title`, '').trim()
    if (!title) continue
    tiles.push({
      title,
      text: configValue(config, `tile${n}_text`, '').trim(),
      href: configValue(config, `tile${n}_href`, '').trim(),
      icon: configValue(config, `tile${n}_icon`, '').trim(),
    })
  }

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      {renderHeader(section, visualEditor)}
      {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.06rem', lineHeight: 1.75 })}
      <div
        className="portal-gateway"
        {...getVeFieldAttrs(visualEditor, section.id, 'component', 'structured', false)}
      >
        {tiles.map((tile, index) => {
          const inner = (
            <>
              {tile.icon ? <div className="portal-icon">{tile.icon}</div> : null}
              <h3>{tile.title}</h3>
              {tile.text ? <p>{tile.text}</p> : null}
            </>
          )
          return tile.href ? (
            <Card as="a" variant="plain" key={`${section.id}-tile-${index}`} className="portal-tile" href={tile.href}>
              {inner}
            </Card>
          ) : (
            <Card as="div" variant="plain" key={`${section.id}-tile-${index}`} className="portal-tile">
              {inner}
            </Card>
          )
        })}
      </div>
    </section>
  )
}

export function PageIntroBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'lg'))
  const textMaxWidth = contentWidth(configValue(config, 'textWidth', 'normal'))
  const textAlign = alignment(configValue(config, 'align', 'left'))

  return (
    <section style={{ margin: '0 auto 80px', maxWidth, textAlign }}>
      <div style={{ margin: textAlign === 'center' ? '0 auto' : '0', maxWidth: textMaxWidth }}>
        {renderHeader(section, visualEditor)}
        {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.06rem', lineHeight: 1.75 })}
        {renderButtons(section, visualEditor)}
      </div>
    </section>
  )
}

export function MediaTextBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'xl'))
  const mediaSide = configValue(config, 'mediaSide', 'left')
  const mediaWidth = `${configValue(config, 'mediaWidth', '50')}%`
  const gap = gapSize(configValue(config, 'gap', 'lg'))
  const aspectRatio = ratioValue(configValue(config, 'mediaRatio', '4:3'))
  const fit = configValue(config, 'mediaFit', 'cover')
  const radius = radiusSize(configValue(config, 'rounded', 'lg'))
  const alignItems = flexAlign(configValue(config, 'verticalAlign', 'center'))

  const mediaNode = (
    <div style={{ flex: `0 0 ${mediaWidth}`, minWidth: '280px' }}>
      {renderImageFrame(section, visualEditor, 'section_image', aspectRatio, fit, radius)}
    </div>
  )

  const textNode = (
    <div style={{ flex: '1 1 0%', minWidth: '280px' }}>
      {renderHeader(section, visualEditor)}
      {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.06rem', lineHeight: 1.75 })}
      {renderButtons(section, visualEditor)}
    </div>
  )

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap, alignItems }}>
        {mediaSide === 'right' ? textNode : mediaNode}
        {mediaSide === 'right' ? mediaNode : textNode}
      </div>
    </section>
  )
}

export function CardsGridBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'xl'))
  const columnsDesktop = Number(configValue(config, 'columnsDesktop', '3')) || 3
  const gap = gapSize(configValue(config, 'gap', 'lg'))
  const aspectRatio = ratioValue(configValue(config, 'mediaRatio', '3:4'))
  const fit = configValue(config, 'mediaFit', 'cover')
  const radius = radiusSize(configValue(config, 'rounded', 'md'))
  const cardStyle = configValue(config, 'cardStyle', 'soft')
  const mediaItems = getSectionMedia(section)

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      {renderHeader(section, visualEditor)}
      {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.03rem', lineHeight: 1.72, marginBottom: '24px' })}
      <div style={{ display: 'grid', gridTemplateColumns: `repeat(${Math.max(1, columnsDesktop)}, minmax(0, 1fr))`, gap }}>
        {mediaItems.map((item, index) => {
          const title = (item.alt || '').split(/[|,–-]/)[0]?.trim() || `Karte ${index + 1}`
          const cardBg = cardStyle === 'soft' ? '#f5f2e9' : '#fff'
          const cardBorder = cardStyle === 'outlined' ? '1px solid #d6d0c3' : 'none'
          return (
            <Card as="article" variant={cardStyle as 'soft' | 'outlined' | 'plain'} key={`${section.id}-card-${index}`} style={{ background: cardBg, border: cardBorder, borderRadius: radius, overflow: 'hidden', padding: cardStyle === 'plain' ? 0 : '14px' }}>
              <div {...getVeFieldAttrs(visualEditor, section.id, 'media', 'media', false, { targetField: 'section_images' })} style={{ position: 'relative', width: '100%', aspectRatio, minHeight: aspectRatio ? undefined : '280px', borderRadius: radius, overflow: 'hidden', background: '#e5e7eb' }}>
                <Image src={item.url} alt={item.alt || title} fill sizes="(max-width: 768px) 100vw, 33vw" style={{ objectFit: fit as 'cover' | 'contain' }} />
              </div>
              <h3 style={{ fontSize: '1.2rem', margin: '14px 0 0' }}>{title}</h3>
            </Card>
          )
        })}
      </div>
    </section>
  )
}

export function GalleryStripBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'xl'))
  const columnsDesktop = Number(configValue(config, 'columnsDesktop', '3')) || 3
  const gap = gapSize(configValue(config, 'gap', 'lg'))
  const aspectRatio = ratioValue(configValue(config, 'mediaRatio', '4:3'))
  const fit = configValue(config, 'mediaFit', 'cover')
  const radius = radiusSize(configValue(config, 'rounded', 'lg'))
  const mediaItems = getSectionMedia(section)

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      {renderHeader(section, visualEditor)}
      {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.03rem', lineHeight: 1.72, marginBottom: '24px' })}
      <div {...getVeFieldAttrs(visualEditor, section.id, 'media', 'media', false, { targetField: 'section_images' })} style={{ display: 'grid', gridTemplateColumns: `repeat(${Math.max(1, columnsDesktop)}, minmax(0, 1fr))`, gap }}>
        {mediaItems.map((item, index) => (
          <div key={`${section.id}-gallery-${index}`} style={{ position: 'relative', width: '100%', aspectRatio, minHeight: aspectRatio ? undefined : '280px', borderRadius: radius, overflow: 'hidden', background: '#e5e7eb' }}>
            <Image src={item.url} alt={item.alt || section.title} fill sizes="(max-width: 768px) 100vw, 33vw" style={{ objectFit: fit as 'cover' | 'contain' }} />
          </div>
        ))}
      </div>
      {renderButtons(section, visualEditor)}
    </section>
  )
}

export function TextColumnsBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'lg'))
  const columnsDesktop = Number(configValue(config, 'columnsDesktop', '2')) || 2
  const gap = gapSize(configValue(config, 'gap', 'lg'))

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      {renderHeader(section, visualEditor)}
      {section.text ? (
        <div
          {...getVeFieldAttrs(visualEditor, section.id, 'text', 'richtext', true)}
          style={{ color: '#4b5563', fontSize: '1.03rem', lineHeight: 1.8, columnCount: columnsDesktop, columnGap: gap }}
          dangerouslySetInnerHTML={{ __html: section.text }}
        />
      ) : null}
      {renderButtons(section, visualEditor)}
    </section>
  )
}

export function TimelineHeaderBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'lg'))
  const textMaxWidth = contentWidth(configValue(config, 'textWidth', 'normal'))
  const textAlign = alignment(configValue(config, 'align', 'left'))

  return (
    <section style={{ margin: '0 auto 40px', maxWidth, textAlign }}>
      <div style={{ margin: textAlign === 'center' ? '0 auto' : '0', maxWidth: textMaxWidth }}>
        {renderHeader(section, visualEditor)}
        {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.03rem', lineHeight: 1.75 })}
      </div>
    </section>
  )
}

export function TimelineItemBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'lg'))
  const emphasis = configValue(config, 'emphasis', 'normal')
  const badgeBg = emphasis === 'highlight' ? '#8ab272' : '#111827'

  return (
    <section style={{ margin: '0 auto 28px', maxWidth }}>
      <div style={{ display: 'grid', gridTemplateColumns: '120px 1fr', gap: '24px', alignItems: 'start' }}>
        <div>
          <div {...getVeFieldAttrs(visualEditor, section.id, 'eyebrow', 'text', true)} style={{ display: 'inline-flex', padding: '10px 14px', borderRadius: '999px', background: badgeBg, color: '#fff', fontWeight: 700 }}>
            {section.eyebrow || '•'}
          </div>
        </div>
        <div style={{ padding: '0 0 0 20px', borderLeft: '2px solid #d5dfcf' }}>
          {renderHeader({ ...section, eyebrow: '' }, visualEditor)}
          {renderText(section, visualEditor, { color: '#4b5563', fontSize: '1.02rem', lineHeight: 1.75 })}
          {renderButtons(section, visualEditor)}
        </div>
      </div>
    </section>
  )
}

export function CtaBandBlock({ section, visualEditor = false }: RegisteredComponentProps) {
  const config = getConfig(section)
  const maxWidth = containerMaxWidth(configValue(config, 'containerWidth', 'lg'))
  const align = configValue(config, 'align', 'left')
  const theme = configValue(config, 'theme', 'soft')
  const radius = radiusSize(configValue(config, 'rounded', 'xl'))

  const background = theme === 'dark'
    ? '#111827'
    : theme === 'accent'
      ? '#d97706'
      : theme === 'light'
        ? '#ffffff'
        : '#f5f2e9'
  const color = theme === 'dark' ? '#f8fafc' : theme === 'accent' ? '#fff7ed' : '#111827'

  return (
    <section style={{ margin: '0 auto 80px', maxWidth }}>
      <div style={{ background, color, borderRadius: radius, padding: '32px', textAlign: alignment(align) }}>
        {renderHeader(section, visualEditor)}
        {renderText(section, visualEditor, { color: 'inherit', fontSize: '1.03rem', lineHeight: 1.75 })}
        <div style={{ display: 'flex', justifyContent: align === 'center' ? 'center' : 'flex-start' }}>
          {renderButtons(section, visualEditor)}
        </div>
      </div>
    </section>
  )
}
