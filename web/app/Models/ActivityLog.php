<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_domain',
        'shopify_product_id',
        'shopify_variant_id',
        'product_title',
        'variant_title',
        'current_quantity',
        'threshold_quantity',
        'alert_email',
        'alert_type',
        'email_sent_successfully',
        'email_error_message',
        'email_data',
    ];

    protected $casts = [
        'current_quantity' => 'integer',
        'threshold_quantity' => 'integer',
        'email_sent_successfully' => 'boolean',
        'email_data' => 'array',
    ];

    /**
     * Get the product threshold that this log relates to.
     */
    public function productThreshold(): BelongsTo
    {
        return $this->belongsTo(ProductThreshold::class, 'shopify_product_id', 'shopify_product_id')
            ->where('shop_domain', $this->shop_domain);
    }

    /**
     * Get the alert settings for this shop.
     */
    public function alertSetting(): BelongsTo
    {
        return $this->belongsTo(AlertSetting::class, 'shop_domain', 'shop_domain');
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
     * Get a formatted status message.
     */
    public function getStatusMessageAttribute(): string
    {
        if ($this->email_sent_successfully) {
            return 'Email sent successfully';
        }
        
        return 'Email failed: ' . ($this->email_error_message ?? 'Unknown error');
    }

    /**
     * Scope to get logs for a specific shop.
     */
    public function scopeForShop($query, string $shopDomain)
    {
        return $query->where('shop_domain', $shopDomain);
    }

    /**
     * Scope to get successful email logs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('email_sent_successfully', true);
    }

    /**
     * Scope to get failed email logs.
     */
    public function scopeFailed($query)
    {
        return $query->where('email_sent_successfully', false);
    }

    /**
     * Scope to get recent logs.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Create a new activity log entry.
     */
    public static function createLog(
        string $shopDomain,
        ProductThreshold $productThreshold,
        string $alertEmail,
        string $alertType,
        bool $emailSent = false,
        ?string $errorMessage = null,
        ?array $emailData = null
    ): self {
        return self::create([
            'shop_domain' => $shopDomain,
            'shopify_product_id' => $productThreshold->shopify_product_id,
            'shopify_variant_id' => $productThreshold->shopify_variant_id,
            'product_title' => $productThreshold->product_title,
            'variant_title' => $productThreshold->variant_title,
            'current_quantity' => $productThreshold->current_inventory,
            'threshold_quantity' => $productThreshold->threshold_quantity,
            'alert_email' => $alertEmail,
            'alert_type' => $alertType,
            'email_sent_successfully' => $emailSent,
            'email_error_message' => $errorMessage,
            'email_data' => $emailData,
        ]);
    }
}
