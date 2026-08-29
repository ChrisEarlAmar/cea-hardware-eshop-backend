<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synonyms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            // Stored pre-normalized (lowercase, single-spaced) so lookup is
            // a plain equality check against the same normalization applied
            // to a search query - e.g. "ss" or "20mm" -> a real attribute
            // value like "Stainless Steel" or "20".
            $table->string('term');
            $table->string('value');
            $table->timestamps();

            $table->unique(['attribute_id', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synonyms');
    }
};
