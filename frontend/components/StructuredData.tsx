export function OrganizationSchema() {
  const schema = {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "biocò Gemüsegenossenschaft",
    "alternateName": "bioco",
    "url": "https://bioco.ch",
    "logo": "https://bioco.ch/images/bioco-logo.png",
    "description": "Gemüsegenossenschaft für solidarische Landwirtschaft in der Region Baden-Brugg. Frisches Demeter-Gemüse aus Gebenstorf.",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Geisshof",
      "addressLocality": "Gebenstorf",
      "postalCode": "5412",
      "addressCountry": "CH",
      "addressRegion": "Aargau"
    },
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": "47.4806",
      "longitude": "8.2683"
    },
    "contactPoint": {
      "@type": "ContactPoint",
      "email": "info@bioco.ch",
      "contactType": "customer service",
      "areaServed": "CH",
      "availableLanguage": "de"
    },
    "foundingDate": "2014"
  }

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
    />
  )
}

export function LocalBusinessSchema() {
  const schema = {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "biocò Gemüsegenossenschaft",
    "image": "https://bioco.ch/images/bioco-logo.png",
    "description": "Solidarische Landwirtschaft mit frischem Demeter-Gemüse aus der Region Baden-Brugg. Wöchentliche Gemüsekörbe vom Geisshof in Gebenstorf.",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Geisshof",
      "addressLocality": "Gebenstorf",
      "postalCode": "5412",
      "addressCountry": "CH",
      "addressRegion": "Aargau"
    },
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": "47.4806",
      "longitude": "8.2683"
    },
    "url": "https://bioco.ch",
    "telephone": "",
    "email": "info@bioco.ch",
    "priceRange": "CHF 1000-2000",
    "openingHours": "Tu,Fr 16:00-19:00",
    "servesCuisine": "Organic Vegetables",
    "areaServed": [
      {
        "@type": "City",
        "name": "Baden"
      },
      {
        "@type": "City",
        "name": "Brugg"
      },
      {
        "@type": "City",
        "name": "Gebenstorf"
      },
      {
        "@type": "City",
        "name": "Wettingen"
      }
    ]
  }

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
    />
  )
}

export function ProductSchema() {
  const schema = {
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "Gemüseabo Demeter-Qualität",
    "description": "Wöchentliches Gemüseabo mit frischem, saisonalem Bio-Gemüse in Demeter-Qualität aus solidarischer Landwirtschaft. Vom Geisshof in Gebenstorf für die Region Baden-Brugg.",
    "brand": {
      "@type": "Brand",
      "name": "biocò"
    },
    "category": "Organic Vegetable Subscription",
    "offers": [
      {
        "@type": "Offer",
        "name": "Halber Gemüsekorb",
        "price": "1000",
        "priceCurrency": "CHF",
        "availability": "https://schema.org/InStock",
        "validFrom": "2024-01-01",
        "priceValidUntil": "2025-12-31",
        "description": "Halber Gemüsekorb für 1-2 Personen, Jahresabo"
      },
      {
        "@type": "Offer",
        "name": "Ganzer Gemüsekorb",
        "price": "2000",
        "priceCurrency": "CHF",
        "availability": "https://schema.org/InStock",
        "validFrom": "2024-01-01",
        "priceValidUntil": "2025-12-31",
        "description": "Ganzer Gemüsekorb für 3-4 Personen, Jahresabo"
      }
    ],
    "aggregateRating": {
      "@type": "AggregateRating",
      "ratingValue": "5",
      "bestRating": "5",
      "ratingCount": "50"
    }
  }

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
    />
  )
}

export function EventSchema({ event }: { event: { name: string; date: string; description: string } }) {
  const schema = {
    "@context": "https://schema.org",
    "@type": "Event",
    "name": event.name,
    "description": event.description,
    "startDate": event.date,
    "location": {
      "@type": "Place",
      "name": "Geisshof",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Geisshof",
        "addressLocality": "Gebenstorf",
        "postalCode": "5412",
        "addressCountry": "CH"
      }
    },
    "organizer": {
      "@type": "Organization",
      "name": "biocò Gemüsegenossenschaft",
      "url": "https://bioco.ch"
    },
    "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
    "eventStatus": "https://schema.org/EventScheduled"
  }

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
    />
  )
}

export function BreadcrumbSchema({ items }: { items: Array<{ name: string; url: string }> }) {
  const schema = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": items.map((item, index) => ({
      "@type": "ListItem",
      "position": index + 1,
      "name": item.name,
      "item": item.url
    }))
  }

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
    />
  )
}

