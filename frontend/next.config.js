/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  env: {
    PROCESSWIRE_BASE_URL:
      process.env.PROCESSWIRE_BASE_URL ||
      process.env.PROCESSWIRE_API_URL ||
      process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL ||
      'https://bioco.ch',
    NEXT_PUBLIC_PROCESSWIRE_BASE_URL:
      process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL ||
      process.env.PROCESSWIRE_BASE_URL ||
      process.env.PROCESSWIRE_API_URL ||
      'https://bioco.ch',
    NEXT_PUBLIC_SITE_URL:
      process.env.NEXT_PUBLIC_SITE_URL ||
      (process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : 'https://bioco.ch'),
    MATOMO_URL: process.env.MATOMO_URL || '',
    MATOMO_SITE_ID: process.env.MATOMO_SITE_ID || '',
  },
  images: {
    domains: ['localhost', 'staging.bioco.ch', 'bioco.ch', 'api.bioco.ch'],
    remotePatterns: [
      {
        protocol: 'http',
        hostname: 'localhost',
      },
      {
        protocol: 'https',
        hostname: 'staging.bioco.ch',
      },
      {
        protocol: 'https',
        hostname: 'bioco.ch',
      },
      {
        protocol: 'https',
        hostname: 'api.bioco.ch',
      },
      {
        protocol: 'https',
        hostname: '**',
      },
    ],
  },
  async redirects() {
    return [
      {
        source: '/der-geisshof',
        destination: '/wir',
        permanent: true,
      },
      {
        source: '/depotstandorte',
        destination: '/standorte-depots',
        permanent: true,
      },
      {
        source: '/was-steckt-hinter-bioco',
        destination: '/mitmachen',
        permanent: true,
      },
      {
        source: '/bioco-die-genossenschaft',
        destination: '/mitmachen',
        permanent: true,
      },
      {
        source: '/intranet-dokumente',
        destination: '/intranet',
        permanent: true,
      },
      {
        source: '/solidarische-landwirtschaft',
        destination: '/solawi',
        permanent: true,
      },
      {
        source: '/das-leitbild-von-bioco',
        destination: '/wir',
        permanent: true,
      },
      {
        source: '/links',
        destination: '/wir',
        permanent: true,
      },
    ]
  },
}

module.exports = nextConfig
