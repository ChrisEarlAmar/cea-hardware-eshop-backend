<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stripe (like any real webhook sender) delivers at-least-once, not
        // exactly-once - a timeout or a 5xx makes it retry the same event.
        // The unique constraint on event_id is what turns that at-least-
        // once delivery into an effectively-once state transition.
        Schema::create('processed_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('stripe');
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->timestamp('processed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhook_events');
    }
};
