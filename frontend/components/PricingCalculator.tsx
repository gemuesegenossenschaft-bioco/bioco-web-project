'use client'

import { useState } from 'react'
import Link from 'next/link'

const ABO_PRICES = {
  'halb': { name: 'Halb Gemüsekorb', price: 750, shares: 1, hours: 20, arbeitseinsaetze: 10 },
  'standard': { name: 'Gemüsekorb (Standard)', price: 1280, shares: 2, hours: 40, arbeitseinsaetze: 20 },
  'doppel': { name: 'Doppelter Gemüsekorb', price: 2350, shares: 4, hours: 80, arbeitseinsaetze: 40 },
  'kein': { name: 'Kein Abo', price: 0, shares: 0, hours: 0, arbeitseinsaetze: 0 }
}

const SHARE_PRICE = 250

interface PricingCalculatorProps {
  onStartForm?: (data: { aboType: string, additionalShares: number, membershipType: string }) => void
}

export function PricingCalculator({ onStartForm }: PricingCalculatorProps) {
  const [selectedAbo, setSelectedAbo] = useState<'halb' | 'standard' | 'doppel' | 'kein'>('halb')
  const [additionalShares, setAdditionalShares] = useState(0)

  const aboData = ABO_PRICES[selectedAbo]
  const requiredShares = aboData.shares
  const totalShares = requiredShares + additionalShares
  const aboCost = aboData.price
  const sharesCost = totalShares * SHARE_PRICE
  const totalCost = aboCost + sharesCost

  return (
    <div className="pricing-calculator">
      <div className="abo-selector" style={{ marginBottom: '24px' }}>
        <div className="abo-buttons">
          <button
            type="button"
            className={`abo-button ${selectedAbo === 'halb' ? 'active' : ''}`}
            onClick={() => {
              setSelectedAbo('halb')
              setAdditionalShares(0)
            }}
          >
            Halb (1 Person)<br />
            <span className="price">CHF 750.-</span>
          </button>
          <button
            type="button"
            className={`abo-button ${selectedAbo === 'standard' ? 'active' : ''}`}
            onClick={() => {
              setSelectedAbo('standard')
              setAdditionalShares(0)
            }}
            style={{ position: 'relative' }}
          >
            Standard (2-3 Personen)
            <span className="recommended-badge" style={{ position: 'absolute', top: '-8px', right: '-8px' }}>Empfohlen</span>
            <br />
            <span className="price">CHF 1&apos;280.-</span>
          </button>
          <button
            type="button"
            className={`abo-button ${selectedAbo === 'doppel' ? 'active' : ''}`}
            onClick={() => {
              setSelectedAbo('doppel')
              setAdditionalShares(0)
            }}
          >
            Doppel (4-6 Personen)<br />
            <span className="price">CHF 2&apos;350.-</span>
          </button>
          <button
            type="button"
            className={`abo-button ${selectedAbo === 'kein' ? 'active' : ''}`}
            onClick={() => {
              setSelectedAbo('kein')
              setAdditionalShares(0)
            }}
          >
            Kein Abo<br />
            <span className="price">Nur Anteilsscheine</span>
          </button>
        </div>
      </div>

      <div className="pricing-table">
        <table>
          <thead>
            <tr>
              <th>Artikel</th>
              <th>Anzahl</th>
              <th>Einzelpreis</th>
              <th>Total</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {selectedAbo !== 'kein' && (
              <tr>
                <td>
                  {aboData.name}
                  <span className="payment-type-label" style={{ display: 'block', marginTop: '4px' }}>
                    <strong style={{ color: 'var(--bioco-green)', fontSize: '0.875rem' }}>Jährlicher Beitrag</strong>
                  </span>
                  <span className="mitarbeit-info" style={{ display: 'block', marginTop: '8px', fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
                    <strong>Mitarbeit pro Jahr:</strong> {aboData.arbeitseinsaetze} Arbeitseinsätze à 2 Stunden
                  </span>
                </td>
                <td>1</td>
                <td>CHF {aboData.price.toLocaleString('de-CH')}.-</td>
                <td><strong>CHF {aboData.price.toLocaleString('de-CH')}.-</strong></td>
                <td></td>
              </tr>
            )}
            <tr>
              <td>
                Anteilsscheine
                <span className="payment-type-label" style={{ display: 'block', marginTop: '4px' }}>
                  <strong style={{ color: 'var(--bioco-beet)', fontSize: '0.875rem' }}>Einmalige Zahlung</strong>
                </span>
                {selectedAbo !== 'kein' && requiredShares > 0 && (
                  <span className="text-sm" style={{ display: 'block', color: 'var(--text-secondary)', marginTop: '4px' }}>
                    ({requiredShares} erforderlich{additionalShares > 0 && ` + ${additionalShares} zusätzlich`})
                  </span>
                )}
                {selectedAbo === 'kein' && (
                  <span className="text-sm" style={{ display: 'block', color: 'var(--text-secondary)', marginTop: '4px' }}>
                    (ohne Gemüsekorb)
                  </span>
                )}
              </td>
              <td>{totalShares}</td>
              <td>CHF {SHARE_PRICE}.-</td>
              <td><strong>CHF {sharesCost.toLocaleString('de-CH')}.-</strong></td>
              <td>
                <div className="share-buttons-container">
                  <div className="share-buttons-label">
                    <strong>Zusätzliche Anteilsscheine hinzufügen:</strong>
                  </div>
                  <div className="share-buttons">
                    {additionalShares > 0 && (
                      <button
                        type="button"
                        className="btn-remove-share"
                        onClick={() => setAdditionalShares(prev => Math.max(0, prev - 1))}
                        aria-label="Ein Anteilsschein entfernen"
                        title="Ein Anteilsschein entfernen"
                      >
                        -1
                      </button>
                    )}
                    <button
                      type="button"
                      className="btn-add-share"
                      onClick={() => setAdditionalShares(prev => prev + 1)}
                      aria-label="Ein zusätzlicher Anteilsschein hinzufügen"
                      title="Ein zusätzlicher Anteilsschein hinzufügen"
                    >
                      +1
                    </button>
                  </div>
                </div>
              </td>
            </tr>
            <tr className="total-row">
              <td colSpan={3}><strong>Total</strong></td>
              <td colSpan={2}><strong className="total-amount">CHF {totalCost.toLocaleString('de-CH')}.-</strong></td>
            </tr>
          </tbody>
        </table>
      </div>

      {selectedAbo === 'kein' && additionalShares === 0 && (
        <div style={{ marginTop: '16px', padding: '16px', background: 'var(--bg-secondary)', borderRadius: '12px' }}>
          <p style={{ margin: 0, fontSize: '0.875rem' }}>
            <strong>💡 Info:</strong> Du kannst Anteilsscheine auch ohne Gemüsekorb erwerben. 
            Genossenschafter/innen haben Vorrang auf der Warteliste für einen Gemüsekorb.
          </p>
        </div>
      )}

      {(selectedAbo !== 'kein' || additionalShares > 0) && (
        <div style={{ marginTop: '24px', textAlign: 'center' }}>
          <Link
            href={`/anmeldung?abo=${selectedAbo === 'kein' ? 'kein' : selectedAbo}&shares=${totalShares}&additional=${additionalShares}`}
            className="btn btn-primary"
            style={{ display: 'inline-block', fontSize: '1.125rem', padding: '16px 32px' }}
          >
            Jetzt mitmachen!
          </Link>
          <p style={{ marginTop: '12px', fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
            Deine Auswahl wird automatisch ins Anmeldeformular übernommen
          </p>
        </div>
      )}
    </div>
  )
}
