# Recover Emails from Last 14 Days - Resend & AWS SES

## Overview
This guide helps you recover emails sent in the last 14 days that went through Resend API (outgoing) and AWS SES (incoming).

---

## Part 1: Recover Outgoing Emails from Resend Dashboard

**These are emails YOU sent via your forms** (contact, membership, etc.)

### Step 1: Access Resend Dashboard

1. **Go to:** https://resend.com/
2. **Log in** with your Resend account credentials
3. If you don't have credentials:
   - Check your email for Resend account confirmation
   - Use password reset: https://resend.com/forgot-password
   - Check if credentials are saved in your password manager

### Step 2: View Email Logs

1. **Navigate to:** Dashboard → **Emails** (or **Logs**)
2. **Filter by date:**
   - Use date filter to show last 14 days
   - Or manually set: Today - 14 days

### Step 3: View Email Details

For each email, you can see:
- ✅ **Recipients** (who received it)
- ✅ **Subject** line
- ✅ **Timestamp** (when sent)
- ✅ **Status** (delivered, bounced, etc.)
- ✅ **Content** (HTML and plain text versions)
- ✅ **Email ID** (for reference)

### Step 4: Export Email Data

**Export all emails from last 14 days:**

1. **Apply filters:**
   - Date range: Last 14 days
   - Status: All (or specific status)

2. **Click "Export" button**
   - Confirm your filters
   - For ≤1,000 emails: Download starts immediately
   - For >1,000 emails: Download link sent to your email

3. **Access exports:**
   - Go to: **Settings** → **Team** → **Exports**
   - All team members can see exports
   - Only admins can download

**Export format:** CSV file with:
- Email ID
- Recipient
- Subject
- Status
- Timestamp
- Content preview

### Step 5: Forward/Download Important Emails

**For each important email:**
1. Click on the email to view full details
2. **Copy the content** (HTML or plain text)
3. **Forward to yourself** or save locally
4. **Note the recipient** to verify delivery

---

## Part 2: Recover Incoming Emails from AWS SES

**These are emails sent TO @bioco.ch addresses** (info@, intranet@, etc.)

### Step 1: Access AWS Console

1. **Go to:** https://console.aws.amazon.com/
2. **Log in** with AWS account credentials
3. **Select region:** **EU (Ireland) eu-west-1** (based on your MX record)

### Step 2: Check AWS SES Email Receiving

1. **Navigate to:** Services → **Simple Email Service (SES)**
2. **Go to:** Left menu → **Email Receiving** → **Rule Sets**
3. **Check active rule sets:**
   - Look for rules configured for `bioco.ch`
   - Check what actions were configured

### Step 3: Check S3 Bucket (Most Common Storage)

**If emails were stored in S3:**

1. **Go to:** Services → **S3**
2. **Look for buckets** with names like:
   - `bioco-emails`
   - `ses-emails`
   - `bioco-ch-emails`
   - `inbound-emails`
   - Or any bucket created around the time MX was configured

3. **Browse bucket contents:**
   - Emails are usually stored as raw `.eml` files
   - Organized by date folders (YYYY/MM/DD)
   - Or by recipient folders

4. **Download emails:**
   - Select emails from last 14 days
   - Click "Download" or "Download as ZIP"
   - Emails are in raw format - open with email client or text editor

### Step 4: Check AWS WorkMail

**If WorkMail was configured:**

1. **Go to:** https://console.aws.amazon.com/workmail/
2. **Region:** EU (Ireland) eu-west-1
3. **Check organizations:**
   - Look for `bioco.ch` organization
   - Access mailboxes for:
     - info@bioco.ch
     - intranet@bioco.ch
     - medien@bioco.ch
     - Any other @bioco.ch addresses

4. **Access webmail:**
   - URL format: `https://[organization-alias].awsapps.com/mail`
   - Log in with mailbox credentials
   - Check inbox for last 14 days

### Step 5: Check SNS Topics (If Configured)

**If emails were sent to SNS:**

1. **Go to:** Services → **Simple Notification Service (SNS)**
2. **Check topics:**
   - Look for topics related to email
   - Check subscriptions (email endpoints)
   - Review message history

