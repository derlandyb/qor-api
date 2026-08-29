<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscribable_type');
            $table->unsignedBigInteger('subscribable_id');
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status');
            $table->string('billing_cycle')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->unsignedInteger('publishes_used_this_period')->default(0);
            $table->timestamps();

            $table->index(['subscribable_type', 'subscribable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
