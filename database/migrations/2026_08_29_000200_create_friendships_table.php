<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->timestamps();
        });

        // One row per pair regardless of direction (ARCHITECTURE.md §4) — a reverse
        // request while Pending hits the same row instead of creating a second one.
        DB::statement(
            'CREATE UNIQUE INDEX friendships_pair_unique ON friendships (LEAST(requester_id, recipient_id), GREATEST(requester_id, recipient_id))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
