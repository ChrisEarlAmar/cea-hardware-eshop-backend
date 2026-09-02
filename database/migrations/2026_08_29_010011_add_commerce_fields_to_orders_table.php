<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('guest_name')->nullable()->after('guest_email');
            $table->foreignId('shipping_address_id')->nullable()->after('guest_name')
                ->constrained('addresses')->nullOnDelete();
            $table->foreignId('billing_address_id')->nullable()->after('shipping_address_id')
                ->constrained('addresses')->nullOnDelete();
            $table->string('tracking_carrier')->nullable()->after('billing_address_id');
            $table->string('tracking_number')->nullable()->after('tracking_carrier');
            $table->string('customer_note')->nullable()->after('tracking_number');
            $table->string('po_reference')->nullable()->after('customer_note');
            $table->foreignId('coupon_id')->nullable()->after('po_reference')
                ->constrained('coupons')->nullOnDelete();
            $table->decimal('discount_total', 10, 2)->default('0.00')->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_address_id');
            $table->dropConstrainedForeignId('billing_address_id');
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn([
                'guest_email', 'guest_name', 'tracking_carrier', 'tracking_number',
                'customer_note', 'po_reference', 'discount_total',
            ]);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
