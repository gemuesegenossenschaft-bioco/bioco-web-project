import React from 'react'

interface IconProps {
  className?: string
  ariaLabel?: string
  size?: number
}

const canvasStyle = (size?: number) => ({
  width: `${size ?? 72}px`,
  height: `${size ?? 72}px`,
  borderRadius: '28px',
  background: 'var(--bg-secondary)',
  border: 'none',
  boxShadow: '0 8px 16px rgba(0, 0, 0, 0.12)',
  display: 'inline-flex',
  alignItems: 'center',
  justifyContent: 'center',
})

const svgStyle = {
  width: '90%',
  height: '90%',
  display: 'block',
} as const

/**
 * Tomato cluster inspired avatar (hand-painted look, simplified flat style)
 */
export const DuckAvatarIcon = ({ className = '', ariaLabel = 'Tomaten Illustration', size }: IconProps) => {
  return (
    <div
      className={className}
      role="img"
      aria-label={ariaLabel}
      style={canvasStyle(size)}
    >
      <svg viewBox="0 0 120 120" style={svgStyle}>
        {/* Simple vine */}
        <path
          d="M18 30 C30 22 42 20 54 26 C65 31 74 44 86 50 C95 55 104 55 108 58 C112 61 111 67 105 70 C95 76 82 72 72 68 C62 64 52 58 42 52 C30 45 20 38 15 33 C12 30 14 28 18 30 Z"
          fill="none"
          stroke="var(--bioco-green-dark)"
          strokeWidth="5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        {/* Simple flat tomatoes */}
        {[{ cx: 36, cy: 40, r: 16 },
          { cx: 60, cy: 32, r: 14 },
          { cx: 80, cy: 48, r: 15 },
          { cx: 52, cy: 64, r: 17 }].map((tomato) => (
          <circle 
            key={`${tomato.cx}-${tomato.cy}`}
            cx={tomato.cx} 
            cy={tomato.cy} 
            r={tomato.r} 
            fill="var(--bioco-beet)" 
            stroke="var(--bioco-beet-light)" 
            strokeWidth="3" 
          />
        ))}
        {/* Simple stems */}
        {[{ x: 34, y: 26 }, { x: 58, y: 20 }, { x: 78, y: 40 }, { x: 50, y: 58 }].map((stem, idx) => (
          <path
            key={idx}
            d={`M${stem.x} ${stem.y} c -6 -6 -4 -12 4 -16`}
            stroke="var(--bioco-green)"
            strokeWidth="4"
            fill="none"
            strokeLinecap="round"
          />
        ))}
      </svg>
    </div>
  )
}

/**
 * Carrot icon for location-type actions (hand-painted, simplified flat style)
 */
export const MapNeedleIcon = ({ className = '', ariaLabel = 'Karotten Illustration', size = 64 }: IconProps) => {
  return (
    <svg
      role="img"
      aria-label={ariaLabel}
      className={className}
      viewBox="0 0 120 120"
      style={{ ...svgStyle, width: `${size}px`, height: `${size}px` }}
    >
      {/* Simple flat carrot */}
      <path
        d="M20 38 C32 18 42 10 48 14 C54 18 56 32 74 34 C92 36 102 46 96 60 C90 74 66 104 54 110 C42 116 32 106 28 94 C24 82 26 68 18 52 C12 40 14 32 20 38 Z"
        fill="var(--bioco-orange)"
        stroke="var(--bioco-orange-dark)"
        strokeWidth="4"
        strokeLinecap="round"
      />
      {/* Simple leaves */}
      {[{ x: 42, y: 20 }, { x: 52, y: 22 }, { x: 60, y: 24 }].map((leaf, idx) => (
        <path
          key={idx}
          d={`M${leaf.x} ${leaf.y} c -4 -16 6 -20 16 -18`}
          stroke="var(--bioco-green)"
          strokeWidth="4"
          strokeLinecap="round"
          fill="none"
        />
      ))}
    </svg>
  )
}

/**
 * Letter/Envelope icon for contact (hand-drawn style with beet colors)
 */
