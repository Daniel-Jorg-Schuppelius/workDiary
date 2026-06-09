<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000014_create_whistleblowing_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Hinweisgebersystem – technisches Fundament (Phase 1). Siehe
 * docs/hinweisgebersystem.md (inkl. Red-Team-Entscheidungen, Abschnitt 25):
 * per-Fall-DEK (`dek_wrapped`) fuer Crypto-Shredding, voll-zufaellige public_id,
 * keine Reporter-IP/-UA, append-only Event-Hash-Kette, Tombstone-Ledger.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('whistleblowing_portals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->string('public_slug', 64)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('allow_anonymous')->default(true);
            $table->boolean('allow_confidential')->default(true);
            $table->json('allowed_locales')->nullable();
            $table->string('default_locale', 10)->nullable();
            $table->text('intro_text')->nullable();
            $table->string('privacy_text_version', 32)->nullable();
            $table->json('external_channels')->nullable();
            $table->unsignedSmallInteger('retention_months')->default(36);
            $table->timestamps();
        });

        Schema::create('whistleblowing_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->uuid('public_id')->unique();                 // voll-zufaellig (UUIDv4)
            $table->string('case_number', 32);
            $table->string('access_code_hash', 255);             // Argon2-Hash des Geheimnisses
            $table->string('access_code_lookup', 64)->unique();  // HMAC (eigener Key)
            $table->text('dek_wrapped')->nullable();             // per-Fall-DEK, vom KEK gewrappt; null = geschreddert
            $table->string('reporter_mode', 16);
            $table->string('category', 32);
            $table->string('status', 32)->index();
            $table->string('priority', 16)->default('normal');
            $table->longText('subject_ciphertext');
            $table->longText('description_ciphertext');
            $table->longText('contact_ciphertext')->nullable();
            $table->date('occurred_from')->nullable();
            $table->date('occurred_to')->nullable();
            $table->timestamp('acknowledgement_due_at')->nullable();
            $table->timestamp('feedback_due_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('feedback_sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('retention_due_at')->nullable();
            $table->timestamp('legal_hold_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'case_number']);
        });

        Schema::create('whistleblowing_case_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('whistleblowing_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();

            $table->index(['case_id', 'user_id', 'revoked_at']);
        });

        Schema::create('whistleblowing_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('whistleblowing_cases')->cascadeOnDelete();
            $table->string('author_type', 16);
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 16);
            $table->longText('body_ciphertext');
            $table->timestamp('sent_at');
            $table->timestamp('read_by_reporter_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['case_id', 'visibility']);
        });

        Schema::create('whistleblowing_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('whistleblowing_cases')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('whistleblowing_messages')->nullOnDelete();
            $table->string('uploaded_by_type', 16);
            $table->string('storage_key', 191)->unique();
            $table->text('original_name_ciphertext');
            $table->string('mime_detected', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('scan_status', 16)->default('pending');
            $table->boolean('metadata_scrubbed')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        // Append-only Event-Kette. BEWUSST ohne FK auf cases/organizations:
        // Ein FK mit null/cascade waere ein DB-seitiges UPDATE/DELETE und wuerde
        // die Hash-Kette zerreissen (case_id/organization_id gehen in den Hash).
        Schema::create('whistleblowing_case_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event', 64);
            $table->json('metadata')->nullable();   // minimiert, KEINE Meldeinhalte
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['case_id', 'event']);
            $table->index('hash');
        });

        // Tombstone-Ledger: ueberlebt die Loeschung, traegt KEINE Inhalte.
        Schema::create('whistleblowing_case_tombstones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('case_number', 32);
            $table->uuid('public_id');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('closed_category', 32)->nullable();
            $table->timestamp('deleted_at');
            $table->string('audit_hash', 64)->nullable();

            $table->index('case_number');
        });

        // Kettenkopf fuer die Event-Kette (analog audit_logs / org-Kette).
        DB::table('audit_chain_heads')->insertOrIgnore([
            'chain' => 'whistleblowing_case_events',
            'head_hash' => null,
            'height' => 0,
        ]);
    }

    public function down(): void {
        DB::table('audit_chain_heads')->where('chain', 'whistleblowing_case_events')->delete();
        Schema::dropIfExists('whistleblowing_case_tombstones');
        Schema::dropIfExists('whistleblowing_case_events');
        Schema::dropIfExists('whistleblowing_attachments');
        Schema::dropIfExists('whistleblowing_messages');
        Schema::dropIfExists('whistleblowing_case_assignments');
        Schema::dropIfExists('whistleblowing_cases');
        Schema::dropIfExists('whistleblowing_portals');
    }
};
