<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000022_create_privacy_avv_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datenschutzmanagement MVP 2: Dienstleister-/AVV-Register (DSGVO Art. 28) mit
 * Unterauftragsverarbeitern und Verknuepfung zu Verarbeitungstaetigkeiten.
 * Kurze, explizite Index-/Unique-Namen wegen MySQL-Limit (64).
 */
return new class extends Migration {
    public function up(): void {
        // Dienstleister / Vertragspartner
        Schema::create('privacy_processors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('role', 20)->default('processor');
            $table->string('contact')->nullable();
            $table->string('location')->nullable();        // Verarbeitungsort
            $table->boolean('third_country')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        // AVV / DPA
        Schema::create('privacy_processing_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('processor_id')->constrained('privacy_processors')->cascadeOnDelete();
            $table->string('title');
            $table->string('version', 32)->default('1.0');
            $table->string('status', 16)->default('draft');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->date('review_due_at')->nullable();
            $table->text('data_categories')->nullable();
            $table->boolean('tom_checked')->default(false);
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            // Vertragsende-Workflow (Datenrueckgabe/Loeschnachweis)
            $table->timestamp('terminated_at')->nullable();
            $table->string('data_return', 16)->nullable();  // pending | returned | deleted
            $table->timestamp('data_return_confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('review_due_at');
        });

        // Unterauftragsverarbeiter
        Schema::create('privacy_subprocessors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained('privacy_processing_agreements')->cascadeOnDelete();
            $table->string('name');
            $table->string('purpose')->nullable();
            $table->string('location')->nullable();
            $table->boolean('third_country')->default(false);
            $table->boolean('approved')->default(false);    // Aenderungs-/Freigabestatus
            $table->timestamp('added_at')->nullable();
            $table->timestamps();
        });

        // Verknuepfung AVV ↔ Verarbeitungstaetigkeit
        Schema::create('privacy_agreement_activity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agreement_id')->constrained('privacy_processing_agreements')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('privacy_processing_activities')->cascadeOnDelete();

            $table->unique(['agreement_id', 'activity_id'], 'paa_agreement_activity_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('privacy_agreement_activity');
        Schema::dropIfExists('privacy_subprocessors');
        Schema::dropIfExists('privacy_processing_agreements');
        Schema::dropIfExists('privacy_processors');
    }
};
