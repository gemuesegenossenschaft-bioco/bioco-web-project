'use client'

import Link from 'next/link'
import type { AnchorHTMLAttributes, ButtonHTMLAttributes, InputHTMLAttributes, ReactNode } from 'react'

export type ButtonVariant = 'primary' | 'secondary' | 'orange'

interface ButtonClassNameOptions {
  variant: ButtonVariant
  organic?: boolean
  className?: string
}

function buttonClassName({ variant, organic, className }: ButtonClassNameOptions): string {
  return ['btn', `btn-${variant}`, organic ? 'btn-organic' : null, className || null]
    .filter(Boolean)
    .join(' ')
}

interface CommonProps {
  variant: ButtonVariant
  organic?: boolean
  className?: string
}

type LinkButtonProps = CommonProps &
  Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'className' | 'href'> & {
    as: 'a'
    href: string
    children: ReactNode
  }

type NativeButtonProps = CommonProps &
  Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'className'> & {
    as?: 'button'
    children: ReactNode
  }

type SubmitInputProps = CommonProps &
  Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'type' | 'value'> & {
    as: 'input'
    value: string
  }

export type ButtonProps = LinkButtonProps | NativeButtonProps | SubmitInputProps

export function Button(props: ButtonProps) {
  const classes = buttonClassName(props)

  if (props.as === 'a') {
    const { as, variant, organic, className, href, children, ...rest } = props
    return (
      <Link href={href} className={classes} {...rest}>
        {children}
      </Link>
    )
  }

  if (props.as === 'input') {
    const { as, variant, organic, className, value, ...rest } = props
    return <input type="submit" value={value} className={classes} {...rest} />
  }

  const { as, variant, organic, className, children, ...rest } = props
  return (
    <button className={classes} {...rest}>
      {children}
    </button>
  )
}
