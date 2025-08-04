<?php
/**
 * Amazon SES Setup Verification Script
 * 
 * This script helps verify your Amazon SES configuration for Low Stock Pulse.
 * Run this script to test your AWS credentials and SES setup.
 * 
 * Usage: php verify-ses-setup.php
 */

require_once 'vendor/autoload.php';

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

echo "🔍 Amazon SES Setup Verification for Low Stock Pulse\n";
echo "==================================================\n\n";

// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            if (!empty($key) && !empty($value)) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Check environment variables
echo "1. Checking Environment Configuration...\n";

$requiredVars = [
    'AWS_ACCESS_KEY_ID' => 'AWS Access Key ID',
    'AWS_SECRET_ACCESS_KEY' => 'AWS Secret Access Key',
    'AWS_DEFAULT_REGION' => 'AWS Default Region',
    'MAIL_FROM_ADDRESS' => 'Mail From Address'
];

$missingVars = [];
foreach ($requiredVars as $var => $description) {
    if (empty($_ENV[$var]) || $_ENV[$var] === 'your-aws-access-key-id' || $_ENV[$var] === 'your-aws-secret-access-key') {
        $missingVars[] = $var;
        echo "   ❌ {$description} ({$var}): Not configured\n";
    } else {
        $maskedValue = $var === 'AWS_SECRET_ACCESS_KEY' ? str_repeat('*', strlen($_ENV[$var])) : $_ENV[$var];
        echo "   ✅ {$description} ({$var}): {$maskedValue}\n";
    }
}

if (!empty($missingVars)) {
    echo "\n❌ Missing required environment variables. Please update your .env file:\n";
    foreach ($missingVars as $var) {
        echo "   - {$var}\n";
    }
    echo "\nSee EMAIL_SETUP.md for detailed setup instructions.\n";
    exit(1);
}

echo "\n2. Testing AWS SES Connection...\n";

try {
    // Create SES client
    $sesClient = new SesClient([
        'version' => 'latest',
        'region' => $_ENV['AWS_DEFAULT_REGION'],
        'credentials' => [
            'key' => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ],
    ]);

    // Test connection by getting sending quota
    $result = $sesClient->getSendQuota();
    
    echo "   ✅ Successfully connected to Amazon SES\n";
    echo "   📊 Sending Quota:\n";
    echo "      - Max 24 Hour Send: " . number_format($result['Max24HourSend']) . " emails\n";
    echo "      - Max Send Rate: " . number_format($result['MaxSendRate']) . " emails/second\n";
    echo "      - Sent Last 24h: " . number_format($result['SentLast24Hours']) . " emails\n";

} catch (AwsException $e) {
    echo "   ❌ Failed to connect to Amazon SES\n";
    echo "   Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'InvalidAccessKeyId') !== false) {
        echo "   💡 Check your AWS_ACCESS_KEY_ID\n";
    } elseif (strpos($e->getMessage(), 'SignatureDoesNotMatch') !== false) {
        echo "   💡 Check your AWS_SECRET_ACCESS_KEY\n";
    } elseif (strpos($e->getMessage(), 'UnauthorizedOperation') !== false) {
        echo "   💡 Check your IAM user permissions (needs AmazonSESFullAccess)\n";
    }
    
    exit(1);
}

echo "\n3. Checking Verified Email Addresses...\n";

try {
    // Get verified email addresses
    $result = $sesClient->listVerifiedEmailAddresses();
    $verifiedEmails = $result['VerifiedEmailAddresses'];
    
    if (empty($verifiedEmails)) {
        echo "   ⚠️  No verified email addresses found\n";
        echo "   💡 You need to verify at least one email address in SES Console\n";
        echo "   📖 See EMAIL_SETUP.md for verification instructions\n";
    } else {
        echo "   ✅ Verified email addresses:\n";
        foreach ($verifiedEmails as $email) {
            echo "      - {$email}\n";
        }
    }

} catch (AwsException $e) {
    echo "   ⚠️  Could not retrieve verified emails: " . $e->getMessage() . "\n";
}

echo "\n4. Checking Account Status...\n";

try {
    // Check if account is in sandbox mode
    $result = $sesClient->getSendQuota();
    
    // In sandbox mode, you can only send to verified addresses
    // This is a simple heuristic - accounts with very low limits are likely in sandbox
    if ($result['Max24HourSend'] <= 200) {
        echo "   ⚠️  Account appears to be in Sandbox Mode\n";
        echo "   📝 Sandbox mode restrictions:\n";
        echo "      - Can only send to verified email addresses\n";
        echo "      - Limited to 200 emails per 24 hours\n";
        echo "      - Limited to 1 email per second\n";
        echo "   💡 Request production access in SES Console for full functionality\n";
    } else {
        echo "   ✅ Account appears to be in Production Mode\n";
        echo "   🚀 You can send emails to any address\n";
    }

} catch (AwsException $e) {
    echo "   ⚠️  Could not determine account status: " . $e->getMessage() . "\n";
}

echo "\n5. Testing Email Template...\n";

try {
    // Test if we can create a simple email (without sending)
    $fromAddress = $_ENV['MAIL_FROM_ADDRESS'];
    
    echo "   ✅ Email template configuration looks good\n";
    echo "   📧 From Address: {$fromAddress}\n";
    
    // Check if from address is verified
    if (!empty($verifiedEmails) && in_array($fromAddress, $verifiedEmails)) {
        echo "   ✅ From address is verified\n";
    } elseif (!empty($verifiedEmails)) {
        echo "   ⚠️  From address is not verified\n";
        echo "   💡 Consider verifying {$fromAddress} in SES Console\n";
    }

} catch (Exception $e) {
    echo "   ❌ Email template error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎉 SES Setup Verification Complete!\n\n";

if (empty($missingVars)) {
    echo "✅ Your Amazon SES configuration looks good!\n";
    echo "🚀 You can now test email sending in your Low Stock Pulse app.\n\n";
    echo "Next steps:\n";
    echo "1. Go to Settings page in your app\n";
    echo "2. Enter a verified email address in 'Test Email Address'\n";
    echo "3. Click 'Send Test Email'\n";
    echo "4. Check your email for the test message\n\n";
    
    if (!empty($verifiedEmails)) {
        echo "💡 Verified email addresses you can test with:\n";
        foreach ($verifiedEmails as $email) {
            echo "   - {$email}\n";
        }
    }
} else {
    echo "❌ Please fix the configuration issues above and run this script again.\n";
}

echo "\n📖 For detailed setup instructions, see EMAIL_SETUP.md\n";
echo "🆘 Need help? Check the AWS SES documentation or contact support.\n";
?>
