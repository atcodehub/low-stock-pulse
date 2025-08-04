<?php

namespace App\Http\Controllers;

use App\Models\ProductThreshold;
use App\Services\ShopifyProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Get all products with their current thresholds and inventory.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');
            $shopDomain = $session->getShop();
            
            $shopifyService = new ShopifyProductService($session);
            
            // Get pagination parameters
            $limit = min($request->get('limit', 50), 100);
            $cursor = $request->get('cursor');
            
            // Fetch products from Shopify
            $shopifyData = $shopifyService->getAllProductsWithInventory($limit, $cursor);
            
            // Get existing thresholds for these products
            $productIds = collect($shopifyData['products'])->pluck('id')->toArray();
            $existingThresholds = ProductThreshold::forShop($shopDomain)
                ->whereIn('shopify_product_id', $productIds)
                ->get()
                ->keyBy(function ($item) {
                    return $item->shopify_product_id . '_' . ($item->shopify_variant_id ?? 'default');
                });
            
            // Merge Shopify data with threshold data
            $products = collect($shopifyData['products'])->map(function ($product) use ($existingThresholds, $shopDomain) {
                $variants = collect($product['variants'])->map(function ($variant) use ($product, $existingThresholds, $shopDomain) {
                    $key = $product['id'] . '_' . $variant['id'];
                    $threshold = $existingThresholds->get($key);
                    
                    $shopifyInventory = $variant['inventory_quantity'];
                    $thresholdQty = $threshold ? $threshold->threshold_quantity : 0;

                    // Use live Shopify inventory for comparison, not DB inventory
                    $isBelowThreshold = $threshold && $shopifyInventory < $thresholdQty;

                    return [
                        'id' => $variant['id'],
                        'title' => $variant['title'],
                        'sku' => $variant['sku'],
                        'inventory_quantity' => $shopifyInventory,
                        'threshold_quantity' => $thresholdQty,
                        'alerts_enabled' => $threshold ? $threshold->alerts_enabled : true,
                        'is_below_threshold' => $isBelowThreshold,
                        'last_checked_at' => $threshold ? $threshold->last_checked_at : null,
                        'last_alert_sent_at' => $threshold ? $threshold->last_alert_sent_at : null,
                    ];
                });
                
                return [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'handle' => $product['handle'],
                    'status' => $product['status'],
                    'variants' => $variants,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $products,
                    'pagination' => [
                        'has_next_page' => $shopifyData['hasNextPage'],
                        'end_cursor' => $shopifyData['endCursor'],
                    ],
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set or update threshold for a product variant.
     */
    public function setThreshold(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'variant_id' => 'nullable|string',
            'threshold_quantity' => 'required|integer|min:0',
            'product_title' => 'required|string',
            'variant_title' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $session = $request->get('shopifySession');
            $shopDomain = $session->getShop();
            
            $shopifyService = new ShopifyProductService($session);

            // Get current inventory level from Shopify
            $currentInventory = $shopifyService->getInventoryLevel(
                $request->product_id,
                $request->variant_id
            );

            // Log if inventory fetch failed
            if ($currentInventory === null) {
                Log::warning("Failed to fetch current inventory for product {$request->product_id}, variant {$request->variant_id}. Using 0 as fallback.");
                $currentInventory = 0;
            } else {
                Log::info("Successfully fetched current inventory: {$currentInventory} for product {$request->product_id}, variant {$request->variant_id}");
            }

            $productThreshold = ProductThreshold::updateOrCreate(
                [
                    'shop_domain' => $shopDomain,
                    'shopify_product_id' => $request->product_id,
                    'shopify_variant_id' => $request->variant_id,
                ],
                [
                    'product_title' => $request->product_title,
                    'variant_title' => $request->variant_title,
                    'threshold_quantity' => $request->threshold_quantity,
                    'current_inventory' => $currentInventory,
                    'last_checked_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Threshold updated successfully',
                'data' => $productThreshold,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to set threshold: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set threshold',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle alerts for a product variant.
     */
    public function toggleAlerts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'variant_id' => 'nullable|string',
            'alerts_enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $session = $request->get('shopifySession');
            $shopDomain = $session->getShop();

            $productThreshold = ProductThreshold::where('shop_domain', $shopDomain)
                ->where('shopify_product_id', $request->product_id)
                ->where('shopify_variant_id', $request->variant_id)
                ->first();

            if (!$productThreshold) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product threshold not found',
                ], 404);
            }

            $productThreshold->update([
                'alerts_enabled' => $request->alerts_enabled,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alert settings updated successfully',
                'data' => $productThreshold,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to toggle alerts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle alerts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update inventory levels for all tracked products.
     */
    public function updateInventory(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');
            $shopDomain = $session->getShop();
            
            $shopifyService = new ShopifyProductService($session);
            $updatedCount = $shopifyService->updateInventoryLevels($shopDomain);

            return response()->json([
                'success' => true,
                'message' => "Updated inventory for {$updatedCount} products",
                'updated_count' => $updatedCount,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update inventory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update inventory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get products that are below their thresholds.
     * Updates inventory from Shopify first to ensure accurate data.
     */
    public function getBelowThreshold(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');
            $shopDomain = $session->getShop();

            // First, update inventory levels from Shopify to ensure accuracy
            $shopifyService = new ShopifyProductService($session);
            $shopifyService->updateInventoryLevels($shopDomain);

            $belowThresholdProducts = ProductThreshold::forShop($shopDomain)
                ->withAlertsEnabled()
                ->belowThreshold()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $belowThresholdProducts,
                'count' => $belowThresholdProducts->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get below threshold products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get below threshold products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
