<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_type'); // snapshot at submission time
            $table->foreignId('promoter_id')->nullable()->constrained('promoters')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->cascadeOnDelete();
            $table->string('business_name');
            $table->string('contact_email');
            $table->string('contact_phone', 50)->nullable();
            $table->string('social_link'); // Instagram or WhatsApp URL, no format validation
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending_review');
            $table->text('rejection_feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_applications');
    }
};
