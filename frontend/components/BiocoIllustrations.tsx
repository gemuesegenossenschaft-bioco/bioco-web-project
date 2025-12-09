/**
 * BiocoIllustrations Component
 * 
 * Displays the Selina Kallen illustrations converted to SVG format
 * with the bioco three-color palette (green, orange, beet).
 */

import Image from 'next/image'

export type IllustrationName =
  | 'fliege'
  | 'fliege2'
  | 'schmetterling'
  | 'schmetterling2'
  | 'radieschen'
  | 'knoblauch'
  | 'kohlrabi'
  | 'lauch_mit_schnecke'
  | 'ruebli'
  | 'blumenkohl_mit_vogel'
  | 'fenchel'
  | 'zwiebel'
  | 'aubergine'

interface BiocoIllustrationProps {
  /** Name of the illustration to display */
  name: IllustrationName
  /** Optional width (default: auto) */
  width?: number
  /** Optional height (default: auto) */
  height?: number
  /** Optional className for styling */
  className?: string
  /** Optional opacity (default: 1) */
  opacity?: number
  /** Optional alt text */
  alt?: string
}

/**
 * Component to display bioco illustrations as SVG
 */
export function BiocoIllustration({
  name,
  width,
  height,
  className = '',
  opacity = 1,
  alt,
}: BiocoIllustrationProps) {
  const src = `/images/illustrations/${name}.svg`
  const defaultAlt = alt || `Bioco Illustration ${name} by Selina Kallen`

  return (
    <Image
      src={src}
      alt={defaultAlt}
      width={width}
      height={height}
      className={className}
      style={{ opacity }}
      unoptimized // SVGs don't need Next.js optimization
    />
  )
}

/**
 * Inline SVG component that loads the SVG content directly
 * Useful when you need to style the SVG paths with CSS
 */
export function BiocoIllustrationInline({
  name,
  className = '',
  opacity = 1,
  style = {},
}: Omit<BiocoIllustrationProps, 'width' | 'height' | 'alt'> & { style?: React.CSSProperties }) {
  const src = `/images/illustrations/${name}.svg`

  return (
    <img
      src={src}
      alt={`Bioco Illustration ${name}`}
      className={className}
      style={{ opacity, ...style }}
    />
  )
}

/**
 * Mapping of illustration names to their German/English descriptions
 */
export const ILLUSTRATION_NAMES: Record<IllustrationName, { de: string; en: string }> = {
  fliege: { de: 'Fliege', en: 'Fly' },
  fliege2: { de: 'Fliege 2', en: 'Fly 2' },
  schmetterling: { de: 'Schmetterling', en: 'Butterfly' },
  schmetterling2: { de: 'Schmetterling 2', en: 'Butterfly 2' },
  radieschen: { de: 'Radieschen', en: 'Radish' },
  knoblauch: { de: 'Knoblauch', en: 'Garlic' },
  kohlrabi: { de: 'Kohlrabi', en: 'Kohlrabi' },
  lauch_mit_schnecke: { de: 'Lauch mit Schnecke', en: 'Leek with Snail' },
  ruebli: { de: 'Rüebli', en: 'Carrot' },
  blumenkohl_mit_vogel: { de: 'Blumenkohl mit Vogel', en: 'Cauliflower with Bird' },
  fenchel: { de: 'Fenchel', en: 'Fennel' },
  zwiebel: { de: 'Zwiebel', en: 'Onion' },
  aubergine: { de: 'Aubergine', en: 'Eggplant' },
}

/**
 * Get all available illustration names
 */
export function getAllIllustrationNames(): IllustrationName[] {
  return Object.keys(ILLUSTRATION_NAMES) as IllustrationName[]
}

/**
 * Animated creature types
 */
export type AnimatedCreatureName = 'biene' | 'ente' | 'ente_right' | 'schnecke'

/**
 * Mapping of animated creature names to their German/English descriptions
 */
export const ANIMATED_CREATURE_NAMES: Record<AnimatedCreatureName, { de: string; en: string }> = {
  biene: { de: 'Biene', en: 'Bee' },
  ente: { de: 'Ente', en: 'Duck' },
  ente_right: { de: 'Ente (rechts)', en: 'Duck (right)' },
  schnecke: { de: 'Schnecke', en: 'Snail' },
}

interface AnimatedCreatureProps {
  /** Name of the animated creature */
  name: AnimatedCreatureName
  /** Optional width (default: auto) */
  width?: number
  /** Optional height (default: auto) */
  height?: number
  /** Optional className for styling */
  className?: string
  /** Optional opacity (default: 1) */
  opacity?: number
  /** Optional alt text */
  alt?: string
}

/**
 * Component to display animated bioco creatures
 * - biene: Bee with flapping wings
 * - ente: Duck walking and opening beak (left-facing)
 * - ente_right: Duck walking and opening beak (right-facing)
 * - schnecke: Snail sliding
 */
export function AnimatedCreature({
  name,
  width,
  height,
  className = '',
  opacity = 1,
  alt,
}: AnimatedCreatureProps) {
  let src: string
  if (name === 'ente_right') {
    src = `/images/illustrations/animated/ente_walk_right.svg`
  } else {
    const animationSuffix = name === 'biene' ? 'flap' : name === 'ente' ? 'walk' : 'slide'
    src = `/images/illustrations/animated/${name}_${animationSuffix}.svg`
  }
  const defaultAlt = alt || `Bioco Illustration ${ANIMATED_CREATURE_NAMES[name].en} by Selina Kallen`

  return (
    <Image
      src={src}
      alt={defaultAlt}
      width={width}
      height={height}
      className={className}
      style={{ opacity }}
      unoptimized
    />
  )
}

