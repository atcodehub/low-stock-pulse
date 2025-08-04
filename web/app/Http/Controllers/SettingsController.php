<?php

namespace App\Http\Controllers;

use App\Models\AlertSetting;
use App\Models\ActivityLog;
use App\Models\ProductThreshold;
use App\Services\EmailNotificationService;
use App\Services\ShopifyEmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get alert settings for the shop.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');
            $shopDomain = $session->getShop();

            $alertSettings = AlertSetting::getOrCreateForShop($shopDomain);

            return response()->json([
                'success' => true,
                'data' => $alertSettings,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch alert settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch alert settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update alert settings for the shop.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'alert_email' => 'nullable|email',
            'alert_frequency' => 'required|in:instant,daily,weekly',
            'notifications_enabled' => 'required|boolean',
            'daily_alert_time' => 'nullable|date_format:H:i:s',
            'weekly_alert_day' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'email_template_settings' => 'nullable|array',
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

            $alertSettings = AlertSetting::getOrCreateForShop($shopDomain);
            
            $updateData = $request->only([
                'alert_email',
                'alert_frequency',
                'notifications_enabled',
                'email_template_settings',
            ]);

            if ($request->has('daily_alert_time')) {
                $updateData['daily_alert_time'] = $request->daily_alert_time;
            }

            if ($request->has('weekly_alert_day')) {
                $updateData['weekly_alert_day'] = $request->weekly_alert_day;
            }

            $alertSettings->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $alertSettings->fresh(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update alert settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update alert settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get activity logs for the shop.
     */
    public function getActivityLogs(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');
            $shopDomain = $session->getShop();

            $limit = min($request->get('limit', 10), 50);
            $page = $request->get('page', 1);

            $activityLogs = ActivityLog::forShop($shopDomain)
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $activityLogs->items(),
                'pagination' => [
                    'current_page' => $activityLogs->currentPage(),
                    'last_page' => $activityLogs->lastPage(),
                    'per_page' => $activityLogs->perPage(),
                    'total' => $activityLogs->total(),
                    'has_more_pages' => $activityLogs->hasMorePages(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch activity logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activity logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent activity logs (last 10).
     */
    public function getRecentActivityLogs(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Shopify session found',
                ], 401);
            }

            $shopDomain = $session->getShop();
            Log::info("Fetching recent activity logs for shop: {$shopDomain}");

            $recentLogs = ActivityLog::forShop($shopDomain)
                ->recent(10)
                ->get();

            Log::info("Found {$recentLogs->count()} recent logs for shop: {$shopDomain}");

            return response()->json([
                'success' => true,
                'data' => $recentLogs,
                'count' => $recentLogs->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch recent activity logs: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent activity logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test email configuration by sending a test email.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'test_email' => 'required|email',
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

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Shopify session found',
                ], 401);
            }

            $shopifyEmailService = new ShopifyEmailService();
            $result = $shopifyEmailService->sendTestEmail($session, $request->test_email);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'shop_info' => $result['shop_info'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send test email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Shopify session found',
                ], 401);
            }

            $shopDomain = $session->getShop();
            Log::info("Fetching dashboard stats for shop: {$shopDomain}");

            $alertSettings = AlertSetting::getOrCreateForShop($shopDomain);
            Log::info("Alert settings retrieved for shop: {$shopDomain}");

            // Get statistics using direct queries to avoid relationship issues
            $totalProducts = ProductThreshold::forShop($shopDomain)->count();
            $activeAlerts = ProductThreshold::forShop($shopDomain)->withAlertsEnabled()->count();
            $belowThreshold = ProductThreshold::forShop($shopDomain)
                ->withAlertsEnabled()
                ->belowThreshold()
                ->count();

            $recentAlerts = ActivityLog::forShop($shopDomain)
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            $lastEmailSent = ActivityLog::forShop($shopDomain)
                ->successful()
                ->orderBy('created_at', 'desc')
                ->first();

            Log::info("Dashboard stats calculated successfully for shop: {$shopDomain}");

            return response()->json([
                'success' => true,
                'data' => [
                    'total_products_tracked' => $totalProducts,
                    'active_alerts' => $activeAlerts,
                    'products_below_threshold' => $belowThreshold,
                    'alerts_sent_last_7_days' => $recentAlerts,
                    'last_email_sent_at' => $lastEmailSent ? $lastEmailSent->created_at : null,
                    'notifications_enabled' => $alertSettings->notifications_enabled,
                    'alert_frequency' => $alertSettings->alert_frequency,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch dashboard stats: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get email service recommendations for the shop.
     */
    public function getEmailServiceInfo(Request $request): JsonResponse
    {
        try {
            $session = $request->get('shopifySession');

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Shopify session found',
                ], 401);
            }

            $shopifyEmailService = new ShopifyEmailService();
            $shopInfo = $shopifyEmailService->getShopInfo($session);
            $canSendEmails = $shopifyEmailService->canSendEmails($session);
            $recommendedService = $shopifyEmailService->getRecommendedEmailService($session);

            return response()->json([
                'success' => true,
                'data' => [
                    'shop_info' => $shopInfo,
                    'can_send_emails' => $canSendEmails,
                    'recommended_service' => $recommendedService,
                    'current_setup' => 'Shopify-Integrated Email Service',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get email service info: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get email service info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
