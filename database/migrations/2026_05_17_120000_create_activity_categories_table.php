<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_120000_create_activity_categories_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog of non-project activity categories (administration, training,
 * meetings, internal work, paid absences, on-call standby, etc.).
 *
 * A TimeEntry may be linked to either a project_id (project work) OR an
 * activity_category_id (non-project work). The category's `activity_type`
 * is mirrored onto the time entry for fast reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            // Machine-readable key, unique per organization.
            $table->string('key', 64);
            $table->string('label');
            // admin | training | meeting | internal | travel | break | absence | standby | other
            $table->string('activity_type', 32);
            $table->boolean('billable_default')->default(false);
            $table->boolean('counts_as_work')->default(true);
            $table->string('color', 16)->nullable();
            $table->string('icon', 32)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'active']);
            $table->index('activity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_categories');
    }
};
