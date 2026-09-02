<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null means no limit (the default for an account owner or a
            // solo user) - only set for a member an owner has deliberately
            // capped.
            $table->unsignedInteger('spending_limit_cents')->nullable()->after('is_staff');
            $table->timestamp('deletion_requested_at')->nullable()->after('spending_limit_cents');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['spending_limit_cents', 'deletion_requested_at']);
        });
    }
};
