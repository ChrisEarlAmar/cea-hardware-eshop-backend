<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            // Stored as plain text regardless of the attribute's data_type -
            // deliberately not split into typed columns; that's more EAV
            // machinery than a single-currency, non-faceted-search catalog
            // needs right now.
            $table->string('value');
            $table->timestamps();

            $table->unique(['product_variant_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
    }
};
