<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('gift_card_id')->nullable()->after('coupon_id')
                ->constrained('gift_cards')->nullOnDelete();
            $table->decimal('gift_card_amount', 10, 2)->default('0.00')->after('gift_card_id');
            // Redeemed at checkout time (deducted from the buyer's balance
            // immediately, mirroring how a coupon's used_count is
            // incremented immediately) - refunded back to the buyer if the
            // order is later cancelled, the same way stock is restored.
            $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('gift_card_amount');
            // Only set once the order is actually paid - see
            // OrderService::transitionTo - never at creation time, since a
            // never-paid order shouldn't earn anything.
            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('loyalty_points_redeemed');
            $table->decimal('tax_total', 10, 2)->default('0.00')->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_card_id');
            $table->dropColumn(['gift_card_amount', 'loyalty_points_redeemed', 'loyalty_points_earned', 'tax_total']);
        });
    }
};
