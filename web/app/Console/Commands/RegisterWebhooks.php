<?php

namespace App\Console\Commands;

use App\Models\Session;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Shopify\Clients\Rest;
use Shopify\Auth\Session as ShopifySession;

class RegisterWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'webhooks:register {--shop=} {--all} {--include-orders}';

    /**
     * The console command description.
     */
    protected $description = 'Register Low Stock Pulse webhooks with Shopify';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $specificShop = $this->option('shop');
            $registerAll = $this->option('all');

            if ($specificShop) {
                $this->info("Registering webhooks for shop: {$specificShop}");
                $this->registerWebhooksForShop($specificShop);
            } elseif ($registerAll) {
                $this->info("Registering webhooks for all shops");
                $this->registerWebhooksForAllShops();
            } else {
                $this->error("Please specify --shop=<shop-domain> or --all");
                return Command::FAILURE;
            }

            $this->info("Webhook registration completed successfully");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to register webhooks: " . $e->getMessage());
            Log::error("RegisterWebhooks command failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Register webhooks for all shops.
     */
    private function registerWebhooksForAllShops(): void
    {
        $sessions = Session::all();
        
        $this->info("Found " . $sessions->count() . " shops");

        foreach ($sessions as $session) {
            $this->registerWebhooksForShop($session->shop);
        }
    }

    /**
     * Register webhooks for a specific shop.
     */
    private function registerWebhooksForShop(string $shopDomain): void
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

            $client = new Rest($shopDomain, $session->access_token);

            // Get the app URL
            $appUrl = config('app.url');
            
            // Define webhooks to register
            $webhooks = [
                [
                    'topic' => 'inventory_levels/update',
                    'address' => "{$appUrl}/api/webhooks/inventory/update",
                    'format' => 'json'
                ],
                [
                    'topic' => 'products/update',
                    'address' => "{$appUrl}/api/webhooks/products/update",
                    'format' => 'json'
                ]
            ];

            // Only add orders webhook if we have permission (for production)
            if ($this->option('include-orders')) {
                $webhooks[] = [
                    'topic' => 'orders/create',
                    'address' => "{$appUrl}/api/webhooks/orders/create",
                    'format' => 'json'
                ];
            }

            // First, get existing webhooks to avoid duplicates
            $existingWebhooks = $this->getExistingWebhooks($client);
            $existingTopics = array_column($existingWebhooks, 'topic');

            foreach ($webhooks as $webhook) {
                if (in_array($webhook['topic'], $existingTopics)) {
                    $this->info("Webhook {$webhook['topic']} already exists for {$shopDomain}");
                    continue;
                }

                $response = $client->post('webhooks', [
                    'webhook' => $webhook
                ]);

                if ($response->getStatusCode() === 201) {
                    $this->info("✅ Registered {$webhook['topic']} webhook for {$shopDomain}");
                } else {
                    $this->error("❌ Failed to register {$webhook['topic']} webhook for {$shopDomain}");
                    $this->error("Response: " . $response->getBody());
                }
            }

        } catch (\Exception $e) {
            $this->error("Failed to register webhooks for shop {$shopDomain}: " . $e->getMessage());
            Log::error("Failed to register webhooks for shop {$shopDomain}: " . $e->getMessage());
        }
    }

    /**
     * Get existing webhooks for a shop.
     */
    private function getExistingWebhooks(Rest $client): array
    {
        try {
            $response = $client->get('webhooks');
            
            if ($response->getStatusCode() === 200) {
                $body = $response->getDecodedBody();
                return $body['webhooks'] ?? [];
            }
            
            return [];
            
        } catch (\Exception $e) {
            Log::error("Failed to get existing webhooks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * List existing webhooks for a shop.
     */
    public function listWebhooks(string $shopDomain): void
    {
        try {
            $session = Session::where('shop', $shopDomain)->first();

            if (!$session) {
                $this->error("No session found for shop: {$shopDomain}");
                return;
            }

            $client = new Rest($shopDomain, $session->access_token);
            $webhooks = $this->getExistingWebhooks($client);

            $this->info("Existing webhooks for {$shopDomain}:");
            
            if (empty($webhooks)) {
                $this->info("No webhooks found");
                return;
            }

            foreach ($webhooks as $webhook) {
                $this->info("- {$webhook['topic']} -> {$webhook['address']}");
            }

        } catch (\Exception $e) {
            $this->error("Failed to list webhooks: " . $e->getMessage());
        }
    }
}
