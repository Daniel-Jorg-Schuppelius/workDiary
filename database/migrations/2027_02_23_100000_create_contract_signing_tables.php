<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100000_create_contract_signing_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 157 (MVP-822) — Unterzeichnungsschicht für Kundenvereinbarungen
 * (AVV/NDA) über dem bestehenden Vertragskopf (CLM, Feature 079) und den
 * append-only DMS-Versionen (Feature 031):
 *
 * - contract_signing_revisions: eingefrorene Fassung je Vertrag mit Status,
 *   Vorgänger/Nachfolger, Wirksamkeit, Portal-Freigabe und Manifest-Hash.
 * - contract_signing_manifest_items: die konkret gebundenen Dokumentversionen
 *   samt SHA-256 — der FK auf document_versions ist RESTRICT, damit ein
 *   gebundener Nachweis nicht per Kaskade verschwindet.
 * - contract_signature_requests: je Partei eine Anforderung (Unterzeichner,
 *   Pflicht/Verzicht, Stand).
 * - contract_signature_links: Einmal-Tokens (nur Hash) für Unterzeichnung und
 *   Abruf, mit Ablauf, Widerruf und Versandstand.
 * - contract_signature_evidences: Unterzeichnungsnachweise (Bild oder PDF)
 *   mit Manifest-Hash, Methode, Erklärung, Serverzeit und Prüfentscheidung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('contract_signing_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_no');
            $table->foreignId('predecessor_id')->nullable()->constrained('contract_signing_revisions')->nullOnDelete();
            $table->string('status', 24)->default('draft');
            // AVV: welche Seite Verantwortlicher ist (Art. 28 DSGVO); NDA leer.
            $table->string('controller_party', 16)->nullable();
            $table->text('declaration_text');
            $table->char('manifest_hash', 64)->nullable();
            $table->date('review_on')->nullable();

            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('withdrawal_reason', 500)->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('contract_signing_revisions')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->date('effective_on')->nullable();
            $table->timestamp('customer_visible_at')->nullable();
            $table->foreignId('customer_visible_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'revision_no'], 'csr_contract_rev_unique');
            $table->index(['organization_id', 'status'], 'csr_org_status_idx');
        });

        Schema::create('contract_signing_manifest_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_id')->constrained('contract_signing_revisions')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->string('role', 16);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('original_name', 255);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->unique(['revision_id', 'document_version_id'], 'csmi_revision_version_unique');
        });

        Schema::create('contract_signature_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_id')->constrained('contract_signing_revisions')->cascadeOnDelete();
            $table->string('party', 16);
            $table->boolean('required')->default(true);
            $table->string('signer_name', 120);
            $table->string('signer_function', 120)->nullable();
            $table->string('signer_email', 180)->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('waiver_reason', 500)->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->unique(['revision_id', 'party'], 'csq_revision_party_unique');
        });

        Schema::create('contract_signature_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_id')->constrained('contract_signing_revisions')->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('contract_signature_requests')->cascadeOnDelete();
            $table->string('purpose', 16);
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to', 180)->nullable();
            $table->string('send_error', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('token_hash', 'csl_token_hash_unique');
            $table->index(['revision_id', 'purpose'], 'csl_revision_purpose_idx');
        });

        Schema::create('contract_signature_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revision_id')->constrained('contract_signing_revisions')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('contract_signature_requests')->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained('contract_signature_links')->nullOnDelete();
            $table->char('manifest_hash', 64);
            $table->string('party', 16);
            $table->string('method', 16);
            $table->string('submitted_via', 16);
            $table->string('signer_name', 120);
            $table->string('signer_function', 120)->nullable();
            $table->text('declaration_text');
            $table->boolean('declaration_accepted')->default(false);
            $table->boolean('authority_confirmed')->default(false);
            $table->timestamp('signed_at');
            $table->date('stated_signed_on')->nullable();
            $table->string('disk', 32)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('file_hash', 64)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_status', 16)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['revision_id', 'party'], 'cse_revision_party_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('contract_signature_evidences');
        Schema::dropIfExists('contract_signature_links');
        Schema::dropIfExists('contract_signature_requests');
        Schema::dropIfExists('contract_signing_manifest_items');
        Schema::dropIfExists('contract_signing_revisions');
    }
};
