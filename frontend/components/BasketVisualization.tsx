interface BasketVisualizationProps {
  size: 'halb' | 'standard' | 'doppel'
  className?: string
}

export function BasketVisualization({ size, className = '' }: BasketVisualizationProps) {
  const sizes = {
    halb: { width: '40px', height: '30px', items: 3 },
    standard: { width: '60px', height: '45px', items: 6 },
    doppel: { width: '80px', height: '60px', items: 9 },
  }

  const config = sizes[size]

  return (
    <div className={`basket-visualization ${className}`} style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <svg 
        width={config.width} 
        height={config.height} 
        viewBox="0 0 100 80" 
        style={{ maxWidth: '100%', height: 'auto' }}
      >
        {/* Basket */}
        <path 
          d="M10 20 L10 70 Q10 75 15 75 L85 75 Q90 75 90 70 L90 20 L10 20 Z" 
          fill="var(--bioco-beet)" 
          stroke="var(--bioco-beet-light)" 
          strokeWidth="2"
        />
        <path 
          d="M10 20 L20 15 L30 18 L40 15 L50 18 L60 15 L70 18 L80 15 L90 20" 
          stroke="var(--bioco-beet-light)" 
          strokeWidth="2" 
          fill="none"
        />
        
        {/* Vegetables */}
        {Array.from({ length: config.items }).map((_, i) => {
          const x = 20 + (i % 3) * 25
          const y = 30 + Math.floor(i / 3) * 20
          const size = 8 + Math.random() * 4
          return (
            <circle 
              key={i} 
              cx={x} 
              cy={y} 
              r={size} 
              fill={i % 2 === 0 ? 'var(--bioco-green)' : 'var(--bioco-orange)'}
            />
          )
        })}
      </svg>
    </div>
  )
}
