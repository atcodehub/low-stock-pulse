<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Mail\TestEmail;
use App\Mail\LowStockAlert;
use Shopify\Clients\Graphql;
use Shopify\Clients\Rest;

class ShopifyEmailService
{
    /**
     * Get shop information for email branding.
     */
    public function getShopInfo($session): array
    {
        try {
            // Handle different session object types
            $shopDomain = $this->getShopDomain($session);
            $accessToken = $this->getAccessToken($session);

            if (!$shopDomain || !$accessToken) {
                return $this->getDefaultShopInfo($shopDomain ?: 'unknown.myshopify.com');
            }

            $client = new Graphql($shopDomain, $accessToken);

            $query = '
                query getShop {
                    shop {
                        name
                        email
                        domain
                        myshopifyDomain
                        contactEmail
                        customerEmail
                        plan {
                            displayName
                        }
                    }
                }
            ';

            $response = $client->query(['query' => $query]);
            $body = $response->getDecodedBody();

            if (isset($body['data']['shop'])) {
                $shop = $body['data']['shop'];
                return [
                    'name' => $shop['name'],
                    'email' => $shop['email'] ?? $shop['contactEmail'] ?? $shop['customerEmail'],
                    'domain' => $shop['domain'],
                    'myshopify_domain' => $shop['myshopifyDomain'],
                    'plan' => $shop['plan']['displayName'] ?? 'Basic',
                ];
            }

            return $this->getDefaultShopInfo($shopDomain);

        } catch (\Exception $e) {
            Log::error('Failed to fetch shop info: ' . $e->getMessage());
            $shopDomain = $this->getShopDomain($session);
            return $this->getDefaultShopInfo($shopDomain ?: 'unknown.myshopify.com');
        }
    }

    /**
     * Get shop domain from session object.
     */
    private function getShopDomain($session): ?string
    {
        if (method_exists($session, 'getShop')) {
            return $session->getShop();
        }
        if (isset($session->shop)) {
            return $session->shop;
        }
        return null;
    }

    /**
     * Get access token from session object.
     */
    private function getAccessToken($session): ?string
    {
        if (method_exists($session, 'getAccessToken')) {
            return $session->getAccessToken();
        }
        if (isset($session->accessToken)) {
            return $session->accessToken;
        }
        return null;
    }
    
    /**
     * Get default shop info if API call fails.
     */
    private function getDefaultShopInfo(string $shopDomain): array
    {
        return [
            'name' => str_replace('.myshopify.com', '', $shopDomain),
            'email' => "admin@{$shopDomain}",
            'domain' => $shopDomain,
            'myshopify_domain' => $shopDomain,
            'plan' => 'Basic',
        ];
    }

    /**
     * Configure Laravel Mail to use Shopify-style settings with SES.
     */
    private function configureShopifyMail(array $shopInfo): void
    {
        // For SES, we need to use a verified email address
        // Set the from address - in production, this should be a verified SES domain
        $fromAddress = env('MAIL_FROM_ADDRESS', "noreply@lowstockpulse.com");
        $fromName = "Low Stock Pulse - {$shopInfo['name']}";

        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);

