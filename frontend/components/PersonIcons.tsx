interface PersonIconsProps {
  count: number
  className?: string
}

export function PersonIcons({ count, className = '' }: PersonIconsProps) {
  const iconsPerRow = count === 4 ? 2 : count
  const rows = count === 4 ? 2 : 1
  
  return (
    <div className={`person-icons ${className}`} style={{ display: 'flex', flexDirection: 'column', gap: '4px', alignItems: 'flex-start' }}>
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <div key={rowIndex} style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
          {Array.from({ length: iconsPerRow }).map((_, iconIndex) => {
            const iconNumber = rowIndex * iconsPerRow + iconIndex
            if (iconNumber >= count) return null
            return (
              <svg 
                key={iconNumber} 
                width="24" 
                height="24" 
                viewBox="0 0 24 24" 
                fill="none" 
                xmlns="http://www.w3.org/2000/svg"
                style={{ flexShrink: 0 }}
              >
                <circle cx="12" cy="8" r="4" fill="var(--bioco-green)" stroke="var(--bioco-green-dark)" strokeWidth="1"/>
                <path d="M6 20C6 16 8 14 12 14C16 14 18 16 18 20" stroke="var(--bioco-green)" strokeWidth="2" strokeLinecap="round" fill="none"/>
              </svg>
            )
          })}
        </div>
      ))}
    </div>
  )
}
