<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            // Cliff pricing: once quantity reaches min_quantity, this price
            // applies to every unit in the line, not just the units above
            // the threshold.
            $table->unsignedInteger('min_quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->unique(['product_variant_id', 'min_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_tiers');
    }
};
