<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101700_create_learning_cmi5_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cmi5 für die Lernplattform (Feature 149, Nachfolge MVP-743).
 *
 * Ein cmi5-Kurs hängt an einer Lerneinheit und bringt eine oder mehrere AUs
 * mit. Registrierung, AU-Zustand und Sitzungen bilden den Ablauf ab, den die
 * Spezifikation vom LMS verlangt; State- und Profil-Dokumente das kleine LRS.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_cmi5_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_unit_id')->constrained('learning_units')->cascadeOnDelete();
            $table->string('title', 255);
            // Kennung des Herausgebers aus der Kursstruktur.
            $table->string('course_id', 500);
            // Wie bei den AUs vergibt das LMS die Aktivitäts-IDs von Kurs und Blöcken
            // selbst; die satisfied-Statements beziehen sich darauf (cmi5 9.3.6).
            $table->string('activity_id', 100);
            $table->json('blocks')->nullable();
            // Null: reine Kursstruktur, deren AUs extern liegen.
            $table->string('storage_path', 255)->nullable();
            $table->string('structure_hash', 128);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('learning_unit_id', 'lrn_cmi5_pkg_unit_uq');
            $table->unique('activity_id', 'lrn_cmi5_pkg_activity_uq');
        });

        Schema::create('learning_cmi5_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_cmi5_package_id')->constrained('learning_cmi5_packages')->cascadeOnDelete();
            // Kennung des Herausgebers — das LMS vergibt für den Start eine eigene.
            $table->string('publisher_id', 500);
            $table->string('activity_id', 100);
            $table->string('title', 255);
            $table->string('url', 2000);
            $table->string('move_on', 30);
            $table->decimal('mastery_score', 5, 4)->nullable();
            $table->string('launch_method', 20);
            $table->text('launch_parameters')->nullable();
            $table->string('entitlement_key', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique('activity_id', 'lrn_cmi5_au_activity_uq');
        });

        Schema::create('learning_cmi5_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->foreignId('learning_cmi5_package_id')->constrained('learning_cmi5_packages')->cascadeOnDelete();
            $table->uuid('registration');
            $table->timestamp('satisfied_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_enrollment_id', 'learning_cmi5_package_id'], 'lrn_cmi5_reg_uq');
            $table->unique('registration', 'lrn_cmi5_reg_id_uq');
        });

        Schema::create('learning_cmi5_au_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_cmi5_registration_id')->constrained('learning_cmi5_registrations')->cascadeOnDelete();
            $table->foreignId('learning_cmi5_unit_id')->constrained('learning_cmi5_units')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('passed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->timestamp('satisfied_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_cmi5_registration_id', 'learning_cmi5_unit_id'], 'lrn_cmi5_au_state_uq');
        });

        Schema::create('learning_cmi5_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_cmi5_registration_id')->constrained('learning_cmi5_registrations')->cascadeOnDelete();
            $table->foreignId('learning_cmi5_unit_id')->constrained('learning_cmi5_units')->cascadeOnDelete();
            $table->uuid('session_id');
            $table->string('launch_mode', 10);
            // Tokens nur als Abdruck: Wer die Datenbank liest, kann damit nichts senden.
            $table->string('fetch_token_hash', 64);
            $table->timestamp('fetch_used_at')->nullable();
            $table->string('auth_token_hash', 64)->nullable();
            $table->timestamp('launched_at');
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('last_statement_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique('session_id', 'lrn_cmi5_sess_uq');
            $table->unique('fetch_token_hash', 'lrn_cmi5_sess_fetch_uq');
            $table->index('auth_token_hash', 'lrn_cmi5_sess_auth_idx');
            $table->index(['learning_cmi5_registration_id', 'learning_cmi5_unit_id'], 'lrn_cmi5_sess_reg_idx');
        });

        Schema::create('learning_xapi_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // state | agent_profile
            $table->string('kind', 20);
            $table->string('activity_id', 500)->nullable();
            $table->string('agent_hash', 64);
            $table->uuid('registration')->nullable();
            $table->string('document_id', 255);
            // Abdruck über Art, Aktivität, Agent, Registrierung und Dokument-ID —
            // eindeutig, auch wo Teile fehlen dürfen.
            $table->string('lookup_hash', 64);
            $table->longText('content');
            $table->string('content_type', 100);
            $table->string('etag', 64);
            $table->timestamps();

            $table->unique('lookup_hash', 'lrn_xapi_doc_lookup_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_xapi_documents');
        Schema::dropIfExists('learning_cmi5_sessions');
        Schema::dropIfExists('learning_cmi5_au_states');
        Schema::dropIfExists('learning_cmi5_registrations');
        Schema::dropIfExists('learning_cmi5_units');
        Schema::dropIfExists('learning_cmi5_packages');
    }
};
