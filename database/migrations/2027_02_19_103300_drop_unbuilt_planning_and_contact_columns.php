<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vollscan 2026-08-23, F13 (Rest, MVP-723): die zwei Kandidaten, die
 * `2027_02_19_100900_drop_unbuilt_reserve_columns` noch stehen ließ. Beide
 * fallen jetzt — je Spalte begründet:
 *
 * 1. `communication_note_participants.customer_contact_id` — die vermeintliche
 *    Schreibstelle (`CommunicationNoteService::syncParticipants()`) war tot:
 *    `CommunicationNoteController` validiert `participants.*` ohne diesen
 *    Schlüssel, `validate()` wirft ihn also vorher weg; das Formular hat kein
 *    Feld, kein anderer Aufrufer liefert Teilnehmer. Die Spalte war in JEDEM
 *    Datensatz NULL. Ein Ziel gibt es auch nicht: Kontaktpersonen liegen
 *    bewusst als ID-lose JSON-Liste `contact_persons` (MVP-707), ein
 *    Integer-FK kann darauf nicht zeigen. „Fertig bauen" wäre eine
 *    Produktwelle (contact_persons normalisieren) — dann entsteht die Spalte
 *    ohnehin neu, mit echtem FK.
 * 2. `diary_entries.planned_start_at/_end_at/_duration_min` — geschrieben vom
 *    `saving()`-Hook, aber repoweit NULL Lesestellen. Die Disposition (GapFill
 *    MVP-245, Konfliktprüfung, Kalender, Calendly, Plan/Ist-Reporting) nutzt
 *    ausnahmslos das parallel gebaute Feldset `scheduled_for` /
 *    `time_window_start|end` / `planned_at` / `planned_by_user_id` /
 *    `planned_minutes`. Reine Schreiblast bei jedem Save.
 *
 * Beide Spalten hängen in einem Index, den die Vorlage nicht abdeckt:
 * `comm_note_part_unique` wird auf die drei tragenden Spalten neu gesetzt,
 * `diary_lifecycle_status_idx` auf `(organization_id, status, scheduled_for)`
 * — gleicher Präfix, aber mit der Spalte, die die Disposition wirklich liest.
 */
return new class extends Migration {
    public function up(): void {
        // Index zuerst lösen: SQLite kann eine indizierte Spalte nicht droppen.
        Schema::table('communication_note_participants', function (Blueprint $table): void {
            $table->dropUnique('comm_note_part_unique');
        });
        Schema::table('communication_note_participants', function (Blueprint $table): void {
            $table->dropColumn('customer_contact_id');
        });
        Schema::table('communication_note_participants', function (Blueprint $table): void {
            $table->unique(['communication_note_id', 'user_id', 'name'], 'comm_note_part_unique');
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('diary_lifecycle_status_idx');
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropColumn(['planned_start_at', 'planned_end_at', 'planned_duration_min']);
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->index(['organization_id', 'status', 'scheduled_for'], 'diary_lifecycle_status_idx');
        });
    }

    public function down(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('diary_lifecycle_status_idx');
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->timestamp('planned_start_at')->nullable();
            $table->timestamp('planned_end_at')->nullable();
            $table->unsignedInteger('planned_duration_min')->nullable();
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->index(['organization_id', 'status', 'planned_start_at'], 'diary_lifecycle_status_idx');
        });

        Schema::table('communication_note_participants', function (Blueprint $table): void {
            $table->dropUnique('comm_note_part_unique');
        });
        Schema::table('communication_note_participants', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_contact_id')->nullable();
        });
        Schema::table('communication_note_participants', function (Blueprint $table): void {
            $table->unique(['communication_note_id', 'user_id', 'customer_contact_id', 'name'], 'comm_note_part_unique');
        });
    }
};
