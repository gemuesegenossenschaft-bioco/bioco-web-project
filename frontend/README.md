# Bioco.ch Frontend

Next.js 14 app-router frontend for `bioco.ch`. Content comes from ProcessWire via `/cms/api/*`.

## Local setup

```bash
npm install
npm run dev
```

Create `frontend/.env.local`:

```env
PROCESSWIRE_BASE_URL=https://cms.bioco.ch
PROCESSWIRE_API_KEY=bioco2026ready
NEXT_PUBLIC_SITE_URL=http://localhost:3000
NEXT_PUBLIC_PROCESSWIRE_BASE_URL=https://cms.bioco.ch
NEXT_PUBLIC_TURNSTILE_SITE_KEY=your-public-site-key
TURNSTILE_SECRET_KEY=your-secret-key
```

`TURNSTILE_SECRET_KEY` is required for both Next.js form routes (`/api/forms/*`) and legacy CMS form routes (`/cms/api/forms/*`).

## Important routes

- `app/page.tsx`: homepage
- `app/(cms)/[...slug]/page.tsx`: CMS-driven pages
- `app/api/revalidate/route.ts`: ISR invalidation endpoint
- `components/HomeClient.tsx`: homepage section rendering + visual editor markers
- `components/sections/VisualEditorWrapper.tsx`: `?_visual=1` wrapper
- `components/visual-editor/InlineVisualEditorRuntime.tsx`: inline edit runtime inside iframe

## CMS API contract

Frontend reads from:

- `GET /api/content/homepage`
- `GET /api/content/sections/{slug}`
- `GET /api/content/page?path=/foo`
- `GET /api/content/pages`
- `GET /api/content/navigation`
- `GET /api/content/events`
- `GET /api/content/aktuelles`
- `GET /api/content/instagram`
- `GET /api/content/settings`

Frontend posts to:

- `POST /api/forms/contact`
- `POST /api/forms/subscribe`
- `POST /api/forms/visit`
- `POST /api/forms/waiting-list`
- `POST /api/forms/event-signup`
- `POST /api/revalidate`

## Visual editor

- Visual editor runs only on the dedicated ProcessWire screen `/visual-editor/`
- Frontend enters editor mode with `?_visual=1`
- Homepage and CMS repeater pages emit stable `data-ve-section-id` / `data-ve-field` markers
- Inline text editing happens inside the iframe
- Parent shell stays source of truth for save/discard, section CRUD, media actions, and busy blocking

## Validation

```bash
npm run build
npm test -- --run tests/visual-editor.test.tsx
```
