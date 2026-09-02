<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('purpose');
            $table->string('identifier');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            // Unique (not just indexed) — issue() upserts on this pair, and
            // it rules out ever having two live codes for the same purpose
            // + identifier (see OtpAdapter's review-fixed race condition).
            $table->unique(['purpose', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
