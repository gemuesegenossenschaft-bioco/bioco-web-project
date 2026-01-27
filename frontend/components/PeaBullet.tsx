import { ReactNode } from 'react'

interface PeaBulletProps {
  children: ReactNode
}

export function PeaBullet({ children }: PeaBulletProps) {
  return (
    <li className="pea-bullet">
      <span className="pea-bullet-content">{children}</span>
    </li>
  )
}
