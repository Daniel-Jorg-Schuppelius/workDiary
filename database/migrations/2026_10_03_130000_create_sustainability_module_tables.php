<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_03_130000_create_sustainability_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 071 (Phase 22, MVP-223–236): Nachhaltigkeit/ESG —
 * Kriterienkatalog (E/S/G, gewichtbar), versionierte Bewertungen mit
 * Snapshot (Greenwashing-Schutz), Aktivitätsdaten mit Datenqualität,
 * versionierte Emissionsfaktoren-Bibliothek (Sets, Org-Override,
 * Stichtags-Lookup — Blueprint PerDiemRate; Einheiten als Code-Strings
 * nach Flexibilitätsplan D5), Maßnahmenregister mit Wirksamkeitsprüfung,
 * Zielpfade, Berichts-Snapshots und VSME-Referenzmatrix (P3/D8).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sustainability_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'susc_org_fk')->cascadeOnDelete();
            $table->string('dimension', 12); // environment|social|governance
            $table->string('label', 200);
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('weight')->default(1); // Gewichtung 1–10
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sustainability_factor_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'susfs_org_fk')->cascadeOnDelete();
            $table->string('name', 200); // z. B. „UBA/DEFRA Standard"
            $table->string('source', 200)->nullable(); // Quelle/Lizenz
            $table->string('region', 10)->default('DE');
            $table->unsignedSmallInteger('year');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sustainability_emission_factors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('factor_set_id')->constrained('sustainability_factor_sets', indexName: 'susef_set_fk')->cascadeOnDelete();
            $table->string('activity_code', 40); // electricity_kwh|diesel_l|km_car|waste_kg|…
            $table->string('label', 200);
            // Einheiten als Code-Strings (D5): kg_co2e_per_kwh, kg_co2e_per_l, …
            $table->string('unit_code', 40);
            $table->decimal('factor', 12, 6); // kg CO2e je Aktivitätseinheit
            $table->unsignedTinyInteger('scope')->default(2); // GHG Scope 1|2|3
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('quality', 10)->default('high'); // high|medium|low
            $table->string('source_note', 300)->nullable();
            $table->timestamps();

            $table->index(['factor_set_id', 'activity_code', 'valid_from'], 'susef_set_code_from_idx');
        });

        Schema::create('sustainability_activity_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'susar_org_fk')->cascadeOnDelete();
            $table->string('subject_type', 120)->nullable(); // Asset/Vehicle/Project/Site …
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 200)->nullable(); // Fallback ohne Objektbezug
            $table->string('activity_code', 40);
            $table->decimal('amount', 14, 3);
            $table->string('unit', 20); // kWh|l|km|kg|m3|EUR
            $table->date('period_start');
            $table->date('period_end');
            $table->string('data_quality', 10)->default('measured'); // measured|calculated|estimated
            $table->string('source_note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'susar_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'activity_code', 'period_end'], 'susar_org_code_end_idx');
        });

        Schema::create('sustainability_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'susa_org_fk')->cascadeOnDelete();
            $table->string('subject_type', 120)->nullable(); // Asset/Project/Supplier/Article/…
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 200); // Anzeigename (auch ohne Objekt)
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 10)->default('draft'); // draft|final
            $table->text('summary')->nullable();
            $table->decimal('total_score', 5, 2)->nullable(); // 0–5, gewichtet
            $table->string('rating', 10)->nullable(); // green|yellow|red
            $table->string('data_quality', 10)->nullable(); // schwächstes Item
            $table->json('snapshot')->nullable(); // Items+Gewichte+Faktorkontext bei final
            $table->foreignId('assessed_by')->nullable()->constrained('users', indexName: 'susa_assessed_fk')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'susa_subject_idx');
        });

        Schema::create('sustainability_assessment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'susai_org_fk')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('sustainability_assessments', indexName: 'susai_assessment_fk')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('sustainability_criteria', indexName: 'susai_criterion_fk')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable(); // 0–5 (null = nicht bewertet)
            $table->unsignedTinyInteger('weight')->default(1); // Snapshot der Gewichtung
            $table->string('data_quality', 10)->default('estimated');
            $table->string('source_note', 300)->nullable();
            $table->string('justification', 1000)->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'criterion_id'], 'susai_assessment_criterion_uq');
        });

        Schema::create('sustainability_measures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'susm_org_fk')->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained('sustainability_assessments', indexName: 'susm_assessment_fk')->nullOnDelete();
            $table->string('title', 300);
            $table->string('description', 1000)->nullable();
            $table->string('expected_impact', 500)->nullable(); // erwartete Wirkung
            $table->string('effort', 10)->default('medium'); // low|medium|high
            $table->decimal('cost_estimate', 12, 2)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'susm_resp_fk')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 12)->default('proposed'); // proposed|approved|in_progress|done|discarded
            $table->string('evidence_note', 1000)->nullable();
            $table->string('effectiveness', 12)->nullable(); // effective|partly|ineffective (Wirksamkeitsprüfung)
            $table->string('effectiveness_note', 1000)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', indexName: 'susm_reviewed_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'susm_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sustainability_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sust_org_fk')->cascadeOnDelete();
            $table->string('metric', 40); // co2e_total|energy_kwh|waste_kg|repair_quota|…
            $table->string('label', 200);
            $table->decimal('baseline_value', 14, 3);
            $table->unsignedSmallInteger('baseline_year');
            $table->decimal('target_value', 14, 3);
            $table->unsignedSmallInteger('target_year');
            $table->string('unit', 20);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('sustainability_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'susrs_org_fk')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('data'); // Kennzahlen + Methodik + Faktor-/Setversionen
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'susrs_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        // Referenzmatrix (P3/D8): frame + frame_version als DATEN — erste
        // gepflegte Version vsme-1.0; esrs/iso14001 folgen nach W4/W6.
        Schema::create('sustainability_frame_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'susfm_org_fk')->cascadeOnDelete();
            $table->string('frame', 20); // vsme|esrs|iso14001
            $table->string('frame_version', 20); // 1.0|2.0|2026 …
            $table->string('section_code', 20); // B3, C1 …
            $table->string('section_label', 300);
            $table->string('mapping_note', 500)->nullable(); // welche WorkDiary-Daten
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['frame', 'frame_version'], 'susfm_frame_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sustainability_frame_mappings');
        Schema::dropIfExists('sustainability_report_snapshots');
        Schema::dropIfExists('sustainability_targets');
        Schema::dropIfExists('sustainability_measures');
        Schema::dropIfExists('sustainability_assessment_items');
        Schema::dropIfExists('sustainability_assessments');
        Schema::dropIfExists('sustainability_activity_records');
        Schema::dropIfExists('sustainability_emission_factors');
        Schema::dropIfExists('sustainability_factor_sets');
        Schema::dropIfExists('sustainability_criteria');
    }
};
