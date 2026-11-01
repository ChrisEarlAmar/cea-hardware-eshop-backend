<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            // A US state/province code (e.g. "CA") - deliberately not a
            // full address-rules engine, just enough to charge something
            // plausible without an external tax API. No row for a state
            // means 0% tax, not an error.
            $table->string('state', 2)->unique();
            $table->decimal('rate_percent', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
