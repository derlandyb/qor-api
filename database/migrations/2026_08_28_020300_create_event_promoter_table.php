<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_promoter', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('promoter_id')->constrained('promoters')->cascadeOnDelete();
            $table->timestamp('tagged_at');

            $table->primary(['event_id', 'promoter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_promoter');
    }
};
