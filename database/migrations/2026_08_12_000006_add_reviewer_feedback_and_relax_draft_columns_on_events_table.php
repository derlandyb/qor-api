<?php

use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Type;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // First-time-submission Changes Requested feedback only — EventRevision.reviewer_feedback
            // covers the edit-of-a-Published-event case separately (see event-publishing design.md).
            $table->text('reviewer_feedback')->nullable();
        });

        // PUBLISH-008: `action=draft` persists a real row with only `title` set — every other
        // column event-feed originally declared NOT NULL must tolerate a draft not having filled
        // them in yet. `city` is derived from `venue_id` (Event::booted()'s saving hook), so it
        // must relax the same way. Additive/reversible, and safe pre-launch: no rows exist yet.
        $this->registerTimestampTzDoctrineType();

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->change();
            $table->timestampTz('start_date_time')->nullable()->change();
            $table->string('city')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('reviewer_feedback');
        });

        $this->registerTimestampTzDoctrineType();

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable(false)->change();
            $table->timestampTz('start_date_time')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
        });
    }

    /**
     * doctrine/dbal (required by ->change()) doesn't ship a "timestamptz" Doctrine Type by that
     * name — Laravel's own timestampTz() Fluent type isn't one of DBAL's built-ins — so building
     * the target column definition for the ALTER throws "Unknown column type" without this.
     * Guarded by hasType() since a single sqlite-backed test process may run this migration
     * (via RefreshDatabase) more than once, and Type's registry is a global static.
     */
    private function registerTimestampTzDoctrineType(): void
    {
        if (! Type::hasType('timestamptz')) {
            Schema::getConnection()->registerDoctrineType(DateTimeType::class, 'timestamptz', 'datetime');
        }
    }
};
