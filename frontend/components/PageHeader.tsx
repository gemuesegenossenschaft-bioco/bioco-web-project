import { ReactNode } from 'react'

interface PageHeaderProps {
  eyebrow?: string
  title: ReactNode
  intro?: ReactNode
}

/**
 * Canonical page heading (C.3). Exactly one <h1> per page, with optional
 * eyebrow above and intro below. Shell-aligned + tokenized spacing via
 * the .page-header CSS rules, so the lead heading sits identically on
 * hardcoded and CMS-driven pages.
 */
export function PageHeader({ eyebrow, title, intro }: PageHeaderProps) {
  return (
    <header className="page-header">
      {eyebrow ? <p className="page-header-eyebrow">{eyebrow}</p> : null}
      <h1 className="page-header-title">{title}</h1>
      {intro ? <p className="page-header-intro">{intro}</p> : null}
    </header>
  )
}
