'use client'

import { useEffect, useRef, useState } from 'react'

const MONTHS = [
  'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
  'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
]

// Anbauplan basierend auf den bereitgestellten Listen
const SEASONAL_DATA: Record<string, string[]> = {
  januar: [
    'Yacon', 'Wirz', 'Nüssli', 'Federkohl', 'Lauch', 'Kürbis', 'Randen', 
    '(Sellerie)', 'Zwiebeln', 'Kartoffeln', 'Knoblauch', 'Rüebli', 'Asiasalate'
  ],
  februar: [
    'Wirz', 'Zwiebeln', 'Federkohl', 'Lauch', 'Rüebli', 'Randen', 'Kartoffeln', 
    'Knoblauch', '(Sellerie)', 'Krautstiel', 'Nüssli', 'Yacon', 'Asiasalate'
  ],
  märz: [
    'Federkohl', 'Krautstiel', 'Lauch', 'Randen', 'Sellerie', 'Spinat', 
    'Salate', 'Nüssli', 'Rüebli', 'Zwiebeln', 'Kartoffeln', 'Knoblauch', 'Asiasalate'
  ],
  april: [
    'Blumenkohl', 'Lauch', 'Randen', '(Sellerie)', 'Salate', 'Rüebli', 
    'Zwiebeln', 'Spinat', 'Kartoffeln', 'Knoblauch', 'Krautstiel', 'Asiasalate'
  ],
  mai: [
    'Fenchel', 'Randen', 'Radiesli', 'Blumenkohl', 'Spinat', 'Sellerie', 
    'Salate', 'Lattich', 'Kartoffeln', 'Kräuter', 'Krautstiel', 'Kohlrabi'
  ],
  juni: [
    'Fenchel', 'Broccoli', 'Radiesli', 'Knackerbsen', 'Spinat', 'Dicke Bohnen', 
    'Salate', 'Lattich', 'Kefen', 'Frühkartoffeln', 'Kräuter', 'Kohlrabi', 'Lauch-/Frühlingszwiebeln'
  ],
  juli: [
    'Knackerbsen', 'Fenchel', 'Gurken', 'Buschbohnen', 'Krautstiel', 'Frühkartoffel', 
    'Tomaten', 'Radiesli', 'Zucchetti', 'Kohlrabi', 'Salate', 'Aubergine', 
    'Lauchzwiebeln', 'Knoblauch', 'Rüebli', 'Kräuter'
  ],
  august: [
    'Rettich', 'Buschbohnen', 'Gurken', 'Stangenbohnen', 'Krautstiel', 'Rondini', 
    'Tomaten', 'Peperoni', 'Zucchetti', 'Aubergine', 'Salate', 'Randen', 
    'Zwiebeln', 'Knoblauch', 'Rüebli', 'Kräuter', 'Frühkartoffeln', 'Süssmais'
  ],
  september: [
    'Fenchel', 'Auberginen', 'Gurken', 'Buschbohnen', 'Spinat', 'Stangenbohnen', 
    'Lauch', 'Rondini', 'Tomaten', 'Zucchetti', 'Salate', 'Stangensellerie', 
    'Rüebli', 'Knoblauch', 'Kartoffeln', 'Kräuter', 'Krautstiel', 'Zwiebeln'
  ],
  oktober: [
    'Fenchel', 'Kohlrabi', 'Rettich', 'Gurken', 'Wirz', 'Chinakohl', 
    'Spinat', 'Krautstiel', 'Yacon', 'Lauch', 'Rondini', 'Pak Choi', 
    'Randen', 'Peperoni', 'Rucola', 'Tomaten', 'Sellerie', 'Radicchio', 
    'Salate', 'Kürbisse', 'Zwiebeln', 'Rüebli', 'Knoblauch', 'Kartoffeln', 'Kräuter'
  ],
  november: [
    'Fenchel', 'Kohlrabi', 'Zuckerhut', 'Wirz', 'Chinakohl', 'Asiasalate', 
    'Endivien', 'Federkohl', 'Portulak', 'Lauch', 'Kürbis', 'Rondini', 
    'Randen', 'Pastinaken', 'Sellerie', 'Salate', 'Nüssli', 'Yacon', 
    'Rüebli', 'Knoblauch', 'Zwiebeln', 'Kartoffeln', 'Pak Choi', 'Hirschhorn', 
    'Rotkraut', 'Radicchio'
  ],
  dezember: [
    'Zuckerhut', 'Kohlrabi', 'Sellerie', 'Wirz', 'Chinakohl', 'Yacon', 
    'Asiasalate', 'Endivien', 'Zwiebeln', 'Lauch', 'Kürbis', 'Rotkraut', 
    'Randen', 'Pastinaken', 'Salate', 'Nüssli', 'Rüebli', 'Knoblauch', 
    'Kartoffeln', 'Federkohl'
  ],
}

export function Saisonkalender() {
  const [activeMonth, setActiveMonth] = useState(new Date().getMonth())
  const monthButtonRefs = useRef<Array<HTMLButtonElement | null>>([])

  const monthKey = MONTHS[activeMonth].toLowerCase()
  const vegetables = SEASONAL_DATA[monthKey] || []

  useEffect(() => {
    monthButtonRefs.current[activeMonth]?.scrollIntoView({
      behavior: 'smooth',
      block: 'nearest',
      inline: 'center',
    })
  }, [activeMonth])

  const goToPreviousMonth = () => {
    setActiveMonth((prev) => (prev === 0 ? MONTHS.length - 1 : prev - 1))
  }

  const goToNextMonth = () => {
    setActiveMonth((prev) => (prev === MONTHS.length - 1 ? 0 : prev + 1))
  }

  return (
    <div className="saisonkalender">
      <div className="kalender-mobile-stepper" aria-label="Monat wechseln">
        <button
          type="button"
          className="kalender-mobile-stepper-btn"
          onClick={goToPreviousMonth}
          aria-label="Vorheriger Monat"
        >
          &lt;
        </button>
        <span className="kalender-mobile-stepper-current">{MONTHS[activeMonth]}</span>
        <button
          type="button"
          className="kalender-mobile-stepper-btn"
          onClick={goToNextMonth}
          aria-label="Nächster Monat"
        >
          &gt;
        </button>
      </div>

      {/* Desktop: short tabs, Mobile: full tabs with swipe */}
      <div className="kalender-tabs">
        {MONTHS.map((month, index) => (
          <button
            key={month}
            ref={(el) => {
              monthButtonRefs.current[index] = el
            }}
            type="button"
            className={`kalender-tab ${activeMonth === index ? 'active' : ''}`}
            onClick={() => setActiveMonth(index)}
            aria-label={`Monat ${month} anzeigen`}
          >
            <span className="kalender-tab-short">{month.substring(0, 3)}</span>
            <span className="kalender-tab-full">{month}</span>
          </button>
        ))}
      </div>
      <select
        className="kalender-select"
        value={activeMonth}
        onChange={(e) => setActiveMonth(Number(e.target.value))}
      >
        {MONTHS.map((month, index) => (
          <option key={month} value={index}>
            {month}
          </option>
        ))}
      </select>

      <div className="kalender-content">
        <ul className="vegetable-list">
          {vegetables.length > 0 ? (
            vegetables.map((item, i) => <li key={i}>{item}</li>)
          ) : (
            <li className="empty">Keine Angaben verfügbar</li>
          )}
        </ul>
      </div>
    </div>
  )
}
