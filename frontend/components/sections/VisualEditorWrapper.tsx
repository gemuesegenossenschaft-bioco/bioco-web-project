'use client'

import { useSearchParams } from 'next/navigation'
import { useVisualEditor } from '@/hooks/useVisualEditor'
import { InlineVisualEditorRuntime } from '@/components/visual-editor/InlineVisualEditorRuntime'
import { SectionRenderer } from './SectionRenderer'
import type { ContentSection } from '@/lib/processwire-types'

interface VisualEditorWrapperProps {
  sections: ContentSection[]
  isEditing?: boolean
}

export function VisualEditorWrapper({ sections: initialSections, isEditing = false }: VisualEditorWrapperProps) {
  const searchParams = useSearchParams()
  const isVisualEditor = searchParams.get('_visual') === '1'
  const { sections, highlightedSectionId } = useVisualEditor({
    enabled: isVisualEditor,
    sections: initialSections,
  })

  if (!isVisualEditor) {
    return <SectionRenderer sections={initialSections} isEditing={isEditing} />
  }

  return (
    <>
      <InlineVisualEditorRuntime enabled={isVisualEditor} sections={sections} />
      <style dangerouslySetInnerHTML={{ __html: `
        [data-section-id] {
          position: relative;
          cursor: pointer;
          transition: outline 0.15s;
        }
        [data-section-id]:hover {
          outline: 2px dashed rgba(74, 124, 89, 0.6);
          outline-offset: -2px;
        }
        [data-section-id]:hover::after {
          content: attr(data-section-layout);
          position: absolute;
          top: 4px;
          right: 4px;
          background: rgba(74, 124, 89, 0.9);
          color: #fff;
          font-size: 11px;
          padding: 2px 8px;
          border-radius: 4px;
          z-index: 10;
          pointer-events: none;
        }
        ${highlightedSectionId ? `
        [data-section-id="${highlightedSectionId}"] {
          outline: 3px solid #4a7c59 !important;
          outline-offset: -3px !important;
        }
        ` : ''}
      `}} />
      <SectionRenderer
        sections={sections}
        isEditing={isEditing}
        visualEditor={true}
      />
    </>
  )
}
