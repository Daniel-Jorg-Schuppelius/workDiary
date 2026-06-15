<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_260000_create_isms_supplier_assessments_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferantenbewertung (Feature 044, MVP 2/3 „Lieferanten und Verträge"):
 * Kritikalitäts-/Risikobewertung von Lieferanten mit
 * Sicherheitsanforderungen, Vertragsmerkmalen (NDA/AVV/Prüfungsrecht) und
 * wiederkehrenden Reviews. Laufende Nummer je Organisation (SA-1, SA-2, …).
 *
 * Supplier-Bezug ist OPTIONAL: ein nullable FK auf das bestehende
 * Supplier-Stammdatenmodell ODER ein Freitext-Name als Fallback. Der
 * AVV-Bezug zum Datenschutzmanagement bleibt BEWUSST lose (Flag has_dpa +
 * Freitext dpa_ref) — KEIN FK auf die in Bearbeitung befindlichen
 * Privacy-Tabellen, die Fallakten bleiben getrennt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_supplier_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedInteger('assessment_no');
            // Optionaler Bezug auf das Lieferanten-Stammdatenmodell (loser FK,
            // nullOnDelete) ODER Freitext-Name als Fallback.
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name', 250);
            // low|medium|high|critical (App\Enums\Isms\IncidentSeverity).
            $table->string('criticality', 12)->default('medium');
            $table->text('service_description')->nullable();
            $table->foreignId('isms_scope_id')->nullable()->constrained('isms_scopes')->nullOnDelete();
            // Geforderte Informationssicherheits-Anforderungen (Meldewege,
            // Verfügbarkeit, Unterauftragnehmer …).
            $table->text('security_requirements')->nullable();
            $table->boolean('has_nda')->default(false);
            // AVV vorhanden (loser Verweis auf das Datenschutzmanagement,
            // KEIN FK — siehe Klassenkommentar).
            $table->boolean('has_dpa')->default(false);
            $table->string('dpa_ref', 250)->nullable();
            $table->boolean('audit_right')->default(false);
            $table->date('last_review_on')->nullable();
            $table->date('next_review_on')->nullable();
            // low|medium|high|critical (App\Enums\Isms\IncidentSeverity).
            $table->string('risk_rating', 12)->default('medium');
            // draft|assessed|approved|flagged (App\Enums\Isms\SupplierAssessmentStatus).
            $table->string('status', 12)->default('draft');
            $table->text('findings')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'assessment_no'], 'isms_supplier_org_no_uq');
            $table->index(['organization_id', 'status', 'risk_rating'], 'isms_supplier_org_status_idx');
            $table->index(['organization_id', 'next_review_on'], 'isms_supplier_org_review_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_supplier_assessments');
    }
};
