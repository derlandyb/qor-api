<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('address');
            $table->string('city');
            $table->string('contact_phone');
            $table->string('contact_email');
            $table->string('image_url')->nullable();
            $table->string('approval_status')->default('pending_approval');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
