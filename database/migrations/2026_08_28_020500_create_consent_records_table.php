<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->string('consentable_type');
            $table->unsignedBigInteger('consentable_id');
            $table->string('consent_type');
            $table->string('policy_version');
            $table->timestamp('accepted_at');

            $table->index(['consentable_type', 'consentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
