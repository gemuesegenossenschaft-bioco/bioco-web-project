/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  reactStrictMode: true,
  experimental: {
    externalDir: true,
  },
  env: {
    PROCESSWIRE_BASE_URL:
      process.env.PROCESSWIRE_BASE_URL ||
      process.env.PROCESSWIRE_API_URL ||
      'http://localhost/cms',
    NEXT_PUBLIC_PROCESSWIRE_BASE_URL:
      process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL ||
      'https://cms.bioco.ch',
    PROCESSWIRE_API_KEY: process.env.PROCESSWIRE_API_KEY || '',
    NEXT_PUBLIC_SITE_URL:
      process.env.NEXT_PUBLIC_SITE_URL || 'https://bioco.ch',
    NEXT_PUBLIC_MATOMO_URL: process.env.NEXT_PUBLIC_MATOMO_URL || '',
    NEXT_PUBLIC_MATOMO_SITE_ID: process.env.NEXT_PUBLIC_MATOMO_SITE_ID || '',
  },
  images: {
    domains: ['localhost', 'staging.bioco.ch', 'bioco.ch', 'api.bioco.ch', 'cms.bioco.ch'],
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
  async headers() {
    return [
      {
        // Framing protection: CSP frame-ancestors allows cms.bioco.ch (visual editor)
        // No X-Frame-Options (CSP frame-ancestors supersedes it in all modern browsers)
        source: '/:path*',
        headers: [
          {
            key: 'Content-Security-Policy',
            value: "frame-ancestors 'self' https://cms.bioco.ch",
          },
        ],
      },
    ]
  },
  async redirects() {
    return [
      {
        source: '/ernte',
        destination: '/gemuese',
        permanent: true,
      },
      {
        source: '/depots',
        destination: '/standorte-depots',
        permanent: true,
      },
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
      {
        source: '/impressionen',
        destination: '/mitmachen',
        permanent: true,
      },
      {
        source: '/wp-content/uploads/2017/07/1704_Gemüseabo.pdf',
        destination: '/abos',
        permanent: true,
      },
      {
        source: '/presse',
        destination: '/wir',
        permanent: true,
      },
      {
        source: '/home/home',
        destination: '/',
        permanent: true,
      },
    ]
  },
}

module.exports = nextConfig
