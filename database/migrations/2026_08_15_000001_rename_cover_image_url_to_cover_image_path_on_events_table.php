<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('cover_image_url', 'cover_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('cover_image_path', 'cover_image_url');
        });
    }
};
