'use client'

import { useEffect, useRef } from 'react'

interface DepotLocation {
  id: string
  name: string
  address: string
  lat: number
  lng: number
  day: 'Dienstag' | 'Freitag' | 'Beide'
  contact?: string
  website?: string
  notes?: string
}

// Specific depot locations from Gemüseausliefertour document
const depotLocations: DepotLocation[] = [
  // Dienstag (Tuesday) depots
  {
    id: 'chraettli',
    name: 'Depot Chrättli',
    address: 'Allmendstrasse 16, 5400 Baden',
    lat: 47.4685,
    lng: 8.2985,
    day: 'Dienstag',
    contact: 'Corona Banky',
    website: 'https://www.xn--chrttli-7wa.ch/',
    notes: 'Das Depot befindet sich unter der Rampe rechts neben dem Quartierladen.'
  },
  {
    id: 'ohne',
    name: 'Depot Ohne',
    address: 'Stadtturmstrasse 15, 5400 Baden',
    lat: 47.4740,
    lng: 8.3080,
    day: 'Dienstag',
    contact: 'Tobias Kloter',
    website: 'https://www.ohne.ch/',
    notes: 'Die Körbe stehen unter den Tischen im hinteren Bereich.'
  },
  {
    id: 'anixis',
    name: 'Depot Anixis',
    address: 'Oberstadtstrasse 10, Galerie Anixis, 5400 Baden',
    lat: 47.4720,
    lng: 8.3100,
    day: 'Dienstag',
    contact: 'Josef Lindiridi',
    website: 'https://anixis.ch/',
    notes: 'Hinter der Barriere auf dem Materiallager.'
  },
  {
    id: 'casa-flora',
    name: 'Casa Flora',
    address: 'Zurzacherstrasse 171, 5200 Brugg',
    lat: 47.4920,
    lng: 8.2150,
    day: 'Dienstag',
    contact: 'David Müller',
    notes: 'In der Nische beim hinteren Eingang des Blumengeschäfts (Zufahrt via Hauptstrasse).'
  },
  // Freitag (Friday) depots
  {
    id: 'geisshof',
    name: 'Depot Geisshof',
    address: 'Geisshof, Gebenstorf',
    lat: 47.4825,
    lng: 8.2430,
    day: 'Freitag',
    contact: 'Matthias Müller',
    notes: 'Direkt auf dem Hof.'
  },
  {
    id: 'kupperhaus',
    name: 'Depot Kupperhaus',
    address: 'Schulthess-Allee 4, 5200 Brugg',
    lat: 47.4854,
    lng: 8.2083,
    day: 'Freitag',
    contact: 'Brigitte Perren Henneck',
    notes: 'Unten an der Rampe (Zufahrt rückwärts neben dem Kupperhaus).'
  },
  {
    id: 'ennetbaden',
    name: 'Depot Ennetbaden',
    address: 'Geissbergstrasse 17, 5408 Ennetbaden',
    lat: 47.4811,
    lng: 8.3194,
    day: 'Freitag',
    contact: 'Nils und Armelle George',
    notes: 'Beim Wohnhaus.'
  },
  {
    id: 'lemonia',
    name: 'Depot Lemonia',
    address: 'Schartenstrasse 28, 5430 Wettingen',
    lat: 47.4676,
    lng: 8.3277,
    day: 'Freitag',
    contact: 'Martin Gruchow',
    website: 'http://lemonia.ch/',
    notes: 'Hinter dem Haus unter dem Tisch.'
  },
  {
    id: 'laegernstrasse',
    name: 'Depot Lägernstrasse',
    address: 'Lägernstrasse 6, 5430 Wettingen',
    lat: 47.4600,
    lng: 8.3200,
    day: 'Freitag',
    contact: 'Helen Matthäus',
    notes: 'Beim Wohnhaus.'
  },
]

export function DepotMap() {
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
      }).setView([47.4734, 8.3089], 12)

      // Add OpenStreetMap tiles
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
      }).addTo(map)

      // Add markers for each depot
      depotLocations.forEach((depot) => {
        const marker = L.marker([depot.lat, depot.lng]).addTo(map)
        const popupContent = `
          <div style="padding: 8px;">
            <strong>${depot.name}</strong><br>
            ${depot.address}<br>
            <small><strong>${depot.day}</strong></small><br>
            ${depot.contact ? `<small>Kontakt: ${depot.contact}</small><br>` : ''}
            ${depot.website ? `<small><a href="${depot.website}" target="_blank" rel="noopener noreferrer">Website</a></small>` : ''}
          </div>
        `
        marker.bindPopup(popupContent)
      })

      // Fit map to show all markers
      const group = new L.FeatureGroup(depotLocations.map(d => L.marker([d.lat, d.lng])))
      map.fitBounds(group.getBounds().pad(0.1))
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
        <div className="location-addresses">
          <h4>Depot-Standorte</h4>
          <div className="address-list">
            <div style={{ marginBottom: 'var(--spacing-md)' }}>
              <h5 style={{ color: 'var(--bioco-green)', marginBottom: 'var(--spacing-sm)' }}>Dienstag</h5>
              {depotLocations.filter(d => d.day === 'Dienstag').map((depot) => (
                <div key={depot.id} className="address-item" style={{ marginBottom: 'var(--spacing-md)' }}>
                  <strong>{depot.name}</strong>
                  <p>{depot.address}</p>
                  {depot.contact && <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>Kontakt: {depot.contact}</p>}
                  {depot.website && (
                    <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginTop: '4px' }}>
                      <a href={depot.website} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--bioco-green)', textDecoration: 'underline' }}>
                        {depot.website}
                      </a>
                    </p>
                  )}
                  {depot.notes && <p style={{ fontSize: '0.8rem', color: 'var(--text-tertiary)', fontStyle: 'italic', marginBottom: 'var(--spacing-sm)' }}>{depot.notes}</p>}
                  <a
                    href={`https://www.google.com/maps/dir/?api=1&destination=${depot.lat},${depot.lng}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-secondary"
                    style={{ marginTop: 'var(--spacing-sm)', display: 'inline-block' }}
                  >
                    Route planen →
                  </a>
                </div>
              ))}
            </div>
            <div>
              <h5 style={{ color: 'var(--bioco-orange)', marginBottom: 'var(--spacing-sm)' }}>Freitag</h5>
              {depotLocations.filter(d => d.day === 'Freitag').map((depot) => (
                <div key={depot.id} className="address-item" style={{ marginBottom: 'var(--spacing-md)' }}>
                  <strong>{depot.name}</strong>
                  <p>{depot.address}</p>
                  {depot.contact && <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>Kontakt: {depot.contact}</p>}
                  {depot.website && (
                    <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginTop: '4px' }}>
                      <a href={depot.website} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--bioco-green)', textDecoration: 'underline' }}>
                        {depot.website}
                      </a>
                    </p>
                  )}
                  {depot.notes && <p style={{ fontSize: '0.8rem', color: 'var(--text-tertiary)', fontStyle: 'italic', marginBottom: 'var(--spacing-sm)' }}>{depot.notes}</p>}
                  <a
                    href={`https://www.google.com/maps/dir/?api=1&destination=${depot.lat},${depot.lng}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-secondary"
                    style={{ marginTop: 'var(--spacing-sm)', display: 'inline-block' }}
                  >
                    Route planen →
                  </a>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
