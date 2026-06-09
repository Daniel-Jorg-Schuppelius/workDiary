<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000024_create_privacy_tom_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datenschutzmanagement: zentraler, versionierter TOM-Katalog (Art. 32) mit
 * Zuordnungen (VVT/AVV) und dokumentierten Wirksamkeitspruefungen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('privacy_technical_measures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 24);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('implementation_status', 16)->default('planned');
            $table->string('protection_level', 16)->nullable();   // Schutzbedarf
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->date('next_review_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'implementation_status']);
            $table->index('next_review_at');
        });

        Schema::create('privacy_technical_measure_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('measure_id')->constrained('privacy_technical_measures')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->json('payload');                              // Beschreibung, adressierte Risiken, Nachweise
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->timestamps();

            $table->unique(['measure_id', 'version_no'], 'ptmv_measure_version_unique');
        });

        Schema::create('privacy_measure_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('measure_id')->constrained('privacy_technical_measures')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('privacy_processing_activities')->cascadeOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained('privacy_processing_agreements')->cascadeOnDelete();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('privacy_measure_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('measure_id')->constrained('privacy_technical_measures')->cascadeOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('result', 16);
            $table->text('deviation')->nullable();
            $table->text('follow_up')->nullable();
            $table->date('due_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('privacy_measure_reviews');
        Schema::dropIfExists('privacy_measure_assignments');
        Schema::dropIfExists('privacy_technical_measure_versions');
        Schema::dropIfExists('privacy_technical_measures');
    }
};
