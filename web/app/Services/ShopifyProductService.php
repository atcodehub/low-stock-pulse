<?php

namespace App\Services;

use App\Models\ProductThreshold;
use Shopify\Auth\Session;
use Shopify\Clients\Graphql;
use Shopify\Clients\Rest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ShopifyProductService
{
    private Session $session;
    private Graphql $graphqlClient;
    private Rest $restClient;

    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->graphqlClient = new Graphql($session->getShop(), $session->getAccessToken());
        $this->restClient = new Rest($session->getShop(), $session->getAccessToken());
    }

    /**
     * Get all products with their variants and inventory levels.
     */
    public function getAllProductsWithInventory(int $limit = 50, ?string $cursor = null): array
    {
        $query = $this->buildProductsQuery();
        
        $variables = [
            'first' => $limit,
        ];
        
        if ($cursor) {
            $variables['after'] = $cursor;
        }

        try {
            $response = $this->graphqlClient->query([
                'query' => $query,
                'variables' => $variables,
            ]);

            $body = $response->getDecodedBody();
            
            if (isset($body['errors'])) {
                Log::error('Shopify GraphQL errors:', $body['errors']);
                throw new \Exception('GraphQL query failed: ' . json_encode($body['errors']));
            }

            return $this->processProductsResponse($body['data']['products']);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch products from Shopify: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get a specific product with its variants and inventory.
     */
    public function getProductById(string $productId): ?array
    {
        $query = $this->buildSingleProductQuery();
        
        try {
            $response = $this->graphqlClient->query([
                'query' => $query,
                'variables' => ['id' => "gid://shopify/Product/{$productId}"],
            ]);

            $body = $response->getDecodedBody();
            
            if (isset($body['errors'])) {
                Log::error('Shopify GraphQL errors:', $body['errors']);
                return null;
            }

            $product = $body['data']['product'];
            return $product ? $this->processProductData($product) : null;
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch product from Shopify: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update inventory levels for tracked products.
     */
    public function updateInventoryLevels(string $shopDomain): int
    {
        $trackedProducts = ProductThreshold::forShop($shopDomain)->get();
        $updatedCount = 0;

        foreach ($trackedProducts as $productThreshold) {
            try {
                $inventoryLevel = $this->getInventoryLevel(
                    $productThreshold->shopify_product_id,
                    $productThreshold->shopify_variant_id
                );

                if ($inventoryLevel !== null) {
                    $productThreshold->update([
                        'current_inventory' => $inventoryLevel,
                        'last_checked_at' => now(),
                    ]);
                    $updatedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to update inventory for product {$productThreshold->shopify_product_id}: " . $e->getMessage());
            }
        }

        return $updatedCount;
    }

    /**
     * Get inventory level for a specific product variant.
     */
    public function getInventoryLevel(string $productId, ?string $variantId = null): ?int
    {
        try {
            if ($variantId) {
                // Query specific variant
                $query = '
                    query getVariantInventory($id: ID!) {
                        productVariant(id: $id) {
                            id
                            inventoryQuantity
                        }
                    }
                ';
                $id = "gid://shopify/ProductVariant/{$variantId}";

                $response = $this->graphqlClient->query([
                    'query' => $query,
                    'variables' => ['id' => $id],
                ]);

                $body = $response->getDecodedBody();

                if (isset($body['errors'])) {
                    Log::error('GraphQL errors in getInventoryLevel: ' . json_encode($body['errors']));
                    return null;
                }

                return $body['data']['productVariant']['inventoryQuantity'] ?? null;

            } else {
                // Query product's first variant
                $query = '
                    query getProductInventory($id: ID!) {
                        product(id: $id) {
                            variants(first: 1) {
                                edges {
                                    node {
                                        id
                                        inventoryQuantity
                                    }
                                }
                            }
                        }
                    }
                ';
                $id = "gid://shopify/Product/{$productId}";

                $response = $this->graphqlClient->query([
                    'query' => $query,
                    'variables' => ['id' => $id],
                ]);

                $body = $response->getDecodedBody();

                if (isset($body['errors'])) {
                    Log::error('GraphQL errors in getInventoryLevel: ' . json_encode($body['errors']));
                    return null;
                }

                $variants = $body['data']['product']['variants']['edges'] ?? [];
                return !empty($variants) ? $variants[0]['node']['inventoryQuantity'] : null;
            }

        } catch (\Exception $e) {
            Log::error("Failed to fetch inventory level for product {$productId}, variant {$variantId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the GraphQL query for fetching products.
     */
    private function buildProductsQuery(): string
    {
        return '
            query getProducts($first: Int!, $after: String) {
                products(first: $first, after: $after) {
                    edges {
                        node {
                            id
                            title
                            handle
                            status
                            variants(first: 10) {
                                edges {
                                    node {
                                        id
                                        title
                                        sku
                                        inventoryQuantity
                                        inventoryPolicy
                                    }
                                }
                            }
                        }
                        cursor
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';
    }

    /**
     * Build the GraphQL query for fetching a single product.
     */
    private function buildSingleProductQuery(): string
    {
        return '
            query getProduct($id: ID!) {
                product(id: $id) {
                    id
                    title
                    handle
                    status
                    variants(first: 10) {
                        edges {
                            node {
                                id
                                title
                                sku
                                inventoryQuantity
                                inventoryPolicy
                            }
                        }
                    }
                }
            }
        ';
    }



    /**
     * Process the products response from GraphQL.
     */
    private function processProductsResponse(array $productsData): array
    {
        $products = [];
        
        foreach ($productsData['edges'] as $edge) {
            $products[] = $this->processProductData($edge['node']);
        }

        return [
            'products' => $products,
            'hasNextPage' => $productsData['pageInfo']['hasNextPage'],
            'endCursor' => $productsData['pageInfo']['endCursor'] ?? null,
        ];
    }

    /**
     * Process individual product data.
     */
    private function processProductData(array $productData): array
    {
        $variants = [];
        
        foreach ($productData['variants']['edges'] as $variantEdge) {
            $variant = $variantEdge['node'];
            $variants[] = [
                'id' => str_replace('gid://shopify/ProductVariant/', '', $variant['id']),
                'title' => $variant['title'],
                'sku' => $variant['sku'],
                'inventory_quantity' => $variant['inventoryQuantity'] ?? 0,
                'inventory_policy' => $variant['inventoryPolicy'],
            ];
        }

        return [
            'id' => str_replace('gid://shopify/Product/', '', $productData['id']),
            'title' => $productData['title'],
            'handle' => $productData['handle'],
            'status' => $productData['status'],
            'variants' => $variants,
        ];
    }
}
