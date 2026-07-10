'use client'

import type {
  CSSProperties,
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react'

export type FieldVariant = 'bare' | 'bootstrap' | 'compact'

const INLINE_LABEL_STYLE: CSSProperties = {
  display: 'block',
  marginBottom: 'var(--space-2)',
  fontWeight: 600,
}

function bootstrapClassName(base: string, invalid: boolean | undefined, className?: string): string {
  return [base, invalid ? 'is-invalid' : null, className || null].filter(Boolean).join(' ')
}

interface FormFieldProps {
  variant?: 'group' | 'inline'
  label?: ReactNode
  htmlFor?: string
  error?: ReactNode
  /** 'inline' variant only: wrapper margin-bottom (EventSignupForm varies this per field). */
  spacing?: string
  children: ReactNode
}

export function FormField({ variant = 'group', label, htmlFor, error, spacing = 'var(--space-4)', children }: FormFieldProps) {
  if (variant === 'inline') {
    return (
      <div style={{ marginBottom: spacing }}>
        {label ? (
          <label htmlFor={htmlFor} style={INLINE_LABEL_STYLE}>
            {label}
          </label>
        ) : null}
        {children}
      </div>
    )
  }

  return (
    <div className="form-group">
      {label ? <label htmlFor={htmlFor}>{label}</label> : null}
      {children}
      {error ? <div className="invalid-feedback">{error}</div> : null}
    </div>
  )
}

type TextInputProps = InputHTMLAttributes<HTMLInputElement> & {
  variant?: FieldVariant
  invalid?: boolean
}

export function TextInput({ variant = 'bare', invalid, className, ...rest }: TextInputProps) {
  if (variant === 'bootstrap') {
    return <input className={bootstrapClassName('form-control', invalid, className)} {...rest} />
  }
  if (variant === 'compact') {
    return <input className={['form-control-compact', className].filter(Boolean).join(' ')} {...rest} />
  }
  return <input className={className} {...rest} />
}

type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  variant?: FieldVariant
  invalid?: boolean
}

export function TextArea({ variant = 'bare', invalid, className, ...rest }: TextAreaProps) {
  if (variant === 'bootstrap') {
    return <textarea className={bootstrapClassName('form-control', invalid, className)} {...rest} />
  }
  if (variant === 'compact') {
    return <textarea className={['form-control-compact', className].filter(Boolean).join(' ')} {...rest} />
  }
  return <textarea className={className} {...rest} />
}

type SelectFieldProps = SelectHTMLAttributes<HTMLSelectElement> & {
  variant?: FieldVariant
  invalid?: boolean
}

export function SelectField({ variant = 'bare', invalid, className, children, ...rest }: SelectFieldProps) {
  if (variant === 'bootstrap') {
    return (
      <select className={bootstrapClassName('form-control', invalid, className)} {...rest}>
        {children}
      </select>
    )
  }
  return (
    <select className={className} {...rest}>
      {children}
    </select>
  )
}

type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: ReactNode
}

export function Checkbox({ label, ...rest }: CheckboxProps) {
  return (
    <div className="form-group">
      <label>
        <input type="checkbox" {...rest} />
        {label}
      </label>
    </div>
  )
}
