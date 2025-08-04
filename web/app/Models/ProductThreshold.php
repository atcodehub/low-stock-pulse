<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
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
    ];

    protected $casts = [
        'threshold_quantity' => 'integer',
        'current_inventory' => 'integer',
        'alerts_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_alert_sent_at' => 'datetime',
    ];

    /**
     * Get the activity logs for this product threshold.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'shopify_product_id', 'shopify_product_id')
            ->where('shop_domain', $this->shop_domain);
    }

    /**
     * Check if the current inventory is below the threshold.
     */
    public function isBelowThreshold(): bool
    {
        return $this->current_inventory < $this->threshold_quantity;
    }

    /**
     * Check if alerts are enabled and inventory is below threshold.
     */
    public function shouldSendAlert(): bool
    {
        return $this->alerts_enabled && $this->isBelowThreshold();
    }

    /**
     * Get the display name for this product/variant.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->variant_title && $this->variant_title !== 'Default Title') {
            return $this->product_title . ' - ' . $this->variant_title;
        }
        
        return $this->product_title;
    }

    /**
     * Scope to get products for a specific shop.
     */
    public function scopeForShop($query, string $shopDomain)
    {
        return $query->where('shop_domain', $shopDomain);
    }

    /**
     * Scope to get products with alerts enabled.
     */
    public function scopeWithAlertsEnabled($query)
    {
        return $query->where('alerts_enabled', true);
    }

    /**
     * Scope to get products below threshold.
     */
    public function scopeBelowThreshold($query)
    {
        return $query->whereRaw('current_inventory < threshold_quantity');
    }
}
