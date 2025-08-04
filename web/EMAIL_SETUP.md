# Email Configuration for Low Stock Pulse

## ✅ Amazon SES Integration Configured

The Low Stock Pulse app is now configured to use **Amazon Simple Email Service (SES)** for reliable, scalable email delivery.

## Current Setup

The app is configured to use **Amazon SES** for sending emails. You need to configure your AWS credentials to start sending real emails.

### How to View Test Emails

1. **Send a test email** from the Settings page
2. **Check the Laravel logs** to see the full email content:
   ```bash
   cd web && tail -50 storage/logs/laravel.log
   ```
3. **Look for email HTML** in the logs - you'll see the complete email template

## Amazon SES Setup Guide

### Step 1: AWS Account Setup
1. **Create AWS Account** (if you don't have one): https://aws.amazon.com/
2. **Sign in to AWS Console**: https://console.aws.amazon.com/
3. **Navigate to SES**: Search for "Simple Email Service" or go to https://console.aws.amazon.com/ses/

### Step 2: Verify Email Address/Domain
**Important**: SES requires email verification before sending emails.

#### Option A: Verify Single Email Address
1. Go to **SES Console > Verified identities**
2. Click **Create identity**
3. Select **Email address**
4. Enter your sender email (e.g., `noreply@yourdomain.com`)
5. Click **Create identity**
6. Check your email and click the verification link

#### Option B: Verify Entire Domain (Recommended)
1. Go to **SES Console > Verified identities**
2. Click **Create identity**
3. Select **Domain**
4. Enter your domain (e.g., `yourdomain.com`)
5. Follow DNS verification steps

### Step 3: Create IAM User for SES
1. Go to **IAM Console > Users**
2. Click **Create user**
3. Enter username (e.g., `low-stock-pulse-ses`)
4. Select **Programmatic access**
5. Attach policy: **AmazonSESFullAccess**
6. Save the **Access Key ID** and **Secret Access Key**

### Step 4: Configure Your App
Update your `.env` file with your AWS credentials:

```env
# Amazon SES Configuration
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key-id
AWS_SECRET_ACCESS_KEY=your-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_SES_REGION=us-east-1
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Low Stock Pulse"
```

### Step 5: Request Production Access (If Needed)
- **New SES accounts** start in "Sandbox mode"
- **Sandbox mode** can only send to verified email addresses
- **Production mode** can send to any email address
- **Request production access** in SES Console > Account dashboard

### Step 6: Test Email Sending
1. Go to your Low Stock Pulse app **Settings page**
2. Enter a verified email address in **"Test Email Address"**
3. Click **"Send Test Email"**
4. Check for successful delivery

## Email Features

### Test Email
- **Purpose**: Verify email configuration
- **Sent to**: Any email address you specify
- **Content**: Professional test email with shop branding

### Low Stock Alerts
- **Purpose**: Notify when products are below threshold
- **Sent to**: Alert email address from settings
- **Content**: Detailed product list with current stock levels
- **Frequency**: Based on your alert frequency setting

## Shopify Integration

The email system is integrated with Shopify to:
- ✅ **Use shop branding** (name, domain) in emails
- ✅ **Dynamic sender address** based on shop domain
- ✅ **Professional templates** with shop information
- ✅ **Reliable delivery** using proven email infrastructure

## Email Templates

### Test Email Template
- Professional design with Low Stock Pulse branding
- Shop information and configuration details
- Success confirmation message

### Low Stock Alert Template
- Urgent alert styling with red color scheme
- Product table with current stock vs thresholds
- Action buttons linking to Shopify admin
- Recommendations for next steps

## Troubleshooting

### Emails Not Sending
1. Check `.env` configuration
2. Verify email service credentials
3. Check Laravel logs for errors: `tail -f storage/logs/laravel.log`
4. Test with `log` driver first to verify templates

### Email Delivery Issues
1. Check spam folders
2. Verify sender domain reputation
3. Use authenticated email services (SendGrid, Mailgun)
4. Set up SPF/DKIM records for your domain

## Security Notes

- Never commit email credentials to version control
- Use environment variables for all sensitive data
- Consider using app-specific passwords
- Regularly rotate API keys and passwords

## Support

For email configuration help:
1. Check Laravel Mail documentation
2. Contact your email service provider
3. Test with simple SMTP first
4. Use email testing services like Mailtrap for development
