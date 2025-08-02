<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_domain',
        'alert_email',
        'alert_frequency',
        'notifications_enabled',
        'email_template_settings',
        'daily_alert_time',
        'weekly_alert_day',
        'last_daily_alert_sent',
        'last_weekly_alert_sent',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'email_template_settings' => 'array',
        'daily_alert_time' => 'datetime:H:i:s',
        'last_daily_alert_sent' => 'datetime',
        'last_weekly_alert_sent' => 'datetime',
    ];

    /**
     * Get the product thresholds for this shop.
     */
    public function productThresholds(): HasMany
    {
        return $this->hasMany(ProductThreshold::class, 'shop_domain', 'shop_domain');
    }

    /**
     * Get the activity logs for this shop.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'shop_domain', 'shop_domain');
    }

    /**
     * Get the recent activity logs (last 10).
     */
    public function recentActivityLogs()
    {
        return $this->activityLogs()
            ->orderBy('created_at', 'desc')
            ->limit(10);
    }

    /**
     * Check if daily alerts should be sent now.
     */
    public function shouldSendDailyAlert(): bool
    {
        if ($this->alert_frequency !== 'daily' || !$this->notifications_enabled) {
            return false;
        }

        $now = now();
        $alertTime = $now->copy()->setTimeFromTimeString($this->daily_alert_time);
        
        // Check if we're within 5 minutes of the alert time
        $timeDiff = abs($now->diffInMinutes($alertTime));
        
        // Check if we haven't sent an alert today
        $lastSent = $this->last_daily_alert_sent;
        $sentToday = $lastSent && $lastSent->isToday();
        
        return $timeDiff <= 5 && !$sentToday;
    }

    /**
     * Check if weekly alerts should be sent now.
     */
    public function shouldSendWeeklyAlert(): bool
    {
        if ($this->alert_frequency !== 'weekly' || !$this->notifications_enabled) {
            return false;
        }

        $now = now();
        $isCorrectDay = strtolower($now->format('l')) === $this->weekly_alert_day;
        
        if (!$isCorrectDay) {
            return false;
        }

        $alertTime = $now->copy()->setTimeFromTimeString($this->daily_alert_time);
        $timeDiff = abs($now->diffInMinutes($alertTime));
        
        // Check if we haven't sent an alert this week
        $lastSent = $this->last_weekly_alert_sent;
        $sentThisWeek = $lastSent && $lastSent->isCurrentWeek();
        
        return $timeDiff <= 5 && !$sentThisWeek;
    }

    /**
     * Get or create alert settings for a shop.
     */
    public static function getOrCreateForShop(string $shopDomain): self
    {
        return self::firstOrCreate(
            ['shop_domain' => $shopDomain],
            [
                'alert_frequency' => 'daily',
                'notifications_enabled' => true,
                'daily_alert_time' => '09:00:00',
                'weekly_alert_day' => 'monday',
            ]
        );
    }
}
