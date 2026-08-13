<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100800_create_shift_rotations_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-522 (Feature 103): Rollpläne — mehrwöchige rollierende Dienstrhythmen,
 * die sich selbst fortschreiben (Q1-Konzept „Rollplan"). Ein Rhythmus besteht
 * aus Wochen × Wochentagen mit Schichttyp-Bezug; Zuweisungen verankern den
 * Rhythmus je Mitarbeiter an einem Referenz-Montag. Der Roller erzeugt daraus
 * Draft-Dienste; manuelle Planung und genehmigte Abwesenheiten gewinnen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('shift_rotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('weeks_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'srot_org_active_idx');
        });

        Schema::create('shift_rotation_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_rotation_id')->constrained('shift_rotations', indexName: 'srote_rotation_fk')->cascadeOnDelete();
            $table->unsignedTinyInteger('week_index');
            $table->unsignedTinyInteger('iso_weekday');
            $table->foreignId('shift_type_id')->constrained('shift_types', indexName: 'srote_shift_type_fk')->cascadeOnDelete();

            $table->unique(['shift_rotation_id', 'week_index', 'iso_weekday'], 'srote_slot_unique');
        });

        Schema::create('shift_rotation_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained(indexName: 'srota_org_fk')->cascadeOnDelete();
            $table->foreignId('shift_rotation_id')->constrained('shift_rotations', indexName: 'srota_rotation_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'srota_user_fk')->cascadeOnDelete();
            $table->date('anchor_date');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id'], 'srota_org_user_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shift_rotation_assignments');
        Schema::dropIfExists('shift_rotation_entries');
        Schema::dropIfExists('shift_rotations');
    }
};