### Step 6: Check Lambda Functions (If Configured)

**If emails were processed by Lambda:**

1. **Go to:** Services → **Lambda**
2. **Check functions:**
   - Look for functions with "email" or "ses" in name
   - Check CloudWatch Logs for email processing
   - Review function code to see where emails were stored

---

## Part 3: Check Novatrend Backup MX

**Some emails might have gone to backup MX servers:**

1. **Log into Novatrend webmail:**
   - https://webmail.bioco.ch
   - Or via cPanel → Email → Webmail

2. **Check mailboxes:**
   - info@bioco.ch
   - intranet@bioco.ch
   - medien@bioco.ch

3. **Look for emails from last 14 days:**
   - Some might have been delivered to backup MX (Priority 10)
   - While AWS MX (Priority 9) was primary

---

## Part 4: Automated Recovery Script

**If you have AWS CLI access, use this script:**

```bash
#!/bin/bash
# Recover emails from AWS SES S3 bucket (last 14 days)

BUCKET_NAME="your-ses-bucket-name"
START_DATE=$(date -u -d '14 days ago' +%Y-%m-%d)
END_DATE=$(date -u +%Y-%m-%d)

# List emails from last 14 days
aws s3 ls s3://$BUCKET_NAME --recursive --region eu-west-1 | \
  awk -v start="$START_DATE" -v end="$END_DATE" '$1 >= start && $1 <= end {print $4}' | \
  while read file; do
    echo "Downloading: $file"
    aws s3 cp "s3://$BUCKET_NAME/$file" "./recovered-emails/$file" --region eu-west-1
  done
```

**Requirements:**
- AWS CLI installed
- AWS credentials configured
- S3 bucket name known

---

## Part 5: Contact Resend Support

**If you can't access Resend dashboard:**

1. **Email Resend support:**
   - support@resend.com
   - Subject: "Request email logs export for last 14 days"

2. **Provide:**
   - Your Resend account email
   - Domain: bioco.ch
   - Date range: Last 14 days
   - Reason: Email recovery after migration

3. **They can provide:**
   - CSV export of all sent emails
   - Email content and metadata
   - Delivery status information

---

## Part 6: Contact AWS Support

**If you can't access AWS console:**

1. **Contact AWS Support:**
   - https://console.aws.amazon.com/support/
   - Create a support case

2. **Request:**
   - Email logs for domain: bioco.ch
   - S3 bucket contents (if emails stored)
   - SES receiving rule configuration
   - Date range: Last 14 days

3. **Provide:**
   - AWS account ID
   - Domain ownership proof
   - Reason for recovery

---

## Quick Checklist

- [ ] Log into Resend dashboard
- [ ] Export emails from last 14 days (CSV)
- [ ] Download individual email content
- [ ] Log into AWS Console
- [ ] Check SES receiving rules
- [ ] Check S3 buckets for stored emails
- [ ] Check AWS WorkMail (if configured)
- [ ] Check Novatrend webmail (backup MX)
- [ ] Contact Resend support (if needed)
- [ ] Contact AWS support (if needed)

---

## Important Notes

**Resend (Outgoing Emails):**
- ✅ **Can recover:** All emails YOU sent via Resend API
- ✅ **Content available:** Full HTML and plain text
- ✅ **Recipients visible:** Who received each email
- ✅ **Status visible:** Delivery, bounce, etc.

**AWS SES (Incoming Emails):**
- ⚠️ **May not exist:** If SES wasn't configured for receiving
- ✅ **If configured:** Check S3, WorkMail, or forwarding rules
- ❌ **If not configured:** Emails were likely rejected/bounced

**Timeline:**
- **Before AWS MX:** Emails in Novatrend ✅
- **During AWS MX (Priority 9):** Emails went to AWS (if configured) ⚠️
- **After AWS MX removed:** Emails in Novatrend ✅

---

## Next Steps

1. **Start with Resend dashboard** - easiest to access
2. **Export all outgoing emails** from last 14 days
3. **Check AWS S3** if you have access
4. **Contact support** if you need help accessing accounts
5. **Forward important emails** to your current mailboxes

**Priority:** Focus on Resend dashboard first - those are the form submissions you sent, which are most important to recover.

