# Amazon SES Quick Start Guide

## 🚀 Quick Setup (5 minutes)

### 1. Get AWS Credentials
1. **Sign up for AWS**: https://aws.amazon.com/ (if you don't have an account)
2. **Go to IAM Console**: https://console.aws.amazon.com/iam/
3. **Create User**:
   - Username: `low-stock-pulse-ses`
   - Access type: ✅ Programmatic access
   - Permissions: Attach `AmazonSESFullAccess` policy
4. **Save credentials**: Copy Access Key ID and Secret Access Key

### 2. Verify Email Address
1. **Go to SES Console**: https://console.aws.amazon.com/ses/
2. **Click "Verified identities"** → **"Create identity"**
3. **Select "Email address"**
4. **Enter your email** (e.g., `alerts@yourdomain.com`)
5. **Check your email** and click verification link

### 3. Configure Your App
Update your `.env` file:

```env
# Change from log to ses
MAIL_MAILER=ses

# Add your AWS credentials
AWS_ACCESS_KEY_ID=AKIA...your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-access-key
AWS_DEFAULT_REGION=us-east-1

# Set your verified email
MAIL_FROM_ADDRESS="alerts@yourdomain.com"
```

### 4. Test Setup
Run the verification script:
```bash
cd web
php verify-ses-setup.php
```

### 5. Test in App
1. Go to **Settings** in your Low Stock Pulse app
2. Enter your **verified email** in "Test Email Address"
3. Click **"Send Test Email"**
4. Check your email! 📧

## 🔧 Troubleshooting

### "Email address not verified"
- ✅ Verify your sender email in SES Console
- ✅ Use the exact verified email in MAIL_FROM_ADDRESS

### "Access denied" / "InvalidAccessKeyId"
- ✅ Check AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY
- ✅ Ensure IAM user has AmazonSESFullAccess policy

### "Sending quota exceeded"
- ✅ New accounts start with 200 emails/day limit
- ✅ Request production access in SES Console

### Emails not delivered
- ✅ Check spam folder
- ✅ Verify recipient email (if in sandbox mode)
- ✅ Check SES sending statistics in AWS Console

## 📊 SES Limits

### Sandbox Mode (New Accounts)
- ✅ 200 emails per 24 hours
- ✅ 1 email per second
- ✅ Can only send to verified addresses

### Production Mode (After Request)
- ✅ Higher sending limits (varies by account)
- ✅ Can send to any email address
- ✅ Better deliverability features

## 💰 Pricing

Amazon SES is very affordable:
- **$0.10 per 1,000 emails** sent
- **$0.12 per GB** of attachments
- **Free tier**: 62,000 emails/month (if sent from EC2)

## 🔗 Useful Links

- **SES Console**: https://console.aws.amazon.com/ses/
- **IAM Console**: https://console.aws.amazon.com/iam/
- **SES Documentation**: https://docs.aws.amazon.com/ses/
- **Request Production Access**: SES Console → Account dashboard → Request production access

## 🆘 Need Help?

1. **Run verification script**: `php verify-ses-setup.php`
2. **Check EMAIL_SETUP.md** for detailed instructions
3. **AWS Support**: https://aws.amazon.com/support/
4. **SES Forums**: https://forums.aws.amazon.com/forum.jspa?forumID=90

---

**🎉 That's it! Your Low Stock Pulse app is now powered by Amazon SES for reliable email delivery.**
