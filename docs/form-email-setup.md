# Form Email Setup with Novatrend SMTP

## Overview
All form submissions are forwarded via Novatrend SMTP server (`mail.bioco.ch`) to different email recipients based on form type. All emails are automatically BCC'd to `info@bioco.ch` for safety and backup.

## Email Recipients

- **EventSignupForm & VisitDayForm**: `medien@bioco.ch`
- **All other forms** (Contact, Subscribe, Waiting List, Membership): `info@bioco.ch`, `medien@bioco.ch`, and `intranet@bioco.ch` (all three)

**Safety BCC:** All emails are automatically BCC'd to `info@bioco.ch` to ensure no emails are lost.

## Environment Variables

Add to `.env.local`:

```env
# Novatrend SMTP Configuration
SMTP_HOST=mail.bioco.ch
SMTP_PORT=465
SMTP_SECURE=true
SMTP_USER=noreply@bioco.ch
SMTP_PASS=your_smtp_password_here
SMTP_FROM_EMAIL=noreply@bioco.ch
SMTP_FROM_NAME=biocò
```

### Configuration Details

- **SMTP_HOST**: Novatrend mail server (`mail.bioco.ch`)
- **SMTP_PORT**: `465` for SSL (recommended) or `587` for STARTTLS
- **SMTP_SECURE**: `true` for port 465 (SSL), `false` for port 587 (STARTTLS)
- **SMTP_USER**: SMTP username (typically the full email address, e.g., `noreply@bioco.ch`)
- **SMTP_PASS**: SMTP password for the user account
- **SMTP_FROM_EMAIL**: From email address (default: `noreply@bioco.ch`)
- **SMTP_FROM_NAME**: From name (default: `biocò`)

**Note:** For backward compatibility, the system will also check `RESEND_FROM_EMAIL` and `RESEND_FROM_NAME` if SMTP variables are not set, but SMTP variables take precedence.

## Setup Steps

1. Install nodemailer: `npm install nodemailer` in the `frontend` directory
2. Get SMTP password for `noreply@bioco.ch` from Novatrend cPanel → Email Accounts
3. Add environment variables to `.env.local`
4. Test SMTP connection with test email

## Form Types

- `contact` - Contact form → info@bioco.ch, intranet@bioco.ch, gueneyextern@gmail.com
- `subscribe` - Newsletter subscription → info@bioco.ch, intranet@bioco.ch
- `visit` - Visit day / Schnuppertag registration → medien@bioco.ch
- `event-signup` - Event signup → medien@bioco.ch (same as visit)
- `waiting-list` - Waiting list registration → info@bioco.ch, intranet@bioco.ch
- `membership` - Membership form → info@bioco.ch, medien@bioco.ch, intranet@bioco.ch, gueneyextern@gmail.com

## Safety Features

- **Automatic BCC**: All emails are automatically BCC'd to `info@bioco.ch`
- **Logging**: All email send attempts are logged (success/failure) for monitoring
- **No Email Deletion**: The system only logs email attempts - no emails are ever deleted

## Testing

1. Test SMTP connection:
   ```bash
   # In development, check console logs for email send attempts
   ```

2. Send test email:
   - Submit a test form
   - Check that email arrives at primary recipients
   - Verify BCC recipients also receive the email
   - Check server logs for email send confirmation

## Troubleshooting

### SMTP Connection Issues

- Verify SMTP credentials are correct
- Check that port 465 (SSL) or 587 (STARTTLS) is not blocked by firewall
- Ensure SMTP_USER is the full email address
- Verify SMTP_PASS is correct

### Email Not Received

- Check BCC recipient (`info@bioco.ch`) - should always receive a copy
- Check server logs for email send errors
- Verify MX records are correctly configured
- Check spam folders

## Migration from Resend

This system replaced Resend due to blocking of @bioco.ch emails. The previous Resend configuration has been removed, but the email formatting and recipient logic remain the same.

## Double Opt-In (Future)

Currently forms send emails directly. Double opt-in can be added later by:
1. Storing submissions temporarily
2. Sending confirmation email to user
3. Only forwarding to recipients after user confirms
