<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('created_by_type');
            $table->unsignedBigInteger('created_by_id');
            $table->string('title');
            $table->text('description');
            $table->string('cover_image_url')->nullable();
            $table->timestamp('starts_at');
            $table->string('city');
            $table->foreignId('genre_id')->constrained('genres');
            $table->string('address')->nullable();
            $table->boolean('is_free')->default(false);
            $table->string('ticket_url')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('age_rating')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->text('rejection_feedback')->nullable();
            $table->timestamps();

            $table->index(['created_by_type', 'created_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
