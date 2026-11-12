<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_210000_create_learning_video_progress_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gesehener Anteil je Einschreibung und Video (Feature 149, MVP-1598).
 * Gespeichert wird nur die Summe — keine Abschnitte, keine Zeitpunkte.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_video_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->foreignId('learning_unit_id')->constrained('learning_units')->cascadeOnDelete();
            // Der Anhang ist der Schlüssel des Blocks: er bleibt gleich, wenn Blöcke umsortiert werden.
            $table->foreignId('attachment_id')->constrained('attachments')->cascadeOnDelete();
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedTinyInteger('watched_percent')->default(0);
            $table->timestamps();

            $table->unique(['learning_enrollment_id', 'attachment_id'], 'lrn_video_progress_enr_att_uq');
            $table->index(['learning_unit_id'], 'lrn_video_progress_unit_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_video_progress');
    }
};
