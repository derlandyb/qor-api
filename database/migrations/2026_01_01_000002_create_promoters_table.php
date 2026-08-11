<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('social_links')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoters');
    }
};
