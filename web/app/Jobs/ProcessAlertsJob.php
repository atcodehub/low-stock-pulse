<?php

namespace App\Jobs;

use App\Models\AlertSetting;
use App\Models\ProductThreshold;
use App\Services\EmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $shopDomain;
    protected string $accessToken;

    /**
     * Create a new job instance.
     */
    public function __construct(string $shopDomain, string $accessToken)
    {
        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Processing alerts for shop: {$this->shopDomain}");

            $alertSettings = AlertSetting::getOrCreateForShop($this->shopDomain);
            
            if (!$alertSettings->notifications_enabled) {
                Log::info("Notifications disabled for shop: {$this->shopDomain}");
                return;
            }

            $emailService = new EmailNotificationService();

            // Process based on alert frequency
            switch ($alertSettings->alert_frequency) {
                case 'instant':
                    $this->processInstantAlerts($emailService, $alertSettings);
                    break;
                    
                case 'daily':
                    if ($alertSettings->shouldSendDailyAlert()) {
                        $this->processDailyAlerts($emailService, $alertSettings);
                    }
                    break;
                    
                case 'weekly':
                    if ($alertSettings->shouldSendWeeklyAlert()) {
                        $this->processWeeklyAlerts($emailService, $alertSettings);
                    }
                    break;
            }

        } catch (\Exception $e) {
            Log::error("Failed to process alerts for shop {$this->shopDomain}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process instant alerts for products below threshold.
     */
    private function processInstantAlerts(EmailNotificationService $emailService, AlertSetting $alertSettings): void
    {
        $belowThresholdProducts = ProductThreshold::forShop($this->shopDomain)
            ->withAlertsEnabled()
            ->belowThreshold()
            ->get();

        $alertsSent = 0;
        
        foreach ($belowThresholdProducts as $product) {
            // Check if we've already sent an alert recently (within last hour)
            if ($product->last_alert_sent_at && $product->last_alert_sent_at->diffInHours(now()) < 1) {
                continue;
            }

            if ($emailService->sendInstantAlert($product, $alertSettings)) {
                $alertsSent++;
            }
        }

        Log::info("Sent {$alertsSent} instant alerts for shop: {$this->shopDomain}");
    }

    /**
     * Process daily batch alerts.
     */
    private function processDailyAlerts(EmailNotificationService $emailService, AlertSetting $alertSettings): void
    {
        if ($emailService->sendDailyBatchAlert($this->shopDomain)) {
            Log::info("Sent daily batch alert for shop: {$this->shopDomain}");
        } else {
            Log::error("Failed to send daily batch alert for shop: {$this->shopDomain}");
        }
    }

    /**
     * Process weekly batch alerts.
     */
    private function processWeeklyAlerts(EmailNotificationService $emailService, AlertSetting $alertSettings): void
    {
        if ($emailService->sendWeeklyBatchAlert($this->shopDomain)) {
            Log::info("Sent weekly batch alert for shop: {$this->shopDomain}");
        } else {
            Log::error("Failed to send weekly batch alert for shop: {$this->shopDomain}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessAlertsJob failed for shop {$this->shopDomain}: " . $exception->getMessage());
    }
}
