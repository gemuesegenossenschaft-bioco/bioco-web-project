// Bean menu icon for mobile navigation - based on BushBeanIcon
export function BeanMenuIcon({
  width = 32,
  height = 32,
  beans = 3,
  spread = 8,
  curvature = 0.55,
  thickness = 0.10,
  fill = "var(--bioco-green, #2E7D32)",
  stroke = "var(--bioco-green-dark, #1B5E20)",
  strokeOpacity = 1,
  flipIndex = 1, // 0=oben, 1=mitte, 2=unten
  globalRotate = 0
}: {
  width?: number
  height?: number
  beans?: number
  spread?: number
  curvature?: number
  thickness?: number
  fill?: string
  stroke?: string
  strokeOpacity?: number
  flipIndex?: number
  globalRotate?: number
}) {
  const H = height
  const W = width
  const startY = H * 0.25

  const paths = Array.from({ length: beans }, (_, i) => {
    const y = startY + i * spread // No jitter for menu icon
    const amp = (H * thickness) * curvature
    const L = W * 0.12 // Narrower beans (was 0.08)
    const R = W * 0.88 // Narrower beans (was 0.92)

    const pod = `M ${L},${y}
      C ${W*0.28},${y-amp} ${W*0.72},${y-amp} ${R},${y}
      C ${W*0.72},${y+amp*0.45} ${W*0.28},${y+amp*0.45} ${L},${y} Z`

    const midrib = `M ${W*0.10},${y-0.4}
      C ${W*0.32},${y-amp*0.5} ${W*0.68},${y-amp*0.5} ${W*0.90},${y-0.4}`

    const stem = `M ${L},${y} c -1.5,-3.0 -1.2,-4.5 -0.6,-6.0` // Shorter stem

    return { pod, midrib, stem, y }
  })

  return (
    <svg 
      width={W} 
      height={H} 
      viewBox={`0 0 ${W} ${H}`} 
      role="img" 
      aria-label="Menü" 
      xmlns="http://www.w3.org/2000/svg"
      className="bean-menu-icon"
    >
      <g transform={`rotate(${globalRotate} ${W/2} ${H/2})`}>
        {paths.map((p, idx) => {
          const cx = W / 2
          const cy = p.y

          // Horizontal mirroring: translate -> optional scaleX(-1) -> translate back
          const base = `translate(${cx} ${cy})`
          const flip = idx === flipIndex ? " scale(-1 1)" : ""
          const end = ` translate(${-cx} ${-cy})`

          return (
            <g key={idx} transform={base + flip + end}>
              <path 
                d={p.pod} 
                fill={fill} 
                stroke={stroke} 
                strokeOpacity={strokeOpacity}
                strokeWidth={Math.max(1.4, W*0.018)} 
                strokeLinejoin="round"
              />
              <path 
                d={p.midrib} 
                fill="none" 
                stroke="rgba(255,255,255,.35)"
                strokeWidth={Math.max(0.9, W*0.01)} 
                strokeLinecap="round"
              />
              <path 
                d={p.stem} 
                fill="none" 
                stroke={stroke}
                strokeWidth={Math.max(1.4, W*0.018)} 
                strokeLinecap="round"
              />
            </g>
          )
        })}
      </g>
    </svg>
  )
}

