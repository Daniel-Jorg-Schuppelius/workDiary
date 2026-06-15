<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_280000_create_external_participants_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontextbezogene externe Einladungen (Feature 033): Subunternehmer, Prüfer,
 * Sachverständige werden befristet, login-frei und mit begrenzten Rechten an
 * ein Subject (DiaryEntry|Protocol|Document) beteiligt.
 *
 * Token-Muster analog ProtocolSignatureToken/IsmsAuditPackageToken: NUR der
 * SHA-256-Hash wird persistiert, der Klartext-Token wird einmalig angezeigt.
 *
 * Kurze, explizite Index-Namen (MySQL 64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('external_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->nullable();
            $table->string('party', 32)->default('other');
            $table->string('token_hash', 64)->unique('ext_part_token_hash_uq');
            $table->json('abilities');
            $table->timestamp('expires_at');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_access_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id'], 'ext_part_subject_idx');
            $table->index(['organization_id', 'expires_at'], 'ext_part_org_expires_idx');
        });

        // Append-only Nachweis aller externen Aktionen (Zugriff, Kommentar,
        // Upload, Bestätigung). Akteur ist der externe Name/Token, nicht ein
        // interner User — bewusst getrennt vom AuditLog (kein user_id-FK).
        Schema::create('external_participant_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_participant_id')
                ->constrained('external_participants')
                ->cascadeOnDelete();
            $table->string('event', 48);
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['external_participant_id', 'created_at'], 'ext_part_event_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('external_participant_events');
        Schema::dropIfExists('external_participants');
    }
};
