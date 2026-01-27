'use client'

import { useState } from 'react'

interface InfoTooltipProps {
  content: string
  children?: React.ReactNode
}

export function InfoTooltip({ content, children }: InfoTooltipProps) {
  const [isOpen, setIsOpen] = useState(false)

  return (
    <span className="info-tooltip-wrapper">
      <button
        type="button"
        className="info-tooltip-trigger"
        onClick={() => setIsOpen(!isOpen)}
        onBlur={() => setIsOpen(false)}
        aria-label="Information"
      >
        {children || '?'}
      </button>
      {isOpen && (
        <div className="info-tooltip-content">
          {content}
        </div>
      )}
    </span>
  )
}
