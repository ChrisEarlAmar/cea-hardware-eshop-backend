<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A hash of the request's meaningful contents (e.g. cart line
            // items). If the same key ever arrives with a different
            // fingerprint, that's a client bug reusing a key across two
            // different requests, not a legitimate retry - rejected rather
            // than silently replaying the wrong stored response.
            $table->string('request_fingerprint');
            // Null while the first attempt for this key is still in
            // flight; a concurrent duplicate request sees a null response
            // and is told to retry shortly rather than racing the first.
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();

            $table->unique(['key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