export const ContactLetterIcon = ({ className = '', ariaLabel = 'Brief Illustration', size = 64 }: IconProps) => {
  return (
    <svg
      role="img"
      aria-label={ariaLabel}
      className={className}
      viewBox="0 0 100 80"
      style={{ ...svgStyle, width: `${size}px`, height: `${size}px` }}
    >
      {/* Envelope/letter base - hand-drawn rectangle */}
      <path
        d="M10 20 L10 60 Q10 65 15 65 L85 65 Q90 65 90 60 L90 20 Q90 15 85 15 L15 15 Q10 15 10 20 Z"
        fill="var(--bioco-beet)"
        stroke="var(--bioco-beet-light)"
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      {/* Letter flap/triangle at top - hand-drawn */}
      <path
        d="M10 20 L50 40 L90 20 L85 15 L15 15 L10 20 Z"
        fill="var(--bioco-beet-light)"
        stroke="var(--bioco-beet-light)"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      {/* Letter lines inside - hand-drawn text lines */}
      <path
        d="M20 35 L70 35"
        stroke="var(--bg-primary)"
        strokeWidth="2"
        strokeLinecap="round"
        opacity="0.6"
      />
      <path
        d="M20 45 L65 45"
        stroke="var(--bg-primary)"
        strokeWidth="2"
        strokeLinecap="round"
        opacity="0.6"
      />
      <path
        d="M20 55 L60 55"
        stroke="var(--bg-primary)"
        strokeWidth="2"
        strokeLinecap="round"
        opacity="0.6"
      />
    </svg>
  )
}

/**
 * Peas / Pod icon for freshness cues (simplified flat style)
 */
export const PeasIcon = ({ className = '', ariaLabel = 'Erbsenschote Illustration', size = 64 }: IconProps) => {
  return (
    <svg
      role="img"
      aria-label={ariaLabel}
      className={className}
      viewBox="0 0 140 120"
      style={{ ...svgStyle, width: `${size}px`, height: `${size}px` }}
    >
      {/* Simple flat pod */}
      <path
        d="M26 66 C18 42 32 26 54 22 C78 18 112 24 126 40 C140 56 136 72 118 88 C100 104 64 108 42 100 C20 92 10 84 26 66 Z"
        fill="var(--bioco-green)"
        stroke="var(--bioco-green-dark)"
        strokeWidth="4"
        strokeLinecap="round"
      />
      {/* Simple flat peas */}
      {[0, 1, 2, 3].map((idx) => (
        <circle
          key={`pea-${idx}`}
          cx={48 + idx * 18}
          cy={70 - idx * 4}
          r={9}
          fill="var(--bioco-green-light)"
          stroke="var(--bioco-green-dark)"
          strokeWidth="2"
        />
      ))}
      {/* Simple stem */}
      <path
        d="M28 58 Q24 40 38 30"
        stroke="var(--bioco-green)"
        strokeWidth="5"
        strokeLinecap="round"
        fill="none"
      />
    </svg>
  )
}

/**
 * Duck Head Icon for navigation (hand-drawn, realistic style)
 * Shows head and neck only, with longer beak, black and green colors, in a circle
 */
export const DuckIcon = ({ className = '', size = 20 }: { className?: string, size?: number }) => {
  return (
    <svg 
      width={size} 
      height={size} 
      viewBox="0 0 100 100" 
      fill="none" 
      xmlns="http://www.w3.org/2000/svg" 
      className={className}
    >
      {/* Circle border - no fill background */}
      <circle
        cx="50"
        cy="50"
        r="48"
        fill="transparent"
        stroke="var(--bioco-green)"
        strokeWidth="3"
      />
      
      {/* Duck head - black, more recognizable duck profile */}
      <ellipse
        cx="45"
        cy="50"
        rx="18"
        ry="22"
        fill="#1F2A1B"
        stroke="#1F2A1B"
        strokeWidth="2"
      />
      
      {/* Duck beak - much longer, prominent, green */}
      <path
        d="M60 48 Q75 45 88 50 Q85 55 75 55 Q65 53 60 52 Q58 50 60 48 Z"
        fill="var(--bioco-green)"
        stroke="var(--bioco-green-dark)"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      
      {/* Beak nostril detail */}
      <circle
        cx="72"
        cy="50"
        r="1.5"
        fill="var(--bioco-green-dark)"
      />
      
      {/* Duck eye - positioned correctly on head */}
      <circle 
        cx="50" 
        cy="45" 
        r="3.5" 
        fill="#1F2A1B" 
      />
      <circle 
        cx="51" 
        cy="44" 
        r="1" 
        fill="var(--bg-primary)" 
      />
      
      {/* Neck - extending down from head */}
      <path
        d="M35 60 Q30 70 32 80 Q34 82 38 82"
        stroke="#1F2A1B"
        strokeWidth="4"
        strokeLinecap="round"
        fill="none"
      />
      
      {/* Neck green accent */}
      <path
        d="M36 62 Q32 72 34 78"
        stroke="var(--bioco-green)"
        strokeWidth="2.5"
        strokeLinecap="round"
        fill="none"
      />
    </svg>
  )
}


