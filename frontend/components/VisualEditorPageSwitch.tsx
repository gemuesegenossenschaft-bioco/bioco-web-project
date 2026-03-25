'use client'

import { useSearchParams } from 'next/navigation'
import type { ReactNode } from 'react'
import type { ContentSection } from '@/lib/processwire-types'
import { CmsVisualEditorPage } from '@/components/CmsVisualEditorPage'

interface VisualEditorPageSwitchProps {
  sections: ContentSection[]
  children: ReactNode
}

export function VisualEditorPageSwitch({ sections, children }: VisualEditorPageSwitchProps) {
  const searchParams = useSearchParams()
  const isVisualEditor = searchParams.get('_visual') === '1'

  if (isVisualEditor) {
    return <CmsVisualEditorPage sections={sections} />
  }

  return <>{children}</>
}
