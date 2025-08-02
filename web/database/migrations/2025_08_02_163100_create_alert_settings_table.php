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
        Schema::create('alert_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain')->unique();
            $table->string('alert_email')->nullable(); // Custom email, defaults to shop owner email
            $table->enum('alert_frequency', ['instant', 'daily', 'weekly'])->default('daily');
            $table->boolean('notifications_enabled')->default(true);
            $table->json('email_template_settings')->nullable(); // Store custom template preferences
            $table->time('daily_alert_time')->default('09:00:00'); // Time for daily alerts
            $table->enum('weekly_alert_day', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->default('monday');
            $table->timestamp('last_daily_alert_sent')->nullable();
            $table->timestamp('last_weekly_alert_sent')->nullable();
            $table->timestamps();

            $table->index('shop_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_settings');
    }
};
