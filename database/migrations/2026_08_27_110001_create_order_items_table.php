<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // restrict, not cascade: a variant should never disappear out
            // from under a historical order line just because the product
            // was later deleted from the catalog.
            $table->foreignId('product_variant_id')->constrained();
            // Snapshotted at purchase time - these must never change even
            // if the catalog's price or SKU changes later, so a past order
            // still shows exactly what was actually bought.
            $table->string('sku');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
