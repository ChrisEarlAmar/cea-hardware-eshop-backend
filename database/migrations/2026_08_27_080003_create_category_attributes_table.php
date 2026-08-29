<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            // Whether this attribute defines a purchasable variant axis (e.g.
            // Thread Size) versus a purely descriptive spec (e.g. Weight) -
            // scoped per-category since the same attribute could play either
            // role depending on the category.
            $table->boolean('is_variant_defining')->default(false);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['category_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attributes');
    }
};
