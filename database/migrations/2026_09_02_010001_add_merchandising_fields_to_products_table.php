<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('image_url');
            // Null means not on sale. When set, PricingEngine applies it on
            // top of whatever tier price would otherwise win - never on top
            // of an account's own negotiated price, which is already a
            // deliberate override outright (see PricingEngine::priceFor).
            $table->decimal('sale_discount_percent', 5, 2)->nullable()->after('is_featured');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_featured', 'sale_discount_percent', 'sale_ends_at']);
        });
    }
};
