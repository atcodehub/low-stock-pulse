<?php

namespace App\Jobs;

use App\Models\ProductThreshold;
use App\Services\ShopifyProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shopify\Auth\Session;

class UpdateInventoryLevelsJob implements ShouldQueue
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
            Log::info("Starting inventory update for shop: {$this->shopDomain}");

            // Create a session for the Shopify API
            $session = new Session(
                id: uniqid(),
                shop: $this->shopDomain,
                isOnline: false,
                state: null
            );
            $session->setAccessToken($this->accessToken);

            $shopifyService = new ShopifyProductService($session);
            $updatedCount = $shopifyService->updateInventoryLevels($this->shopDomain);

            Log::info("Updated inventory for {$updatedCount} products in shop: {$this->shopDomain}");

            // Dispatch alert processing job after inventory update
            ProcessAlertsJob::dispatch($this->shopDomain, $this->accessToken);

        } catch (\Exception $e) {
            Log::error("Failed to update inventory levels for shop {$this->shopDomain}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("UpdateInventoryLevelsJob failed for shop {$this->shopDomain}: " . $exception->getMessage());
    }
}
