<?php

namespace App\Console\Commands;

use App\Jobs\UpdateInventoryLevelsJob;
use App\Models\AlertSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Lib\DbSessionStorage;

class ProcessLowStockAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'low-stock:process-alerts {--shop=} {--force}';

    /**
     * The console command description.
     */
    protected $description = 'Process low stock alerts for all shops or a specific shop';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $specificShop = $this->option('shop');
            $force = $this->option('force');

            if ($specificShop) {
                $this->info("Processing alerts for shop: {$specificShop}");
                $this->processShopAlerts($specificShop, $force);
            } else {
                $this->info("Processing alerts for all shops");
                $this->processAllShopsAlerts($force);
            }

            $this->info("Alert processing completed successfully");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to process alerts: " . $e->getMessage());
            Log::error("ProcessLowStockAlerts command failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Process alerts for all shops.
     */
    private function processAllShopsAlerts(bool $force): void
    {
        $alertSettings = AlertSetting::where('notifications_enabled', true)->get();
        
        $this->info("Found " . $alertSettings->count() . " shops with notifications enabled");

        foreach ($alertSettings as $setting) {
            $this->processShopAlerts($setting->shop_domain, $force);
        }
    }

    /**
     * Process alerts for a specific shop.
     */
    private function processShopAlerts(string $shopDomain, bool $force): void
    {
        try {
            // Get the session for this shop
            $sessionStorage = new DbSessionStorage();
            $session = $sessionStorage->loadSession($shopDomain);

            if (!$session || !$session->getAccessToken()) {
                $this->warn("No valid session found for shop: {$shopDomain}");
                return;
            }

            $alertSettings = AlertSetting::getOrCreateForShop($shopDomain);

            if (!$alertSettings->notifications_enabled && !$force) {
                $this->info("Notifications disabled for shop: {$shopDomain}");
                return;
            }

            // Dispatch the inventory update job which will trigger alert processing
            UpdateInventoryLevelsJob::dispatch($shopDomain, $session->getAccessToken());
            
            $this->info("Dispatched inventory update job for shop: {$shopDomain}");

        } catch (\Exception $e) {
            $this->error("Failed to process alerts for shop {$shopDomain}: " . $e->getMessage());
            Log::error("Failed to process alerts for shop {$shopDomain}: " . $e->getMessage());
        }
    }
}
