import type { CSSProperties } from 'react'
import { getVeFieldAttrs } from '@/components/visual-editor/fieldAttrs'
import type { ContentSection } from '@/lib/processwire-types'

export type SectionHeadingVariant = 'renderer' | 'block'

interface SectionHeadingProps {
  section: ContentSection
  visualEditor: boolean
  variant: SectionHeadingVariant
  headingAlreadyInText: boolean
}

// RegisteredSectionComponents' historical inline styles, now routed through
// the D13 (GH #87) design tokens (--color-steel equals the literal that
// rendered here before).
const BLOCK_EYEBROW_STYLE: CSSProperties = {
  color: 'var(--color-steel)',
  fontSize: '0.95rem',
  marginBottom: '10px',
  fontWeight: 'var(--font-weight-semibold)',
}

const BLOCK_TITLE_STYLE: CSSProperties = {
  fontSize: 'clamp(2rem, 3vw, 3.6rem)',
  lineHeight: 1.05,
  margin: '0 0 18px 0',
}

export function SectionHeading({ section, visualEditor, variant, headingAlreadyInText }: SectionHeadingProps) {
  const eyebrowAttrs = getVeFieldAttrs(visualEditor, section.id, 'eyebrow', 'text', true)
  const titleAttrs = getVeFieldAttrs(visualEditor, section.id, 'title', 'text', true)

  return (
    <>
      {section.eyebrow ? (
        variant === 'renderer' ? (
          <p className="cms-section-eyebrow" {...eyebrowAttrs}>
            {section.eyebrow}
          </p>
        ) : (
          <p style={BLOCK_EYEBROW_STYLE} {...eyebrowAttrs}>
            {section.eyebrow}
          </p>
        )
      ) : null}
      {section.title && !headingAlreadyInText ? (
        variant === 'renderer' ? (
          <h2 {...titleAttrs}>{section.title}</h2>
        ) : (
          <h2 style={BLOCK_TITLE_STYLE} {...titleAttrs}>
            {section.title}
          </h2>
        )
      ) : null}
    </>
  )
}
