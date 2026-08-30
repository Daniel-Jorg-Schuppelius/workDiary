<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104600_create_learning_time_sessions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lernzeit-Journal (Feature 149, MVP-749).
 *
 * § 12 Abs. 1 ArbSchG verlangt Unterweisungen „während ihrer Arbeitszeit";
 * angeordnete Fortbildung außerhalb der Arbeitszeit ist ebenfalls
 * Arbeitszeit. Deshalb wird jede Lernsitzung mit Beginn und Ende
 * aufgezeichnet und **eingeordnet**:
 *
 *  - `inside`  — die Zeit ist über die Anwesenheit bereits erfasst; es
 *                entsteht KEINE zweite Buchung (Doppelzählung wäre der
 *                naheliegendste und teuerste Fehler dieses Features).
 *  - `outside` — es entsteht eine Anwesenheitsspanne mit Quelle `learning`;
 *                damit greifen die vorhandenen ArbZG-Prüfungen
 *                (Ruhezeit, Höchstarbeitszeit, Nachtarbeit) ohne zweiten Guard.
 *
 * Das Journal ist zweckgebunden: Arbeitszeitnachweis und
 * Abschlusskriterium — kein Verhaltensprofil (Konzept, Abschnitt 26).
 * Deshalb append-only und ohne SoftDelete.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_time_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->foreignId('learning_unit_id')->nullable()->constrained('learning_units')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            // Aktive Sekunden ohne Leerlauf — die Zeit soll stimmen, nicht groß aussehen.
            $table->unsignedInteger('active_seconds')->default(0);
            $table->string('source', 10)->default('web'); // web|mobile|event
            // inside|outside|unknown — Ergebnis des LearningWorkTimeClassifier.
            $table->string('classification', 10)->nullable();
            // Erzeugter Zeitnachweis bei `outside` (keine zweite Nachweiswelt).
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'started_at'], 'lrn_time_org_user_idx');
            $table->index(['learning_enrollment_id', 'ended_at'], 'lrn_time_enr_open_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_time_sessions');
    }
};
