# Form Email Setup with Resend

## Overview
All form submissions are forwarded via Resend to different email recipients based on form type.

## Email Recipients

- **EventSignupForm & VisitDayForm**: `medien@bioco.ch`
- **All other forms** (Contact, Subscribe, Waiting List, Membership): `info@bioco.ch`, `medien@bioco.ch`, and `intranet@bioco.ch` (all three)

## Environment Variables

Add to `.env.local`:

```env
# Resend Configuration
RESEND_API_KEY=re_xxxxxxxxxxxxx
RESEND_FROM_EMAIL=noreply@bioco.ch
RESEND_FROM_NAME=biocò
```

## Setup Steps

1. Install Resend: `npm install resend` in the `frontend` directory
2. Get Resend API key from https://resend.com
3. Add environment variables to `.env.local`
4. Verify domain in Resend dashboard (for `noreply@bioco.ch`)

## Form Types

- `contact` - Contact form → info@bioco.ch, medien@bioco.ch, intranet@bioco.ch
- `subscribe` - Newsletter subscription → info@bioco.ch, medien@bioco.ch, intranet@bioco.ch
- `visit` - Visit day / Schnuppertag registration → medien@bioco.ch
- `event-signup` - Event signup → medien@bioco.ch (same as visit)
- `waiting-list` - Waiting list registration → info@bioco.ch, medien@bioco.ch, intranet@bioco.ch
- `membership` - Membership form → info@bioco.ch, medien@bioco.ch, intranet@bioco.ch

## Double Opt-In (Future)

Currently forms send emails directly. Double opt-in can be added later by:
1. Storing submissions temporarily
2. Sending confirmation email to user
3. Only forwarding to recipients after user confirms
