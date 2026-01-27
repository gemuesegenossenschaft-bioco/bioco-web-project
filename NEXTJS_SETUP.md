# Next.js Setup Guide for Bioco.ch

This guide explains how to set up the Next.js frontend with ProcessWire as a headless CMS.

## Architecture

- **Frontend**: Next.js 14 (React) with App Router
- **Backend**: ProcessWire CMS (headless, API mode)
- **Database**: MariaDB (via ProcessWire)
- **Analytics**: Matomo cookieless

## Prerequisites

- Node.js 18+ and npm
- ProcessWire installed and configured
- ProcessWire modules installed (FormProcessor, DOIManager, MatomoTracker)

## Frontend Setup

1. Navigate to frontend directory:
```bash
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Create `.env.local` file:
```env
# ProcessWire API URL
NEXT_PUBLIC_PROCESSWIRE_API_URL=http://localhost/api
PROCESSWIRE_API_URL=http://localhost/api

# Matomo Analytics (Cookieless)
NEXT_PUBLIC_MATOMO_URL=https://your-matomo-instance.com/
NEXT_PUBLIC_MATOMO_SITE_ID=1

# Site URL
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```

4. Run development server:
```bash
npm run dev
```

The frontend will be available at `http://localhost:3000`

## ProcessWire API Setup

The ProcessWire API endpoints are located in `site/api/`:

- `pages.php` - Returns page data as JSON
- `navigation.php` - Returns navigation items
- `forms.php` - Handles form submissions
- `doi.php` - Handles DOI confirmation

### URL Routing

Configure ProcessWire URL segments to route API requests:

1. In ProcessWire Admin, go to **Setup** → **Templates**
2. Create or edit template settings for API routes
3. Enable URL segments: `urlSegments = 1` or `urlSegments = 2`

Or use `.htaccess` routing (see `site/api/.htaccess`).

### API Endpoints

- `GET /api/pages?path=/` - Get page data
- `GET /api/navigation` - Get navigation
- `POST /api/forms/contact` - Contact form
- `POST /api/forms/subscribe` - Newsletter subscription
- `POST /api/forms/visit` - Visit day registration
- `POST /api/forms/waiting-list` - Waiting list
- `GET /api/doi/confirm?token=...` - Confirm DOI

## Production Deployment

### Build Next.js

```bash
cd frontend
npm run build
```

### Deployment Options

1. **Vercel** (Recommended for Next.js):
   - Connect GitHub repository
   - Configure environment variables
   - Deploy automatically on push

2. **Self-hosted**:
   - Build: `npm run build`
   - Start: `npm start`
   - Use PM2 or similar for process management

3. **Docker**:
   - Create Dockerfile for Next.js
   - Use reverse proxy (nginx) to route:
     - `/api/*` → ProcessWire backend
     - `/*` → Next.js frontend

### Reverse Proxy Configuration (nginx)

```nginx
server {
    listen 80;
    server_name bioco.ch;

    # Next.js frontend
    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    # ProcessWire API
    location /api/ {
        proxy_pass http://localhost/api/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## Environment Variables

### Frontend (.env.local)
- `NEXT_PUBLIC_PROCESSWIRE_API_URL` - Public API URL (accessible in browser)
- `PROCESSWIRE_API_URL` - Server-side API URL
- `NEXT_PUBLIC_MATOMO_URL` - Matomo instance URL
- `NEXT_PUBLIC_MATOMO_SITE_ID` - Matomo site ID
- `NEXT_PUBLIC_SITE_URL` - Site URL for email links
- `SMTP_HOST` - SMTP server host (default: `mail.bioco.ch`)
- `SMTP_PORT` - SMTP port (default: `465` for SSL)
- `SMTP_SECURE` - Use SSL (default: `true` for port 465)
- `SMTP_USER` - SMTP username (e.g., `noreply@bioco.ch`)
- `SMTP_PASS` - SMTP password
- `SMTP_FROM_EMAIL` - From email address (default: `noreply@bioco.ch`)
- `SMTP_FROM_NAME` - From name (default: `biocò`)

### ProcessWire (site/config.php)
- `$config->email_from = 'hallo@bioco.ch'`
- `$config->email_from_name = 'bioco.ch'`
- `$config->admin_email = 'admin@bioco.ch'`

## Testing

1. Test page rendering:
   - Visit `http://localhost:3000`
   - Check console for API errors

2. Test form submissions:
   - Submit each form type
   - Check for DOI email
   - Confirm via email link

3. Test analytics:
   - Check browser console for Matomo script
   - Verify events are tracked

## Troubleshooting

### API Connection Issues
- Verify ProcessWire API endpoints are accessible
- Check CORS headers in ProcessWire API files
- Verify API URL in environment variables

### Form Submissions Not Working
- Check ProcessWire modules are installed
- Verify database table `doi_tokens` exists
- Check ProcessWire error logs

### Matomo Not Tracking
- Verify Matomo URL and site ID are correct
- Check browser console for errors
- Ensure Matomo instance is accessible

## Development Workflow

1. Make changes to Next.js components
2. Hot reload in development (`npm run dev`)
3. Test locally
4. Build and deploy to production

## Notes

- Next.js uses Server-Side Rendering (SSR) for better SEO
- ProcessWire remains as CMS backend
- Forms still use ProcessWire modules for processing
- Email sending handled by ProcessWire
- Analytics tracking is cookieless (Swiss DSG compliant)
