<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100500_create_club_notifications_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zustellprotokoll der Vereinsnachrichten (Feature 159, MVP-845): je Mitglied,
 * Anlass und Empfänger genau ein Eintrag — Wiederholungen versenden nicht
 * doppelt, Zustellfehler bleiben sichtbar, Gelesen-Status gibt es bewusst nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('kind', 32); // reminder|rescheduled|cancelled|promoted
            // Anlass-Schlüssel (Art, Termin, Mitglied, Fingerabdruck der Änderung) — mit Empfänger eindeutig.
            $table->string('dedupe_key', 191);
            $table->string('recipient', 191); // user:<id> | mail:<adresse>
            $table->string('status', 16); // sent|failed
            $table->string('error', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['dedupe_key', 'recipient'], 'club_notif_key_recipient_uq');
            $table->index(['club_member_id', 'created_at'], 'club_notif_member_created_idx');
            $table->index(['event_id', 'status'], 'club_notif_event_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_notifications');
    }
};
