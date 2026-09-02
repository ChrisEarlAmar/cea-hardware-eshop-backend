<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            // Positive or negative - a restock is +N, a shrinkage/damage
            // write-off is -N. Always paired with a reason so the ledger
            // reads as an accounting trail, not just a number that changed.
            $table->integer('delta');
            $table->string('reason');
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->integer('resulting_stock');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