        // Log the configuration for debugging
        Log::info("Email configured for shop {$shopInfo['name']}: From {$fromName} <{$fromAddress}>");
    }
    
    /**
     * Send test email using Shopify-integrated approach.
     */
    public function sendTestEmail($session, string $emailAddress): array
    {
        try {
            $shopInfo = $this->getShopInfo($session);

            // Configure mail settings dynamically for this shop
            $this->configureShopifyMail($shopInfo);

            // Validate email address format
            if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address format',
                ];
            }

            $testMessage = "This is a test email from Low Stock Pulse app for {$shopInfo['name']}. Your Amazon SES email configuration is working correctly!";
            $sentAt = now()->format('Y-m-d H:i:s');

            // Send actual email using Laravel Mail with Amazon SES
            Mail::to($emailAddress)->send(new TestEmail(
                $shopInfo['domain'],
                $testMessage,
                $sentAt
            ));

            // Log successful send
            Log::info("Test email sent via Amazon SES to {$emailAddress} for shop {$shopInfo['name']}");

            return [
                'success' => true,
                'message' => "Test email sent successfully via Amazon SES to {$emailAddress}",
                'shop_info' => $shopInfo,
                'email_sent_to' => $emailAddress,
                'email_service' => 'Amazon SES',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send test email via Amazon SES: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Provide more specific error messages
            $errorMessage = $this->getEmailErrorMessage($e);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_details' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Send low stock alert using Shopify-integrated approach.
     */
    public function sendLowStockAlert($session, array $products, string $emailAddress): array
    {
        try {
            $shopInfo = $this->getShopInfo($session);

            // Configure mail settings dynamically for this shop
            $this->configureShopifyMail($shopInfo);

            $sentAt = now()->format('Y-m-d H:i:s');

            // Validate email address format
            if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address format',
                ];
            }

            // Send actual email using Laravel Mail with Amazon SES
            Mail::to($emailAddress)->send(new LowStockAlert(
                $shopInfo['name'],
                $shopInfo['domain'],
                $products,
                $sentAt
            ));

            // Log successful send
            Log::info("Low stock alert sent via Amazon SES to {$emailAddress} for shop {$shopInfo['name']} with " . count($products) . " products");

            return [
                'success' => true,
                'message' => "Low stock alert sent successfully via Amazon SES to {$emailAddress}",
                'products_count' => count($products),
                'email_sent_to' => $emailAddress,
                'email_service' => 'Amazon SES',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send low stock alert via Amazon SES: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Provide more specific error messages
            $errorMessage = $this->getEmailErrorMessage($e);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_details' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Log email in Shopify-style format.
     */
    private function logShopifyEmail(array $emailData, string $type = 'Test Email'): void
    {
        $logMessage = "
=== SHOPIFY EMAIL SENT ===
Type: {$type}
Shop: {$emailData['shop_name']} ({$emailData['shop_domain']})
To: " . ($emailData['test_email'] ?? $emailData['alert_email']) . "
From: {$emailData['app_name']} <noreply@{$emailData['shop_domain']}>
Sent At: {$emailData['sent_at']}
";
        
        if (isset($emailData['products'])) {
            $logMessage .= "Products: {$emailData['product_count']} low stock items\n";
        }
        
        $logMessage .= "========================\n";
        
        Log::info($logMessage);
    }
    
    /**
     * Check if shop has email capabilities.
     */
    public function canSendEmails($session): bool
    {
        try {
            $shopInfo = $this->getShopInfo($session);
            return !empty($shopInfo['email']);
        } catch (\Exception $e) {
            Log::error('Failed to check email capabilities: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user-friendly error message for email failures.
     */
    private function getEmailErrorMessage(\Exception $e): string
    {
        $message = $e->getMessage();

        // Check for common SES errors
        if (strpos($message, 'Email address not verified') !== false) {
            return 'Email address not verified in Amazon SES. Please verify the sender email address in your AWS SES console.';
        }

        if (strpos($message, 'Access denied') !== false || strpos($message, 'InvalidAccessKeyId') !== false) {
            return 'Invalid AWS credentials. Please check your AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in the .env file.';
        }

        if (strpos($message, 'Sending quota exceeded') !== false) {
            return 'Amazon SES sending quota exceeded. Please check your SES limits in the AWS console.';
        }

        if (strpos($message, 'Daily sending quota exceeded') !== false) {
            return 'Daily sending quota exceeded for Amazon SES. Please wait or request a limit increase.';
        }

        if (strpos($message, 'Rate exceeded') !== false) {
            return 'Amazon SES rate limit exceeded. Please wait a moment and try again.';
        }

        if (strpos($message, 'MessageRejected') !== false) {
            return 'Email rejected by Amazon SES. Please check the recipient email address and your SES configuration.';
        }

        // Default error message
        return 'Failed to send email via Amazon SES. Please check your configuration and try again.';
    }

    /**
     * Get recommended email service for the shop.
     */
    public function getRecommendedEmailService($session): string
    {
        return 'Amazon SES (Currently Configured)';
    }
}
