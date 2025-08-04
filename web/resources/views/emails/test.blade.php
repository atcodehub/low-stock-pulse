<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Low Stock Pulse - Test Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #00b894;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e9ecef;
        }
        .success-icon {
            font-size: 48px;
            color: #00b894;
            text-align: center;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: white;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #00b894;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔔 Low Stock Pulse</h1>
        <p>Test Email Configuration</p>
    </div>
    
    <div class="content">
        <div class="success-icon">✅</div>
        
        <h2 style="text-align: center; color: #00b894;">Email Configuration Test Successful!</h2>
        
        <p>Congratulations! Your Low Stock Pulse app email configuration is working correctly.</p>
        
        <div class="info-box">
            <h3>📧 Test Details:</h3>
            <ul>
                <li><strong>Shop Domain:</strong> {{ $shopDomain }}</li>
                <li><strong>Test Message:</strong> {{ $testMessage }}</li>
                <li><strong>Sent At:</strong> {{ $sentAt }}</li>
            </ul>
        </div>
        
        <p>This test email confirms that:</p>
        <ul>
            <li>✅ Your email configuration is properly set up</li>
            <li>✅ Low Stock Pulse can send notifications</li>
            <li>✅ Your alert emails will be delivered successfully</li>
        </ul>
        
        <p>You can now confidently set up your product thresholds and enable low stock alerts. When your inventory drops below the configured thresholds, you'll receive email notifications just like this one.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <p><strong>Ready to set up your low stock alerts?</strong></p>
            <p>Go to your Low Stock Pulse app and configure your product thresholds!</p>
        </div>
    </div>
    
    <div class="footer">
        <p>This email was sent by Low Stock Pulse app for {{ $shopDomain }}</p>
        <p>If you didn't request this test email, you can safely ignore it.</p>
    </div>
</body>
</html>
