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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain');
            $table->string('shopify_product_id');
            $table->string('shopify_variant_id')->nullable();
            $table->string('product_title');
            $table->string('variant_title')->nullable();
            $table->integer('current_quantity');
            $table->integer('threshold_quantity');
            $table->string('alert_email');
            $table->enum('alert_type', ['instant', 'daily_batch', 'weekly_batch']);
            $table->boolean('email_sent_successfully')->default(false);
            $table->text('email_error_message')->nullable();
            $table->json('email_data')->nullable(); // Store email content for reference
            $table->timestamps();

            // Indexes for performance
            $table->index(['shop_domain', 'created_at']);
            $table->index('created_at');
            $table->index(['shop_domain', 'email_sent_successfully']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
