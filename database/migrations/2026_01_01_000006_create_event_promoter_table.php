<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot even though MVP UI shows one promoter per event — PRD §37 OPEN-01 warns
        // against a hard single-promoter constraint that would be expensive to relax later.
        Schema::create('event_promoter', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('promoter_id')->constrained('promoters')->cascadeOnDelete();
            $table->primary(['event_id', 'promoter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_promoter');
    }
};
