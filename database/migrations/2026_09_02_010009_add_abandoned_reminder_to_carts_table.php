<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Set the first (and only) time an abandoned-cart reminder goes
            // out for this cart, so the scheduled command never emails the
            // same stale cart twice. Cleared back to null if the cart gains
            // a new item afterward, so a customer who comes back, adds one
            // more thing, then goes quiet again is eligible for a reminder
            // on that new round of inactivity too.
            $table->timestamp('abandoned_reminder_sent_at')->nullable()->after('guest_token');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('abandoned_reminder_sent_at');
        });
    }
};
