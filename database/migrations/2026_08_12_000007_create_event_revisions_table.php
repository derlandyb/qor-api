<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_revisions', function (Blueprint $table) {
            $table->id();
            // unique = single-pending-edit, DB-enforced (PUBLISH-002 AC3).
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->timestampTz('start_date_time');
            $table->timestampTz('end_date_time')->nullable();
            $table->foreignId('venue_id')->constrained('venues');
            $table->decimal('price_min', 8, 2)->nullable();
            $table->decimal('price_max', 8, 2)->nullable();
            $table->boolean('is_free')->default(false);
            $table->char('currency', 3)->default('BRL');
            $table->string('age_rating')->nullable();
            $table->jsonb('genres')->nullable();
            $table->string('ticket_url')->nullable();
            $table->string('status')->default('pending_approval');
            $table->text('reviewer_feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_revisions');
    }
};
