'use client'

import { useEffect, useRef } from 'react'

// Geisshof location
const geisshofLocation = {
  name: 'Geisshof',
  address: 'Geisslistrasse, Gebenstorf, Schweiz',
  lat: 47.4741684,
  lng: 8.2456318,
}

export function GeisshofMap() {
  const mapContainerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!mapContainerRef.current) return

    // Load Leaflet CSS
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='
    link.crossOrigin = ''
    document.head.appendChild(link)

    // Load Leaflet JS
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
    script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='
    script.crossOrigin = ''
    
    script.onload = () => {
      // Initialize map - non-interactive
      const L = (window as any).L
      const map = L.map(mapContainerRef.current!, {
        dragging: false,
        touchZoom: false,
        doubleClickZoom: false,
        scrollWheelZoom: false,
        boxZoom: false,
        keyboard: false,
        zoomControl: false,
      }).setView([geisshofLocation.lat, geisshofLocation.lng], 14)

      // Add OpenStreetMap tiles
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
      }).addTo(map)

      // Add marker for Geisshof
      const marker = L.marker([geisshofLocation.lat, geisshofLocation.lng]).addTo(map)
      const popupContent = `
        <div style="padding: 8px;">
          <strong>Geisshof</strong><br>
          ${geisshofLocation.address}<br>
          <a href="https://maps.app.goo.gl/1ESuXVJwUUEd5SzX8" 
             target="_blank" 
             rel="noopener noreferrer"
             style="display: inline-block; margin-top: 8px; padding: 12px 24px; background: #2e7d32; color: #ffffff; border: 2px solid #2e7d32; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 1rem; font-family: 'DM Sans', sans-serif; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.08);">
            Route anzeigen →
          </a>
        </div>
      `
      marker.bindPopup(popupContent).openPopup()
    }

    document.body.appendChild(script)

    return () => {
      // Cleanup
      if (link.parentNode) link.parentNode.removeChild(link)
      if (script.parentNode) script.parentNode.removeChild(script)
    }
  }, [])

  return (
    <>
      <div className="map-container">
        <div ref={mapContainerRef} className="map-wrapper" />
      </div>
      
      <div className="location-info-box">
        <div className="location-card-combined">
          <div className="location-address-section">
            <h4>Adresse</h4>
            <div className="address-item">
              <strong>{geisshofLocation.name}</strong>
              <p>{geisshofLocation.address}</p>
            </div>
          </div>
          
          <div className="location-route-section">
            <h4>Route</h4>
            <div className="direction-item">
              <strong>{geisshofLocation.name}</strong>
              <a
                href="https://maps.app.goo.gl/1ESuXVJwUUEd5SzX8"
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-primary"
              >
                Route planen →
              </a>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
