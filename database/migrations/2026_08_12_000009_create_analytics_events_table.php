<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 64);
            $table->jsonb('properties');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('client_timestamp'); // when the client captured it — may lag ingest time under queue-and-flush
            $table->timestamps(); // created_at = server ingest time
            $table->index('event_name');
            $table->index('client_timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
