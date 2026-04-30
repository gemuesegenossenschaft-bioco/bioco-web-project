'use client'

import { useEffect } from 'react'

declare global {
  interface Window {
    _paq?: any[]
    __matomoInitialized?: boolean
  }
}

export function MatomoScript() {
  useEffect(() => {
    const matomoUrl = process.env.NEXT_PUBLIC_MATOMO_URL
    const siteId = process.env.NEXT_PUBLIC_MATOMO_SITE_ID

    if (!matomoUrl || !siteId) {
      return
    }

    window._paq = window._paq || []

    if (window.__matomoInitialized) {
      return
    }

    window.__matomoInitialized = true
    window._paq.push(['trackPageView'])
    window._paq.push(['enableLinkTracking'])
    window._paq.push(['setTrackerUrl', `${matomoUrl}matomo.php`])
    window._paq.push(['setSiteId', parseInt(siteId)])
    window._paq.push(['disableCookies']) // Cookieless mode for DSG compliance

    if (document.getElementById('matomo-js')) {
      return
    }

    const script = document.createElement('script')
    script.id = 'matomo-js'
    script.async = true
    script.src = `${matomoUrl}matomo.js`
    document.head.appendChild(script)
  }, [])

  return null
}

// Helper function to track events
export function trackEvent(category: string, action: string, name?: string) {
  if (typeof window !== 'undefined' && window._paq) {
    window._paq.push(['trackEvent', category, action, name || ''])
  }
}

// Helper function to track CTA clicks
export function trackCTA(ctaName: string) {
  trackEvent('CTA', 'Click', ctaName)
}
