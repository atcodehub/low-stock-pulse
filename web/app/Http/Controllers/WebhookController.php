<?php

namespace App\Http\Controllers;

use App\Models\ProductThreshold;
use App\Models\Session;
use App\Jobs\ProcessAlertsJob;
use App\Services\ShopifyProductService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Shopify\Auth\Session as ShopifySession;

class WebhookController extends Controller
{
    /**
     * Handle inventory level updates webhook.
     */
    public function handleInventoryLevelUpdate(Request $request): Response
    {
        try {
            $payload = $request->all();
            $shopDomain = $request->header('X-Shopify-Shop-Domain');
            
            Log::info("Inventory level update webhook received for shop: {$shopDomain}", $payload);
            
            if (!$shopDomain) {
                Log::error('No shop domain in webhook headers');
                return response('Missing shop domain', 400);
            }
            
            // Extract inventory data
            $inventoryItemId = $payload['inventory_item_id'] ?? null;
            $locationId = $payload['location_id'] ?? null;
            $available = $payload['available'] ?? null;
            
            if (!$inventoryItemId || $available === null) {
                Log::warning('Missing required inventory data in webhook payload');
                return response('Missing inventory data', 400);
            }
            
            // Find products with this inventory item
            $this->updateProductInventoryByInventoryItem($shopDomain, $inventoryItemId, $available);
            
            // Trigger alert processing
            $this->triggerAlertProcessing($shopDomain);
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to process inventory level update webhook: ' . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }
    
    /**
     * Handle order creation webhook.
     */
    public function handleOrderCreate(Request $request): Response
    {
        try {
            $payload = $request->all();
            $shopDomain = $request->header('X-Shopify-Shop-Domain');
            
            Log::info("Order create webhook received for shop: {$shopDomain}", ['order_id' => $payload['id'] ?? 'unknown']);
            
            if (!$shopDomain) {
                Log::error('No shop domain in webhook headers');
                return response('Missing shop domain', 400);
            }
            
            // Extract line items to update inventory
            $lineItems = $payload['line_items'] ?? [];
            
            foreach ($lineItems as $lineItem) {
                $variantId = $lineItem['variant_id'] ?? null;
                $quantity = $lineItem['quantity'] ?? 0;
                
                if ($variantId && $quantity > 0) {
                    $this->updateProductInventoryByVariant($shopDomain, $variantId, $quantity);
                }
            }
            
            // Trigger alert processing after inventory updates
            $this->triggerAlertProcessing($shopDomain);
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to process order create webhook: ' . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }
    
    /**
     * Handle product update webhook.
     */
    public function handleProductUpdate(Request $request): Response
    {
        try {
            $payload = $request->all();
            $shopDomain = $request->header('X-Shopify-Shop-Domain');
            
            Log::info("Product update webhook received for shop: {$shopDomain}", ['product_id' => $payload['id'] ?? 'unknown']);
            
            if (!$shopDomain) {
                Log::error('No shop domain in webhook headers');
                return response('Missing shop domain', 400);
            }
            
            $productId = $payload['id'] ?? null;
            $variants = $payload['variants'] ?? [];
            
            if (!$productId) {
                Log::warning('Missing product ID in webhook payload');
                return response('Missing product ID', 400);
            }
            
            // Update inventory for all variants of this product
            foreach ($variants as $variant) {
                $variantId = $variant['id'] ?? null;
                $inventoryQuantity = $variant['inventory_quantity'] ?? null;
                
                if ($variantId && $inventoryQuantity !== null) {
                    $this->updateProductInventoryDirect($shopDomain, $productId, $variantId, $inventoryQuantity);
                }
            }
            
            // Trigger alert processing
            $this->triggerAlertProcessing($shopDomain);
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to process product update webhook: ' . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }
    
    /**
     * Update product inventory by inventory item ID.
     */
    private function updateProductInventoryByInventoryItem(string $shopDomain, string $inventoryItemId, int $available): void
    {
        try {
            // This would require mapping inventory items to variants
            // For now, we'll trigger a full inventory sync
            $this->syncAllInventoryForShop($shopDomain);
            
        } catch (\Exception $e) {
            Log::error("Failed to update inventory by inventory item {$inventoryItemId}: " . $e->getMessage());
        }
    }
    
    /**
     * Update product inventory by variant ID.
     */
    private function updateProductInventoryByVariant(string $shopDomain, string $variantId, int $quantityOrdered): void
    {
        try {
            // Find the product threshold for this variant
            $productThreshold = ProductThreshold::where('shop_domain', $shopDomain)
                ->where('shopify_variant_id', $variantId)
                ->first();
                
            if ($productThreshold) {
                // Decrease inventory by the ordered quantity
                $newInventory = max(0, $productThreshold->current_inventory - $quantityOrdered);
                
                $productThreshold->update([
                    'current_inventory' => $newInventory,
                    'last_checked_at' => now(),
                ]);
                
                Log::info("Updated inventory for variant {$variantId}: {$productThreshold->current_inventory} -> {$newInventory}");
            } else {
                Log::info("No tracked product found for variant {$variantId}, syncing from Shopify");
                $this->syncAllInventoryForShop($shopDomain);
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to update inventory by variant {$variantId}: " . $e->getMessage());
        }
    }
    
    /**
     * Update product inventory directly with new value.
     */
    private function updateProductInventoryDirect(string $shopDomain, string $productId, string $variantId, int $inventoryQuantity): void
    {
        try {
            $productThreshold = ProductThreshold::where('shop_domain', $shopDomain)
                ->where('shopify_product_id', $productId)
                ->where('shopify_variant_id', $variantId)
                ->first();
                
            if ($productThreshold) {
                $oldInventory = $productThreshold->current_inventory;
                
                $productThreshold->update([
                    'current_inventory' => $inventoryQuantity,
                    'last_checked_at' => now(),
                ]);
                
                Log::info("Updated inventory for product {$productId}, variant {$variantId}: {$oldInventory} -> {$inventoryQuantity}");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to update inventory directly for product {$productId}, variant {$variantId}: " . $e->getMessage());
        }
    }
    
    /**
     * Sync all inventory for a shop from Shopify.
     */
    private function syncAllInventoryForShop(string $shopDomain): void
    {
        try {
            $session = Session::where('shop', $shopDomain)->first();
            
            if (!$session) {
                Log::error("No session found for shop {$shopDomain}");
                return;
            }
            
            $shopifySession = new ShopifySession(
                id: $session->id,
                shop: $session->shop,
                isOnline: false,
                state: ''
            );
            $shopifySession->setAccessToken($session->access_token);
            
            $shopifyService = new ShopifyProductService($shopifySession);
            $updatedCount = $shopifyService->updateInventoryLevels($shopDomain);
            
            Log::info("Synced inventory for {$updatedCount} products in shop {$shopDomain}");
            
        } catch (\Exception $e) {
            Log::error("Failed to sync inventory for shop {$shopDomain}: " . $e->getMessage());
        }
    }
    
    /**
     * Trigger alert processing for a shop.
     */
    private function triggerAlertProcessing(string $shopDomain): void
    {
        try {
            $session = Session::where('shop', $shopDomain)->first();
            
            if ($session) {
                ProcessAlertsJob::dispatch($shopDomain, $session->access_token);
                Log::info("Triggered alert processing for shop {$shopDomain}");
            } else {
                Log::error("No session found for alert processing in shop {$shopDomain}");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to trigger alert processing for shop {$shopDomain}: " . $e->getMessage());
        }
    }
}
