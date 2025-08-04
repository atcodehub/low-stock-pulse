<?php

namespace App\Services;

use App\Models\ProductThreshold;
use App\Models\AlertSetting;
use App\Models\ActivityLog;
use App\Models\Session;
use App\Mail\TestEmail;
use App\Services\ShopifyEmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;
use Shopify\Auth\Session as ShopifySession;

class EmailNotificationService
{
    /**
     * Send instant alert for a single product.
     */
    public function sendInstantAlert(ProductThreshold $productThreshold, AlertSetting $alertSettings): bool
    {
        try {
            $emailAddress = $this->getAlertEmailAddress($alertSettings);
            
            $emailData = [
                'product' => $productThreshold,
                'alert_type' => 'instant',
                'shop_domain' => $alertSettings->shop_domain,
            ];

            // For now, we'll use Laravel's mail system
            // In production, this should be replaced with Shopify Transactional Email
            $success = $this->sendEmail($emailAddress, 'Low Stock Alert', $emailData);

            // Log the activity
            ActivityLog::createLog(
                $alertSettings->shop_domain,
                $productThreshold,
                $emailAddress,
                'instant',
                $success,
                $success ? null : 'Failed to send email',
                $emailData
            );

            if ($success) {
                $productThreshold->update(['last_alert_sent_at' => now()]);
            }

            return $success;
            
        } catch (\Exception $e) {
            Log::error('Failed to send instant alert: ' . $e->getMessage());
            
            ActivityLog::createLog(
                $alertSettings->shop_domain,
                $productThreshold,
                $alertSettings->alert_email ?? 'unknown',
                'instant',
                false,
                $e->getMessage()
            );
            
            return false;
        }
    }

