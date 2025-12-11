# Vercel Deployment Guide

## Configuration

The Next.js application is located in the `frontend/` subdirectory.

### Option 1: Vercel Dashboard (Recommended)

1. Go to your Vercel project settings
2. Navigate to **Settings** → **General**
3. Set **Root Directory** to `frontend`
4. Vercel will automatically detect Next.js and use the correct build settings

### Option 2: Using vercel.json (Alternative)

If you prefer to configure via file, create a `vercel.json` in the project root:

```json
{
  "buildCommand": "cd frontend && npm run build",
  "outputDirectory": "frontend/.next",
  "installCommand": "cd frontend && npm install",
  "framework": "nextjs",
  "rootDirectory": "frontend"
}
```

## Environment Variables

Set these in Vercel Dashboard → Settings → Environment Variables:

- `PROCESSWIRE_BASE_URL` (and `NEXT_PUBLIC_PROCESSWIRE_BASE_URL` if needed) – ProcessWire origin, e.g. `https://bioco.ch`
- `PROCESSWIRE_API_TOKEN` – optional bearer token if API is protected
- `NEXT_PUBLIC_SITE_URL` – canonical site URL, e.g. `https://bioco.ch`
- `MATOMO_URL` / `MATOMO_SITE_ID` – optional Matomo analytics

## Build Verification

The project should build successfully with:
- Next.js 14.0.0
- React 18.2.0
- TypeScript 5.0.0

All dependencies are correctly listed in `frontend/package.json`.

## Domain setup

1. Add `bioco.ch` (and `www.bioco.ch` if you keep it) under Vercel → Domains for this project.
2. Set `bioco.ch` as the primary domain and configure a redirect either from `www` → root or vice versa.
3. DNS records:
   - Root: ALIAS/ANAME to `cname.vercel-dns.com` (or A record to Vercel IPv4/IPv6 targets if your DNS host lacks ALIAS support).
   - `www`: CNAME to `cname.vercel-dns.com`.
   - Add TXT for domain verification if prompted.
4. After propagation, verify HTTPS works on `https://bioco.ch` and check redirects.
