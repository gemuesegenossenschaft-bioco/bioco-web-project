# MX Records Migration Guide - Safe Procedure

## Current Situation

**Current MX Records:**
- Pointing to: `inbound.smtp-eu-west-1.amazonaws.com` (AWS SES)
- This was causing issues with Resend blocking @bioco.ch emails

**Target MX Records:**
- Should point to: Novatrend mail servers (likely `mail.bioco.ch` or Novatrend's mail servers)

## Important: Email Preservation

**CRITICAL: DO NOT DELETE ANY EMAILS**

This procedure is designed to ensure:
- All existing emails in mailboxes remain untouched
- Incoming emails during transition go to both old and new servers
- No emails are deleted during this process
- Quick rollback is possible if needed

## Safe MX Change Procedure

### Step 1: Document Current MX Records

Before making any changes, document the current MX records exactly:

1. Log into your domain registrar or DNS provider (Novatrend)
2. Navigate to DNS settings for `bioco.ch`
3. Find all MX records and document:
   - **Host/Name**: (usually `@` or `bioco.ch`)
   - **Priority/Preference**: (lower number = higher priority)
   - **Value/Target**: (the mail server hostname)
   - **TTL**: (Time To Live in seconds)

**Example current record:**
```
Type: MX
Host: @
Priority: 10
Value: inbound.smtp-eu-west-1.amazonaws.com
TTL: 3600
```

### Step 2: Lower TTL (Time To Live)

**Why:** Lower TTL allows quick rollback if issues occur.

1. Change TTL on existing MX records to **300 seconds** (5 minutes)
2. Wait at least 5 minutes for changes to propagate
3. This allows quick rollback if needed

### Step 3: Add New MX Records (Parallel)

**Important:** Do NOT delete old MX records yet!

1. Add new MX records pointing to Novatrend mail servers:
   - **Priority**: Use a higher number (lower priority) than existing records
   - **Value**: Novatrend mail server (check with Novatrend support for exact hostname)
   - **TTL**: 300 seconds (same as old records)

**Example new record:**
```
Type: MX
Host: @
Priority: 20
Value: mail.bioco.ch (or Novatrend's mail server)
TTL: 300
```

**Result:** Both old and new MX records are active, with old ones having higher priority initially.

### Step 4: Verify New MX Works

1. **Test SMTP sending:**
   - Send test emails from the application
   - Verify emails are delivered correctly
   - Check that BCC recipients receive emails

2. **Test email receiving:**
   - Send test emails TO @bioco.ch addresses from external email
   - Verify emails arrive in mailboxes
   - Check both old and new mail servers if possible

3. **Monitor for 24-48 hours:**
   - Check that all incoming emails are being received
   - Verify no emails are being lost
   - Monitor application logs for email send errors

### Step 5: Switch Priority (Gradual Migration)

Once new MX is proven to work:

1. **Change priorities:**
   - Set new MX records to priority 10 (higher priority)
   - Set old MX records to priority 20 (lower priority)
   - This makes new MX primary, but old MX still receives emails as backup

2. **Monitor for another 24-48 hours:**
   - Ensure all emails are still being received
   - Check that new MX is handling all traffic correctly

### Step 6: Remove Old MX Records

**Only after confirming new MX works perfectly for at least 48 hours:**

1. Remove old AWS SES MX records
2. Keep only Novatrend MX records active
3. Verify email delivery continues to work

### Step 7: Raise TTL Back to Normal

1. Change TTL on MX records back to **3600 seconds** (1 hour) or your preferred value
2. This reduces DNS query load once migration is complete

## Rollback Procedure

If issues occur during migration:

1. **Immediate rollback:**
   - Change priorities back (old MX = priority 10, new MX = priority 20)
   - Or remove new MX records entirely
   - With low TTL (300s), changes take effect within 5 minutes

2. **Check email delivery:**
   - Verify emails are being received again
   - Check application logs for errors

3. **Investigate issues:**
   - Check SMTP configuration
   - Verify Novatrend mail server settings
   - Contact Novatrend support if needed

## Verification Checklist

Before considering migration complete:

- [ ] All test emails sent successfully
- [ ] All test emails received successfully
- [ ] BCC recipients receive all emails
- [ ] Application logs show no email errors
- [ ] External emails to @bioco.ch arrive correctly
- [ ] No emails lost during transition
- [ ] Monitoring shows stable email delivery for 48+ hours

## Contact Information

- **Novatrend Support:** Check Novatrend panel for support contact
- **DNS Provider:** Novatrend (if managing DNS) or your domain registrar

## Notes

- This procedure ensures zero email loss
- All existing emails in mailboxes remain untouched
- The parallel MX approach provides redundancy during transition
- Low TTL allows quick rollback if needed
- Always test thoroughly before removing old MX records

