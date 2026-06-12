<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_110000_create_day_closures_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-015 — Tagesabschluss (siehe docs/tagesabschluss.md §3).
 *
 * Eine Zeile pro Mitarbeitenden × Kalendertag, angelegt beim ersten Öffnen
 * der Tagesabschluss-Seite. Hält den Tagesstatus (open → closed →
 * correction → open), wer wann abgeschlossen/wiedereröffnet hat sowie die
 * Stempel-Sperre nach Korrektur-Freigabe (§5 Schritt 5). Der Anzeige-Status
 * `locked` wird NICHT persistiert, sondern aus der Monatsfreigabe
 * (MVP-016) abgeleitet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('day_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->date('day');

            // open|closed|correction (locked wird abgeleitet, §3)
            $table->string('status', 20)->default('open');

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();

            // §5 Schritt 5: nach Korrektur-Freigabe bleiben Anwesenheits-
            // Stempel gesperrt (nur Buchungen änderbar) bis zum erneuten close.
            $table->boolean('attendance_locked')->default(false);

            $table->timestamps();

            // Kurze, explizite Index-Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['organization_id', 'user_id', 'day'], 'day_closures_org_user_day_unique');
            $table->index(['organization_id', 'status'], 'day_closures_status_idx');
            $table->index(['user_id', 'day'], 'day_closures_user_day_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('day_closures');
    }
};
