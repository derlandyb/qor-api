<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('city'); // FILTER-002/008
        });

        // GIN indexing (and the jsonb `?|` operator used by scopeGenres()) is Postgres-only;
        // the SQLite-backed Unit/Feature suites don't need it.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX events_genres_gin ON events USING GIN (genres)'); // FILTER-003 ?| lookups
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS events_genres_gin');
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['city']);
        });
    }
};
