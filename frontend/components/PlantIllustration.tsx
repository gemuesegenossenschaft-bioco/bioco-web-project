// Plant illustration component for bento cards
export function PlantIllustration({ variant = 'carrot' }: { variant?: 'carrot' | 'beet' | 'leaf' }) {
  const illustrations = {
    carrot: (
      <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ opacity: 0.15 }}>
        <path d="M30 10L25 35L35 35L30 10Z" fill="#2e7d32"/>
        <path d="M30 35L20 50L40 50L30 35Z" fill="#ff8c00"/>
      </svg>
    ),
    beet: (
      <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ opacity: 0.15 }}>
        <path d="M30 10L25 35L35 35L30 10Z" fill="#2e7d32"/>
        <circle cx="30" cy="45" r="12" fill="#8b0000"/>
      </svg>
    ),
    leaf: (
      <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ opacity: 0.15 }}>
        <path d="M30 10C20 15 15 25 20 35C25 40 35 40 40 35C45 25 40 15 30 10Z" fill="#2e7d32"/>
      </svg>
    ),
  }

  return (
    <div style={{ position: 'absolute', top: '10px', right: '10px', pointerEvents: 'none' }}>
      {illustrations[variant]}
    </div>
  )
}
