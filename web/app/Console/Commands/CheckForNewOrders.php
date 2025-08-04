<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Models\ProductThreshold;
use App\Jobs\ProcessAlertsJob;
use App\Services\ShopifyProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Shopify\Clients\Rest;
use Shopify\Auth\Session as ShopifySession;

class CheckForNewOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:check-new {--shop=} {--all}';

    /**
     * The console command description.
     */
    protected $description = 'Check for new orders and update inventory accordingly';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $specificShop = $this->option('shop');
            $checkAll = $this->option('all');

            if ($specificShop) {
                $this->info("Checking new orders for shop: {$specificShop}");
                $this->checkOrdersForShop($specificShop);
            } elseif ($checkAll) {
                $this->info("Checking new orders for all shops");
                $this->checkOrdersForAllShops();
            } else {
                $this->error("Please specify --shop=<shop-domain> or --all");
                return Command::FAILURE;
            }

            $this->info("Order checking completed successfully");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to check orders: " . $e->getMessage());
            Log::error("CheckForNewOrders command failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Check orders for all shops.
     */
    private function checkOrdersForAllShops(): void
    {
        $sessions = Session::all();
        
        $this->info("Found " . $sessions->count() . " shops");

        foreach ($sessions as $session) {
            $this->checkOrdersForShop($session->shop);
        }
    }

    /**
     * Check orders for a specific shop.
     */
    private function checkOrdersForShop(string $shopDomain): void
    {
        try {
            $session = Session::where('shop', $shopDomain)->first();

            if (!$session || !$session->access_token) {
                $this->warn("No valid session found for shop: {$shopDomain}");
                return;
            }

            // Get the last checked timestamp
            $cacheKey = "last_order_check_{$shopDomain}";
            $lastChecked = Cache::get($cacheKey, now()->subMinutes(5)->toISOString());

            $client = new Rest($shopDomain, $session->access_token);
            
            // Get orders created since last check
            $response = $client->get('orders', [
                'status' => 'any',
                'created_at_min' => $lastChecked,
                'limit' => 50
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->error("Failed to fetch orders for {$shopDomain}");
                return;
            }

            $body = $response->getDecodedBody();
            $orders = $body['orders'] ?? [];

            if (empty($orders)) {
                $this->info("No new orders found for {$shopDomain}");
                Cache::put($cacheKey, now()->toISOString(), 3600); // Cache for 1 hour
                return;
            }

            $this->info("Found " . count($orders) . " new orders for {$shopDomain}");

            $inventoryUpdated = false;

            foreach ($orders as $order) {
                $orderId = $order['id'];
                $lineItems = $order['line_items'] ?? [];

                $this->info("Processing order #{$order['order_number']} with " . count($lineItems) . " items");

                foreach ($lineItems as $lineItem) {
                    $variantId = $lineItem['variant_id'] ?? null;
                    $quantity = $lineItem['quantity'] ?? 0;

                    if ($variantId && $quantity > 0) {
                        if ($this->updateInventoryForVariant($shopDomain, $variantId, $quantity)) {
                            $inventoryUpdated = true;
                        }
                    }
                }
            }

            // Update the last checked timestamp
            Cache::put($cacheKey, now()->toISOString(), 3600);

            // If inventory was updated, trigger alert processing
            if ($inventoryUpdated) {
                $this->triggerAlertProcessing($shopDomain, $session->access_token);
            }

        } catch (\Exception $e) {
            $this->error("Failed to check orders for shop {$shopDomain}: " . $e->getMessage());
            Log::error("Failed to check orders for shop {$shopDomain}: " . $e->getMessage());
        }
    }

    /**
     * Update inventory for a specific variant.
     */
    private function updateInventoryForVariant(string $shopDomain, string $variantId, int $quantityOrdered): bool
    {
        try {
            $productThreshold = ProductThreshold::where('shop_domain', $shopDomain)
                ->where('shopify_variant_id', $variantId)
                ->first();

            if (!$productThreshold) {
                $this->info("No tracked product found for variant {$variantId}");
                return false;
            }

            $oldInventory = $productThreshold->current_inventory;
            $newInventory = max(0, $oldInventory - $quantityOrdered);

            $productThreshold->update([
                'current_inventory' => $newInventory,
                'last_checked_at' => now(),
            ]);

            $this->info("Updated inventory for {$productThreshold->product_title}: {$oldInventory} -> {$newInventory} (ordered: {$quantityOrdered})");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to update inventory for variant {$variantId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger alert processing for a shop.
     */
    private function triggerAlertProcessing(string $shopDomain, string $accessToken): void
    {
        try {
            ProcessAlertsJob::dispatch($shopDomain, $accessToken);
            $this->info("Triggered alert processing for shop {$shopDomain}");
            
        } catch (\Exception $e) {
            Log::error("Failed to trigger alert processing for shop {$shopDomain}: " . $e->getMessage());
        }
    }

    /**
     * Reset the last checked timestamp for a shop (useful for testing).
     */
    public function resetLastChecked(string $shopDomain): void
    {
        $cacheKey = "last_order_check_{$shopDomain}";
        Cache::forget($cacheKey);
        $this->info("Reset last checked timestamp for {$shopDomain}");
    }
}
