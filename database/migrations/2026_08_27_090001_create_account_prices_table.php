<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            // A negotiated price that overrides quantity-tier pricing
            // entirely when it applies - it does not stack with tiers.
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->unique(['account_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_prices');
    }
};
