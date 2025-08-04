<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Models\ProductThreshold;
use App\Jobs\ProcessAlertsJob;
use App\Services\ShopifyProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Shopify\Auth\Session as ShopifySession;

class SyncInventoryAndCheckAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:sync-and-alert {--shop=} {--all}';

    /**
     * The console command description.
     */
    protected $description = 'Sync inventory from Shopify and check for low stock alerts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $specificShop = $this->option('shop');
            $syncAll = $this->option('all');

            if ($specificShop) {
                $this->info("Syncing inventory and checking alerts for shop: {$specificShop}");
                $this->syncInventoryForShop($specificShop);
            } elseif ($syncAll) {
                $this->info("Syncing inventory and checking alerts for all shops");
                $this->syncInventoryForAllShops();
            } else {
                $this->error("Please specify --shop=<shop-domain> or --all");
                return Command::FAILURE;
            }

            $this->info("Inventory sync and alert checking completed successfully");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to sync inventory: " . $e->getMessage());
            Log::error("SyncInventoryAndCheckAlerts command failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Sync inventory for all shops.
     */
    private function syncInventoryForAllShops(): void
    {
        $sessions = Session::all();
        
        $this->info("Found " . $sessions->count() . " shops");

        foreach ($sessions as $session) {
            $this->syncInventoryForShop($session->shop);
        }
    }

    /**
     * Sync inventory for a specific shop.
     */
    private function syncInventoryForShop(string $shopDomain): void
    {
        try {
            $session = Session::where('shop', $shopDomain)->first();

            if (!$session || !$session->access_token) {
                $this->warn("No valid session found for shop: {$shopDomain}");
                return;
            }

            // Create Shopify session
            $shopifySession = new ShopifySession(
                id: $session->id,
                shop: $session->shop,
                isOnline: false,
                state: ''
            );
            $shopifySession->setAccessToken($session->access_token);

            // Get tracked products before sync
            $trackedProducts = ProductThreshold::where('shop_domain', $shopDomain)->get();
            $beforeSync = [];
            foreach ($trackedProducts as $product) {
                $beforeSync[$product->id] = $product->current_inventory;
            }

            // Sync inventory from Shopify
            $shopifyService = new ShopifyProductService($shopifySession);
            $updatedCount = $shopifyService->updateInventoryLevels($shopDomain);

            $this->info("Updated inventory for {$updatedCount} products in {$shopDomain}");

            // Check for inventory changes and products below threshold
            $changedProducts = [];
            $belowThresholdProducts = [];
            
            $trackedProducts->fresh(); // Reload from database
            foreach ($trackedProducts as $product) {
                $oldInventory = $beforeSync[$product->id] ?? 0;
                $newInventory = $product->current_inventory;
                
                if ($oldInventory !== $newInventory) {
                    $changedProducts[] = [
                        'product' => $product->product_title,
                        'old' => $oldInventory,
                        'new' => $newInventory,
                        'change' => $newInventory - $oldInventory
                    ];
                }
                
                if ($product->isBelowThreshold() && $product->alerts_enabled) {
                    $belowThresholdProducts[] = $product;
                }
            }

            // Report changes
            if (!empty($changedProducts)) {
                $this->info("Inventory changes detected:");
                foreach ($changedProducts as $change) {
                    $direction = $change['change'] > 0 ? '↗️' : '↘️';
                    $changeText = $change['change'] > 0 ? '+' . $change['change'] : $change['change'];
                    $this->info("  {$direction} {$change['product']}: {$change['old']} → {$change['new']} ({$changeText})");
                }
            } else {
                $this->info("No inventory changes detected");
            }

            // Report products below threshold
            if (!empty($belowThresholdProducts)) {
                $this->warn("Products below threshold:");
                foreach ($belowThresholdProducts as $product) {
                    $this->warn("  ⚠️  {$product->product_title}: {$product->current_inventory} < {$product->threshold_quantity}");
                }
                
                // Trigger alert processing
                ProcessAlertsJob::dispatch($shopDomain, $session->access_token);
                $this->info("🔔 Alert processing triggered for {$shopDomain}");
            } else {
                $this->info("✅ All products are above their thresholds");
            }

        } catch (\Exception $e) {
            $this->error("Failed to sync inventory for shop {$shopDomain}: " . $e->getMessage());
            Log::error("Failed to sync inventory for shop {$shopDomain}: " . $e->getMessage());
        }
    }

    /**
     * Show current inventory status for a shop.
     */
    public function showInventoryStatus(string $shopDomain): void
    {
        try {
            $products = ProductThreshold::where('shop_domain', $shopDomain)->get();
            
            if ($products->isEmpty()) {
                $this->info("No tracked products found for {$shopDomain}");
                return;
            }

            $this->info("Current inventory status for {$shopDomain}:");
            $this->table(
                ['Product', 'Current', 'Threshold', 'Status', 'Alerts'],
                $products->map(function ($product) {
                    return [
                        $product->product_title,
                        $product->current_inventory,
                        $product->threshold_quantity,
                        $product->isBelowThreshold() ? '⚠️ Below' : '✅ OK',
                        $product->alerts_enabled ? 'ON' : 'OFF'
                    ];
                })
            );

        } catch (\Exception $e) {
            $this->error("Failed to show inventory status: " . $e->getMessage());
        }
    }
}
