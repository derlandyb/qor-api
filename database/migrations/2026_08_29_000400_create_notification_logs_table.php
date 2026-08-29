<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('trigger_type');
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('channel');
            $table->timestamp('sent_at')->useCurrent();

            $table->index(['user_id', 'trigger_type', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
