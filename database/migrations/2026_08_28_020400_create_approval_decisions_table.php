<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decidable_type');
            $table->unsignedBigInteger('decidable_id');
            $table->string('outcome');
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->constrained('admin_users');
            $table->timestamp('decided_at');

            $table->index(['decidable_type', 'decidable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