    /**
     * Send daily batch alert.
     */
    public function sendDailyBatchAlert(string $shopDomain): bool
    {
        try {
            $alertSettings = AlertSetting::getOrCreateForShop($shopDomain);
            
            if (!$alertSettings->notifications_enabled || $alertSettings->alert_frequency !== 'daily') {
                return false;
            }

            $belowThresholdProducts = ProductThreshold::forShop($shopDomain)
                ->withAlertsEnabled()
                ->belowThreshold()
                ->get();

            if ($belowThresholdProducts->isEmpty()) {
                return true; // No products below threshold, consider it successful
            }

            $emailAddress = $this->getAlertEmailAddress($alertSettings);
            
            $emailData = [
                'products' => $belowThresholdProducts,
                'alert_type' => 'daily_batch',
                'shop_domain' => $shopDomain,
                'total_count' => $belowThresholdProducts->count(),
            ];

            $success = $this->sendBatchEmail($emailAddress, 'Daily Low Stock Report', $emailData);

            // Log activity for each product
            foreach ($belowThresholdProducts as $product) {
                ActivityLog::createLog(
                    $shopDomain,
                    $product,
                    $emailAddress,
                    'daily_batch',
                    $success,
                    $success ? null : 'Failed to send daily batch email',
                    $emailData
                );

                if ($success) {
                    $product->update(['last_alert_sent_at' => now()]);
                }
            }

            if ($success) {
                $alertSettings->update(['last_daily_alert_sent' => now()]);
            }

            return $success;
            
        } catch (\Exception $e) {
            Log::error('Failed to send daily batch alert: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send weekly batch alert.
     */
    public function sendWeeklyBatchAlert(string $shopDomain): bool
    {
        try {
            $alertSettings = AlertSetting::getOrCreateForShop($shopDomain);
            
            if (!$alertSettings->notifications_enabled || $alertSettings->alert_frequency !== 'weekly') {
                return false;
            }

            $belowThresholdProducts = ProductThreshold::forShop($shopDomain)
                ->withAlertsEnabled()
                ->belowThreshold()
                ->get();

            if ($belowThresholdProducts->isEmpty()) {
                return true; // No products below threshold, consider it successful
            }

            $emailAddress = $this->getAlertEmailAddress($alertSettings);
            
            $emailData = [
                'products' => $belowThresholdProducts,
                'alert_type' => 'weekly_batch',
                'shop_domain' => $shopDomain,
                'total_count' => $belowThresholdProducts->count(),
            ];

            $success = $this->sendBatchEmail($emailAddress, 'Weekly Low Stock Report', $emailData);

            // Log activity for each product
            foreach ($belowThresholdProducts as $product) {
                ActivityLog::createLog(
                    $shopDomain,
                    $product,
                    $emailAddress,
                    'weekly_batch',
                    $success,
                    $success ? null : 'Failed to send weekly batch email',
                    $emailData
                );

                if ($success) {
                    $product->update(['last_alert_sent_at' => now()]);
                }
            }

            if ($success) {
                $alertSettings->update(['last_weekly_alert_sent' => now()]);
            }

            return $success;
            
        } catch (\Exception $e) {
            Log::error('Failed to send weekly batch alert: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send test email.
     */
    public function sendTestEmail(string $emailAddress, string $shopDomain): bool
    {
        try {
            $testMessage = 'This is a test email from Low Stock Pulse app to verify your email configuration is working correctly.';
            $sentAt = now()->format('Y-m-d H:i:s');

            Mail::to($emailAddress)->send(new TestEmail($shopDomain, $testMessage, $sentAt));

            Log::info("Test email sent successfully to {$emailAddress} for shop {$shopDomain}");
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send test email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the email address to send alerts to.
     */
    private function getAlertEmailAddress(AlertSetting $alertSettings): string
    {
        // Use custom email if set, otherwise use shop owner email
        return $alertSettings->alert_email ?? $this->getShopOwnerEmail($alertSettings->shop_domain);
    }

    /**
     * Get shop owner email from Shopify.
     */
    private function getShopOwnerEmail(string $shopDomain): string
    {
        // TODO: Implement Shopify API call to get shop owner email
        // For now, return a default email
        return 'shop-owner@' . $shopDomain;
    }

    /**
     * Send single product email using ShopifyEmailService.
     */
    private function sendEmail(string $emailAddress, string $subject, array $data): bool
    {
        try {
            Log::info("Sending email to {$emailAddress} with subject: {$subject}", $data);

            // Get shop session
            $shopDomain = $data['shop_domain'];
            $session = Session::where('shop', $shopDomain)->first();

            if (!$session) {
                Log::error("No session found for shop {$shopDomain}");
                return false;
            }

            // Create Shopify session
            $shopifySession = new ShopifySession(
                id: $session->id,
                shop: $session->shop,
                isOnline: false,
                state: ''
            );
            $shopifySession->setAccessToken($session->access_token);

            // Prepare products array for the email
            $products = [];
            if (isset($data['product']) && $data['product'] instanceof ProductThreshold) {
                $products[] = [
                    'title' => $data['product']->product_title,
                    'variant_title' => $data['product']->variant_title,
                    'current_inventory' => $data['product']->current_inventory,
                    'threshold_quantity' => $data['product']->threshold_quantity,
                    'sku' => $data['product']->sku ?? '',
                ];
            }

            // Use ShopifyEmailService to send the alert
            $shopifyEmailService = new ShopifyEmailService();
            $result = $shopifyEmailService->sendLowStockAlert($shopifySession, $products, $emailAddress);

            return $result['success'] ?? false;

        } catch (\Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send batch email with multiple products using ShopifyEmailService.
     */
    private function sendBatchEmail(string $emailAddress, string $subject, array $data): bool
    {
        try {
            Log::info("Sending batch email to {$emailAddress} with subject: {$subject}", [
                'product_count' => $data['total_count'],
                'alert_type' => $data['alert_type'],
            ]);

            // Get shop session
            $shopDomain = $data['shop_domain'];
            $session = Session::where('shop', $shopDomain)->first();

            if (!$session) {
                Log::error("No session found for shop {$shopDomain}");
                return false;
            }

            // Create Shopify session
            $shopifySession = new ShopifySession(
                id: $session->id,
                shop: $session->shop,
                isOnline: false,
                state: ''
            );
            $shopifySession->setAccessToken($session->access_token);

            // Prepare products array for the email
            $products = [];
            if (isset($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as $product) {
                    if ($product instanceof ProductThreshold) {
                        $products[] = [
                            'title' => $product->product_title,
                            'variant_title' => $product->variant_title,
                            'current_inventory' => $product->current_inventory,
                            'threshold_quantity' => $product->threshold_quantity,
                            'sku' => $product->sku ?? '',
                        ];
                    }
                }
            }

            // Use ShopifyEmailService to send the batch alert
            $shopifyEmailService = new ShopifyEmailService();
            $result = $shopifyEmailService->sendLowStockAlert($shopifySession, $products, $emailAddress);

            return $result['success'] ?? false;

        } catch (\Exception $e) {
            Log::error('Failed to send batch email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process all pending alerts based on frequency settings.
     */
    public function processAllPendingAlerts(): array
    {
        $results = [
            'instant' => 0,
            'daily' => 0,
            'weekly' => 0,
            'errors' => 0,
        ];

        try {
            // Process instant alerts
            $instantAlerts = ProductThreshold::with('alertSetting')
                ->whereHas('alertSetting', function ($query) {
                    $query->where('alert_frequency', 'instant')
                          ->where('notifications_enabled', true);
                })
                ->withAlertsEnabled()
                ->belowThreshold()
                ->get();

            foreach ($instantAlerts as $product) {
                if ($this->sendInstantAlert($product, $product->alertSetting)) {
                    $results['instant']++;
                } else {
                    $results['errors']++;
                }
            }

            // Process daily alerts
            $dailyShops = AlertSetting::where('alert_frequency', 'daily')
                ->where('notifications_enabled', true)
                ->get();

            foreach ($dailyShops as $alertSettings) {
                if ($alertSettings->shouldSendDailyAlert()) {
                    if ($this->sendDailyBatchAlert($alertSettings->shop_domain)) {
                        $results['daily']++;
                    } else {
                        $results['errors']++;
                    }
                }
            }

            // Process weekly alerts
            $weeklyShops = AlertSetting::where('alert_frequency', 'weekly')
                ->where('notifications_enabled', true)
                ->get();

            foreach ($weeklyShops as $alertSettings) {
                if ($alertSettings->shouldSendWeeklyAlert()) {
                    if ($this->sendWeeklyBatchAlert($alertSettings->shop_domain)) {
                        $results['weekly']++;
                    } else {
                        $results['errors']++;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to process pending alerts: ' . $e->getMessage());
            $results['errors']++;
        }

        return $results;
    }
}
