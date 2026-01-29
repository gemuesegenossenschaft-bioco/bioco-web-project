'use client'

import { useState } from 'react'
import { getStaticEventItems, AktuellesItem } from './AktuellesData'
import { EventSignupForm } from './EventSignupForm'

export function SchnuppertageSection() {
  const [selectedEvent, setSelectedEvent] = useState<AktuellesItem | null>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)

  const schnuppertage = getStaticEventItems().filter(
    (item) => item.status !== 'past' && item.title.toLowerCase().includes('schnuppertag')
  ).slice(0, 3)

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
      <section id="D-02b" style={{ marginBottom: 'clamp(48px, 8vw, 96px)' }}>
        <h2>Schnuppertage</h2>
        <h3 style={{ fontSize: '1.25rem', marginTop: '16px', marginBottom: '12px', color: 'var(--bioco-green-dark)' }}>
          Komm schnuppern: So geht solidarischer Gemüseanbau.
        </h3>
        <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
          Möchtest du dein Gemüse in Gemeinschaft anbauen und erfahren, wie es sich anfühlt, Teil einer Solawi zu sein?
          Dann komm an einen unserer Schnuppertage vorbei. Geniesse einen Nachmittag auf dem Geisshof in Gebenstorf AG,
          auf dem Feld umgeben von Natur und Tieren, Wildpflanzen, Bäumen, Beerensträuchern und Kräuterspirale.
        </p>

        {/* General description - shown once */}
        <div style={{ 
          background: 'var(--bg-secondary)', 
          padding: '24px', 
          borderRadius: '12px', 
          marginBottom: '24px' 
        }}>
          <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px' }}>
            <strong>Was dich erwartet:</strong>
          </p>
          <ul style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '16px', paddingLeft: '20px' }}>
            <li>Gemeinschaft auf dem Feld, umgeben von Natur</li>
            <li>Unser Hof liegt auf einem Hügel über Gebenstorf AG</li>
            <li>Deine Hilfe auf dem Feld</li>
            <li>Danke: du bekommst eine Tasche frisch geerntetes Demeter-Gemüse</li>
            <li>Kleines zVieri von uns spendiert</li>
            <li>Hof und Demeteranbau kennenlernen</li>
            <li>Möglichkeit anschliessend auf dem Gemeinschaftsplatz zu bräteln</li>
          </ul>
          <p style={{ fontSize: '1.05rem', lineHeight: '1.7', color: 'var(--text-secondary)', marginBottom: '0' }}>
            Uns ist ein achtsamer Umgang mit der Natur wichtig. Wir lassen viel Platz für Wildpflanzen, haben eine Kräuterspirale,
            eine Naschecke mit Beeren, Sandkasten und Enten auf dem Hof. Auf dem Gemeinschaftsplatz hat es einen Sandkasten für Kinder
            und eine Feuerstelle. Nach dem Schnuppernachmittag darfst du gerne noch bleiben und etwas grillieren.
          </p>
        </div>

        {/* Compact date list */}
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

        <p style={{ fontSize: '0.9rem', fontStyle: 'italic', color: 'var(--text-secondary)', marginTop: '16px' }}>
          Hinweis: Formulareingänge gehen an medien@bioco.ch.
        </p>
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
