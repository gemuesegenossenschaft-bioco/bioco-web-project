# Bioco.ch Frontend - Next.js

This is the Next.js frontend for bioco.ch, using ProcessWire as a headless CMS.

**Current Deployment**: staging.bioco.ch

## Setup

1. Install dependencies:
```bash
npm install
```

2. Configure environment variables:
Create a `.env.local` file:
```env
# ProcessWire base URL (headless CMS)
NEXT_PUBLIC_PROCESSWIRE_BASE_URL=https://bioco.ch
PROCESSWIRE_BASE_URL=https://bioco.ch
# Optional bearer token if your ProcessWire API is protected
# PROCESSWIRE_API_TOKEN=your-token

# Matomo Analytics (Cookieless - Swiss DSG compliant)
NEXT_PUBLIC_MATOMO_URL=https://your-matomo-instance.com/
NEXT_PUBLIC_MATOMO_SITE_ID=1

# Site URL
NEXT_PUBLIC_SITE_URL=https://bioco.ch
```

3. Run development server:
```bash
npm run dev
```

4. Build for production:
```bash
npm run build
npm start
```

## Project Structure

- `app/` - Next.js App Router pages
  - `page.tsx` - Homepage (A: Homepage)
  - `ernte/page.tsx` - Ernte section (B)
  - `abos/page.tsx` - Abos section (C)
  - `mitmachen/page.tsx` - Mitmachen section (D)
  - `depots/page.tsx` - Depots section (E)
  - `wir/page.tsx` - Wir section (F)
  - `hofpost/page.tsx` - Hofpost section (G)
  - `mitmachen/page.tsx` - Mitmachen! section (H)
  - `kundenportal/page.tsx` - Kundenportal section (I)
  - `kontakt/page.tsx` - Contact form
  - `newsletter/page.tsx` - Newsletter subscription
  - `tag-der-offenen-tuer/page.tsx` - Visit day registration
  - `warteliste/page.tsx` - Waiting list
  - `doi-confirm/page.tsx` - DOI confirmation
- `components/` - React components
  - `Header.tsx` - Site header with navigation
  - `Navigation.tsx` - Main navigation menu
  - `Hero.tsx` - Hero section component
  - `Footer.tsx` - Site footer
  - `EventsBanner.tsx` - Events banner component
  - `CTA.tsx` - Call-to-action button component
  - `forms/` - Form components
- `lib/` - Utility functions and API clients
- `app/api/` - API routes (proxy to ProcessWire backend)

## Site Structure (Based on Sitemap)

The site follows the sitemap structure with sections A-I:

- **A: Homepage** - Main landing page with hero, value props, CTAs
- **B: Ernte** - Product showcase, gallery, season calendar, Demeter quality
- **C: Abos** - Subscription options (Gemüse-Abos, Probe-Abo, Zusatz-Abos)
- **D: Anpacken** - Volunteer work information
- **E: Depots** - Depot locations map and list
- **F: Wir** - Team, mission, history
- **G: Hofpost** - Blog/news (Phase 2), Events list
- **H: Mitmachen!** - Membership signup, commitment check, waiting list
- **I: Kundenportal** - Gateway to member portal and planning tools

## ProcessWire API Endpoints

The frontend expects ProcessWire API endpoints at:
- `GET /site/api/pages.php?path=/` - Get page data
- `GET /site/api/navigation.php` - Get navigation items
- `GET /site/api/events.php` - Read event listings
- `GET /site/api/instagram.php` - Read Instagram feed
- `POST /api/forms/contact` - Submit contact form
- `POST /api/forms/subscribe` - Submit newsletter subscription
- `POST /api/forms/visit` - Submit visit day registration
- `POST /api/forms/waiting-list` - Submit waiting list form
- `POST /api/forms/membership` - Submit membership form
- `POST /api/forms/event-signup` - Submit event signup form
- `GET /site/api/doi.php/confirm?token=...` - Confirm DOI token

**Note**: For now, deeper database functionalities are not implemented. The ProcessWire API endpoints should be configured but may not have full database integration. This will be implemented in a later phase.

## Features

- Wireframe styling (Balsamiq-style)
- Template variations with CSS-only changes
- Form submissions with double opt-in (DOI)
- Matomo cookieless analytics (Swiss DSG compliant)
- Server-side rendering (SSR) with Next.js
- TypeScript for type safety
- Complete site structure based on sitemap (sections A-I)

## Development

The frontend runs on port 3000 by default. ProcessWire should run on staging.bioco.ch.

For production, configure your reverse proxy to:
- Serve Next.js frontend for the main site
- Proxy API requests to ProcessWire backend

## Deployment

**Current**: staging.bioco.ch

**Branch**: main

When deploying:
1. Ensure all environment variables are set
2. Build the Next.js application: `npm run build`
3. Deploy to your hosting environment
4. Configure reverse proxy for API routes
