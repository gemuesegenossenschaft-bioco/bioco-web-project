/**
 * SEO Utilities
 * 
 * Helper functions for generating Next.js Metadata from CMS SEO data.
 */

import type { Metadata } from 'next'
import type { SeoData } from './processwire-types'

// Default SEO values for the site
const DEFAULT_SEO = {
  siteName: 'biocò',
  locale: 'de_CH',
  siteUrl: 'https://bioco.ch',
  defaultTitle: 'biocò | Bio-Gemüse aus der Region Baden-Brugg',
  defaultDescription: 'Gemüsegenossenschaft biocò: Frisches Demeter-Gemüse aus solidarischer Landwirtschaft. Wöchentliche Gemüsekörbe vom Geisshof in Gebenstorf für die Region Baden-Brugg.',
}

/**
 * Generate Next.js Metadata object from CMS SEO data
 * 
 * @param seo - SEO data from CMS API
 * @param fallback - Optional fallback values if CMS data is incomplete
 * @returns Metadata object for Next.js
 */
export function generateMetadata(
  seo?: SeoData | null,
  fallback?: {
    title?: string
    description?: string
    path?: string
  }
): Metadata {
  const title = seo?.title || fallback?.title || DEFAULT_SEO.defaultTitle
  const description = seo?.description || fallback?.description || DEFAULT_SEO.defaultDescription
  const canonical = seo?.canonical || (fallback?.path ? `${DEFAULT_SEO.siteUrl}${fallback.path}` : DEFAULT_SEO.siteUrl)
  
  const metadata: Metadata = {
    title,
    description,
    alternates: {
      canonical,
    },
    openGraph: {
      title,
      description,
      url: canonical,
      type: 'website',
      locale: DEFAULT_SEO.locale,
      siteName: DEFAULT_SEO.siteName,
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
    },
  }
  
  // Add OG image if available
  if (seo?.ogImage?.url) {
    const ogImage = {
      url: seo.ogImage.url,
      width: seo.ogImage.width,
      height: seo.ogImage.height,
    }
    metadata.openGraph = {
      ...metadata.openGraph,
      images: [ogImage],
    }
    metadata.twitter = {
      ...metadata.twitter,
      images: [seo.ogImage.url],
    }
  }
  
  // Handle robots directives
  if (seo?.robots && (!seo.robots.index || !seo.robots.follow)) {
    metadata.robots = {
      index: seo.robots.index,
      follow: seo.robots.follow,
    }
  }
  
  return metadata
}

/**
 * Merge CMS SEO data with static fallback metadata
 * Use this when you have both CMS data and static defaults
 */
export function mergeSeoMetadata(
  cmsSeo: SeoData | undefined | null,
  staticMetadata: Metadata
): Metadata {
  if (!cmsSeo) {
    return staticMetadata
  }
  
  const staticOg = staticMetadata.openGraph && typeof staticMetadata.openGraph === 'object' 
    ? staticMetadata.openGraph 
    : {}
  const staticTwitter = staticMetadata.twitter && typeof staticMetadata.twitter === 'object'
    ? staticMetadata.twitter
    : {}
  
  return {
    ...staticMetadata,
    title: cmsSeo.title || staticMetadata.title,
    description: cmsSeo.description || staticMetadata.description,
    alternates: {
      ...staticMetadata.alternates,
      canonical: cmsSeo.canonical || staticMetadata.alternates?.canonical,
    },
    openGraph: {
      ...staticOg,
      title: cmsSeo.title || ('title' in staticOg ? staticOg.title : undefined),
      description: cmsSeo.description || ('description' in staticOg ? staticOg.description : undefined),
      ...(cmsSeo.ogImage?.url ? { images: [{ url: cmsSeo.ogImage.url, width: cmsSeo.ogImage.width, height: cmsSeo.ogImage.height }] } : {}),
    },
    twitter: {
      ...staticTwitter,
      title: cmsSeo.title || ('title' in staticTwitter ? staticTwitter.title : undefined),
      description: cmsSeo.description || ('description' in staticTwitter ? staticTwitter.description : undefined),
      ...(cmsSeo.ogImage?.url ? { images: [cmsSeo.ogImage.url] } : {}),
    },
    robots: cmsSeo.robots && (!cmsSeo.robots.index || !cmsSeo.robots.follow)
      ? { index: cmsSeo.robots.index, follow: cmsSeo.robots.follow }
      : staticMetadata.robots,
  }
}

// Re-export types for convenience
export type { SeoData } from './processwire-types'
