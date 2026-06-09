<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000021_create_privacy_mvp1_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Datenschutzmanagement MVP 1: Verzeichnis von Verarbeitungstaetigkeiten (VVT)
 * mit Versionierung sowie Betroffenenanfragen (DSR) mit per-Fall-Krypto und
 * append-only Event-Hash-Kette. Alle Tabellen sind organisationsgebunden.
 *
 * Kurze, explizite Index-/Unique-Namen wegen MySQL-Limit (64 Zeichen) bei den
 * langen Tabellennamen.
 */
return new class extends Migration {
    public function up(): void {
        // ── VVT-Kopf ────────────────────────────────────────────────────────
        Schema::create('privacy_processing_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('purpose')->nullable();
            $table->string('controller_role', 20)->default('controller');
            $table->string('area')->nullable();                 // Fachbereich
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('current_version_id')->nullable(); // bewusst ohne FK (zirkular)
            $table->date('review_due_at')->nullable();
            $table->boolean('dsfa_required')->default(false);
            $table->string('risk_level', 16)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('review_due_at');
        });

        // ── VVT-Versionen (Snapshot je Freigabe) ────────────────────────────
        Schema::create('privacy_processing_activity_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('privacy_processing_activities')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->json('payload');                            // Datenkategorien, Rechtsgrundlagen, Empfaenger, Transfers, Aufbewahrung, TOM
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'version_no'], 'ppav_activity_version_unique');
        });

        // ── Betroffenenanfragen (DSR-Fallakte, per-Fall-Krypto) ─────────────
        Schema::create('privacy_data_subject_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('request_number', 32);
            $table->string('type', 16);
            $table->string('status', 20)->default('intake');
            $table->string('channel', 32)->nullable();          // Eingangskanal
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            // Verschluesselte Inhalte (DEK pro Fall); dek_wrapped nullable → Crypto-Shredding.
            $table->text('subject_ciphertext')->nullable();     // Identitaet der betroffenen Person
            $table->text('content_ciphertext')->nullable();     // Anliegen/Notizen
            $table->text('decision_note_ciphertext')->nullable();
            $table->text('dek_wrapped')->nullable();
            $table->string('decision', 16)->nullable();         // granted | partially | rejected
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'request_number'], 'pdsr_org_number_unique');
            $table->index(['organization_id', 'status']);
            $table->index('deadline_at');
        });

        // ── Append-only Event-Hash-Kette (analog whistleblowing_case_events) ─
        Schema::create('privacy_request_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event', 64);
            $table->json('metadata')->nullable();               // minimiert, KEINE Klartext-PII
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['request_id', 'event']);
            $table->index('hash');
        });

        DB::table('audit_chain_heads')->insertOrIgnore([
            'chain' => 'privacy_request_events',
            'head_hash' => null,
            'height' => 0,
        ]);
    }

    public function down(): void {
        DB::table('audit_chain_heads')->where('chain', 'privacy_request_events')->delete();
        Schema::dropIfExists('privacy_request_events');
        Schema::dropIfExists('privacy_data_subject_requests');
        Schema::dropIfExists('privacy_processing_activity_versions');
        Schema::dropIfExists('privacy_processing_activities');
    }
};
