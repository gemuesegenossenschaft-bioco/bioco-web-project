'use client'

import { useState } from 'react'
import type { AktuellesItem } from './AktuellesData'
import { filterSchnuppertage } from './AktuellesData'
import { useEventsFeed } from '@/hooks/useEventsFeed'
import { EventSignupForm } from './EventSignupForm'
import { getVeFieldAttrs } from '@/components/visual-editor/fieldAttrs'
import { getResolvedComponentConfig } from '@/lib/componentRegistry'
import type { ContentSection, SectionConfigObject } from '@/lib/processwire-types'

// Editorial copy (heading, subheading + intro prose, the "Was dich erwartet"
// list and the closing paragraph) is CMS-driven: section_title -> heading,
// section_text -> subheading + intro (rich text), section_config -> list label,
// item1..item7 and the closing paragraph. The live useEventsFeed date list and
// the signup modal/button below stay code-owned (functional, not content).
const SCHNUPPERTAGE_ITEM_COUNT = 7

interface SchnuppertageSectionProps {
  section?: ContentSection
  visualEditor?: boolean
}

function configString(config: SectionConfigObject, key: string): string {
  const value = config[key]
  return value == null ? '' : String(value)
}

export function SchnuppertageSection({ section, visualEditor = false }: SchnuppertageSectionProps) {
  const [selectedEvent, setSelectedEvent] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const { upcoming } = useEventsFeed()

  const schnuppertage = filterSchnuppertage(upcoming).slice(0, 3)

  const sectionId = section?.id || ''
  const config = getResolvedComponentConfig(section?.component || 'schnuppertage', section?.config)
  const listLabel = configString(config, 'list_label')
  const closing = configString(config, 'closing')
  const listItems: string[] = []
  for (let n = 1; n <= SCHNUPPERTAGE_ITEM_COUNT; n++) {
    const value = configString(config, `item${n}`).trim()
    if (value) listItems.push(value)
  }

  const openModal = (event: AktuellesItem) => {
    setSelectedEvent(event)
    setIsModalOpen(true)
    document.body.style.overflow = 'hidden'
  }

  const closeModal = () => {
    setIsModalOpen(false)
    setSelectedEvent(null)
    document.body.style.overflow = 'unset'
  }

  const handleSignupSuccess = () => {
    setTimeout(() => closeModal(), 2000)
  }

  return (
    <>
      <section id="D-02b">
        {section?.title ? (
          <h2 {...getVeFieldAttrs(visualEditor, sectionId, 'title', 'text', true)}>{section.title}</h2>
        ) : null}
        {section?.text ? (
          <div
            className="cms-section-text"
            style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}
            {...getVeFieldAttrs(visualEditor, sectionId, 'text', 'richtext', true)}
            dangerouslySetInnerHTML={{ __html: section.text }}
          />
        ) : null}

        {/* "Was dich erwartet" info box — label, list items and closing come
            from section_config (CMS-editable). The grey card styling is code-owned. */}
        {(listLabel || listItems.length > 0 || closing) ? (
          <div
            style={{
              background: 'var(--bg-secondary)',
              padding: '24px',
              borderRadius: '12px',
              marginBottom: '24px',
            }}
            {...getVeFieldAttrs(visualEditor, sectionId, 'component', 'structured', false)}
          >
            {listLabel ? (
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
                <strong>{listLabel}</strong>
              </p>
            ) : null}
            {listItems.length > 0 ? (
              <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px', paddingLeft: '20px' }}>
                {listItems.map((item, idx) => (
                  <li key={idx}>{item}</li>
                ))}
              </ul>
            ) : null}
            {closing ? (
              <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '0' }}>
                {closing}
              </p>
            ) : null}
          </div>
        ) : null}

        {/* Compact date list — live from useEventsFeed (code-owned, functional) */}
        <h4 style={{ fontSize: '1.1rem', marginBottom: '16px', color: 'var(--text-primary)' }}>
          Nächste Termine
        </h4>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
          {schnuppertage.map((item, idx) => (
            <div
              key={item.id || idx}
              style={{
                background: 'rgba(var(--bioco-green-rgb), 0.15)',
                padding: '16px 20px',
                borderRadius: '12px',
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: '12px'
              }}
            >
              <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                <strong style={{ color: 'var(--bioco-green-dark)', fontSize: '1.05rem' }}>{item.title}</strong>
                <span style={{ color: 'var(--text-secondary)', fontSize: '0.95rem' }}>
                  {item.date} {item.timeLabel && `· ${item.timeLabel}`}
                </span>
              </div>
              <button
                type="button"
                onClick={() => openModal(item)}
                className="btn btn-primary btn-organic"
              >
                Jetzt anmelden
              </button>
            </div>
          ))}
        </div>
      </section>

      {/* Modal */}
      {isModalOpen && selectedEvent && (
        <>
          {/* Overlay */}
          <div
            onClick={closeModal}
            style={{
              position: 'fixed',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              background: 'rgba(0, 0, 0, 0.5)',
              zIndex: 1000,
              backdropFilter: 'blur(4px)'
            }}
          />

          {/* Modal Content */}
          <div
            style={{
              position: 'fixed',
              top: '50%',
              left: '50%',
              transform: 'translate(-50%, -50%)',
              background: 'white',
              borderRadius: '16px',
              maxWidth: '500px',
              width: '90%',
              maxHeight: '90vh',
              overflow: 'auto',
              zIndex: 1001,
              boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'
            }}
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div style={{
              position: 'sticky',
              top: 0,
              background: 'white',
              borderBottom: '1px solid var(--border-color)',
              padding: '16px 24px',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              zIndex: 10
            }}>
              <div>
                <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
                  {selectedEvent.date}
                </div>
                <h3 style={{ margin: 0, fontSize: '1.25rem' }}>{selectedEvent.title}</h3>
              </div>
              <button
                onClick={closeModal}
                style={{
                  background: 'transparent',
                  border: 'none',
                  fontSize: '1.5rem',
                  cursor: 'pointer',
                  padding: '4px 8px',
                  color: 'var(--text-secondary)',
                  lineHeight: 1
                }}
                aria-label="Schließen"
              >
                ×
              </button>
            </div>

            {/* Event Details */}
            <div style={{ padding: '16px 24px', borderBottom: '1px solid var(--border-color)' }}>
              <div style={{
                padding: '12px 16px',
                background: 'var(--bg-secondary)',
                borderRadius: '8px',
                fontSize: '0.95rem'
              }}>
                <div style={{ marginBottom: '4px' }}>
                  <strong>Ort:</strong> Geisshof, Geisslistrasse, 5412 Gebenstorf
                </div>
                <div>
                  <strong>Zeit:</strong> {selectedEvent.timeLabel || '14:00 - 17:00 Uhr'}
                </div>
              </div>
            </div>

            {/* Signup Form */}
            <EventSignupForm
              eventTitle={selectedEvent.title}
              eventId={selectedEvent.id}
              onSuccess={handleSignupSuccess}
              onCancel={closeModal}
            />
          </div>
        </>
      )}
    </>
  )
}
