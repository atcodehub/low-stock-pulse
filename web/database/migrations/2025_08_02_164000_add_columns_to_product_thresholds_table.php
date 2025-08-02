<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_thresholds', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('product_thresholds', 'shop_domain')) {
                $table->string('shop_domain');
            }
            if (!Schema::hasColumn('product_thresholds', 'shopify_product_id')) {
                $table->string('shopify_product_id');
            }
            if (!Schema::hasColumn('product_thresholds', 'shopify_variant_id')) {
                $table->string('shopify_variant_id')->nullable();
            }
            if (!Schema::hasColumn('product_thresholds', 'product_title')) {
                $table->string('product_title');
            }
            if (!Schema::hasColumn('product_thresholds', 'variant_title')) {
                $table->string('variant_title')->nullable();
            }
            if (!Schema::hasColumn('product_thresholds', 'threshold_quantity')) {
                $table->integer('threshold_quantity')->default(0);
            }
            if (!Schema::hasColumn('product_thresholds', 'alerts_enabled')) {
                $table->boolean('alerts_enabled')->default(true);
            }
            if (!Schema::hasColumn('product_thresholds', 'current_inventory')) {
                $table->integer('current_inventory')->default(0);
            }
            if (!Schema::hasColumn('product_thresholds', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable();
            }
            if (!Schema::hasColumn('product_thresholds', 'last_alert_sent_at')) {
                $table->timestamp('last_alert_sent_at')->nullable();
            }
        });

        // Add indexes after adding columns
        Schema::table('product_thresholds', function (Blueprint $table) {
            try {
                $table->index(['shop_domain', 'shopify_product_id']);
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            try {
                $table->index(['shop_domain', 'alerts_enabled']);
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            try {
                $table->index('last_checked_at');
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_thresholds', function (Blueprint $table) {
            $table->dropIndex(['shop_domain', 'shopify_product_id']);
            $table->dropIndex(['shop_domain', 'alerts_enabled']);
            $table->dropIndex(['last_checked_at']);
            
            $table->dropColumn([
                'shop_domain',
                'shopify_product_id',
                'shopify_variant_id',
                'product_title',
                'variant_title',
                'threshold_quantity',
                'alerts_enabled',
                'current_inventory',
                'last_checked_at',
                'last_alert_sent_at',
            ]);
        });
    }
};
