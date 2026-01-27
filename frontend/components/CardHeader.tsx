import React from 'react'

interface CardHeaderProps {
  title: string
  subtitle?: string
  className?: string
}

export function CardHeader({ title, subtitle, className = '' }: CardHeaderProps) {
  return (
    <div className={`card-header ${className}`}>
      <h3>{title}</h3>
      {subtitle && (
        <>
          <div className="card-header-divider"></div>
          <h4 className="card-title">{subtitle}</h4>
        </>
      )}
    </div>
  )
}

