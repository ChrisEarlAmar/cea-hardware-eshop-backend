<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set the first time a user starts saving a card (see
            // PaymentService::getOrCreateStripeCustomer) - null for every
            // user who has never gone through that flow.
            $table->string('stripe_customer_id')->nullable()->after('loyalty_points_balance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
};
