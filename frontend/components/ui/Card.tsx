import type { ComponentPropsWithoutRef, ElementType, ReactNode } from 'react'

export type CardVariant = 'bento' | 'soft' | 'outlined' | 'plain'

// D11 (GH #85): only 'bento' injects a shared class (.bento-card, styled via
// --card-radius/--card-shadow in globals.css). 'soft'/'outlined'/'plain' name
// the CardsGridBlock-style treatments but carry no built-in styling of their
// own — those call sites compute their own background/border/radius (values
// come from CMS config) and pass them through `style`, so call sites that
// rely on a bare className (portal-tile, summary-card, past-event-tile) stay
// visually untouched (those classNames already own 100% of their look).
const VARIANT_CLASS: Partial<Record<CardVariant, string>> = {
  bento: 'bento-card',
}

type CardOwnProps<T extends ElementType> = {
  as?: T
  variant: CardVariant
  children?: ReactNode
}

type CardProps<T extends ElementType> = CardOwnProps<T> & Omit<ComponentPropsWithoutRef<T>, 'as' | 'children'>

export function Card<T extends ElementType = 'div'>({ as, variant, className, children, ...rest }: CardProps<T>) {
  const Tag = (as ?? 'div') as ElementType
  const classes = [VARIANT_CLASS[variant], className as string | undefined].filter(Boolean).join(' ')
  return (
    <Tag className={classes || undefined} {...rest}>
      {children}
    </Tag>
  )
}
