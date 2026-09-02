<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            // A guest checkout has no user_id - MySQL's unique index on
            // (key, user_id) treats every NULL as distinct, so it can never
            // catch a guest reusing a key. This second unique index on
            // (key, guest_token) is what actually enforces guest-side
            // idempotency; the two indexes never fight because a row only
            // ever has one of the two identities set.
            $table->string('guest_token')->nullable()->after('user_id');
            $table->unique(['key', 'guest_token']);
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['key', 'guest_token']);
            $table->dropColumn('guest_token');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
