<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_17_100000_create_sync_commands_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotenz-Register für Offline-Sync-Befehle (Feature 035, Phase 1 —
 * offline-sync-architektur.md §3.2): Jeder von der Client-Outbox gesendete
 * Befehl trägt eine `client_uuid`; das Unique über (user, client_uuid)
 * verhindert Doppelausführung bei Wiederholung nach Verbindungsabbruch.
 * Ausführung + Registrierung laufen in EINER Transaktion — ein Crash davor
 * hinterlässt keine Zeile (Retry führt frisch aus), ein paralleler
 * Doppel-Submit rollt über die Unique-Verletzung zurück (⇒ duplicate).
 *
 * Additiv, org-gescopt. Kurze, explizite Index-/FK-Namen (MySQL 64-Zeichen-
 * Limit + DB-weite FK-Eindeutigkeit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sync_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Idempotenzschlüssel der Client-Outbox (UUID v4 vom Gerät).
            $table->uuid('client_uuid');

            // Befehlstyp (attendance.clock-in | attendance.clock-out | comment.diary …)
            $table->string('type', 64);

            // Eingegangener Befehls-Payload (ohne sensible Inhalte — Kommentartexte
            // liegen im Zielmodell; hier nur zur Diagnose des Ergebnisses).
            $table->json('payload')->nullable();

            // Ergebnis: applied | duplicate | conflict | rejected (+ Referenz/Fehler).
            $table->string('result_status', 16);
            $table->string('result_ref', 128)->nullable();
            $table->json('result_errors')->nullable();

            // Client-Zeitstempel der Offline-Erfassung (Diagnose Offline-Latenz).
            $table->timestamp('captured_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'client_uuid'], 'sync_cmd_user_uuid_uq');
            $table->index(['organization_id', 'created_at'], 'sync_cmd_org_created_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sync_commands');
    }
};
