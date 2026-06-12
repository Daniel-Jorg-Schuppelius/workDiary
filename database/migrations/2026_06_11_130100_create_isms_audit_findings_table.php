<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_130100_create_isms_audit_findings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditfeststellungen (Feature 046, Inkrement C): laufende Nummer je Audit
 * (finding_no), Art (Haupt-/Nebenabweichung, Beobachtung, Verbesserung),
 * optionaler FK auf die betroffene Normanforderung und Statuskette
 * open → inCorrection → effectivenessCheck → closed (Rücksprung
 * effectivenessCheck → inCorrection; FindingStatus::allowedTransitions()).
 * Abschlussregeln (closed NUR wenn alle Korrekturmaßnahmen done/effective;
 * Nichtkonformitäten brauchen mindestens EINE wirksame Maßnahme) erzwingt
 * der AuditService.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_audit_id')->constrained('isms_audits')->cascadeOnDelete();
            $table->unsignedInteger('finding_no');
            $table->string('kind', 32)->default('observation');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->foreignId('isms_requirement_id')->nullable()->constrained('isms_requirements')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['isms_audit_id', 'finding_no'], 'isms_finding_audit_no_uq');
            $table->index(['organization_id', 'status'], 'isms_finding_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_audit_findings');
    }
};
