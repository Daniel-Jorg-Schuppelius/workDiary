<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_110400_create_isms_vulnerabilities_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schwachstellenregister (Feature 044, MVP 2): Schwachstellen mit Kritikalität
 * (aus CVSS ableitbar), Verantwortung, Frist und dokumentierter
 * Ausnutzbarkeits-Entscheidung. Bezug zum Softwareinventar
 * (isms_software_product_id) und optional zur importierten Advisory-Ablage
 * (isms_advisory_id). Laufende Nummer je Organisation (VU-1, VU-2, …).
 *
 * 044-Regel: Aus einem Advisory erzeugte Treffer starten mit
 * exploitability=underInvestigation — NICHT automatisch „ausnutzbar".
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_vulnerabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedInteger('vuln_no');
            $table->string('title', 250);
            // CVE-ID o. Ä. (z. B. CVE-2026-12345).
            $table->string('identifier', 64)->nullable();
            $table->decimal('cvss_score', 3, 1)->nullable();
            // low|medium|high|critical (App\Enums\Isms\IncidentSeverity, aus CVSS ableitbar).
            $table->string('severity', 12);
            // Betroffene Komponente (purl/Name@Version-Hinweis aus Advisory/SBOM).
            $table->string('affected_component', 250)->nullable();
            $table->foreignId('isms_software_product_id')->nullable()->constrained('isms_software_products')->nullOnDelete();
            $table->foreignId('isms_advisory_id')->nullable()->constrained('isms_advisories')->nullOnDelete();
            // open|underReview|mitigating|resolved|accepted|notAffected (Statusmaschine).
            $table->string('status', 16)->default('open');
            // unknown|underInvestigation|exploitable|notExploitable (Exploitability).
            $table->string('exploitability', 20)->default('underInvestigation');
            $table->text('exploitability_note')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            // manual|advisoryImport (App\Enums\Isms\VulnerabilitySource).
            $table->string('source', 16)->default('manual');
            // Externe Advisory-Referenz (CVE/CSAF-ID) für die Anzeige/Dedup.
            $table->string('advisory_ref', 250)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'vuln_no'], 'isms_vuln_org_no_uq');
            $table->index(['organization_id', 'status', 'severity'], 'isms_vuln_org_status_idx');
            $table->index(['organization_id', 'due_on'], 'isms_vuln_org_due_idx');
            // Dedup-Schlüssel für den Advisory-Re-Import (identifier + Komponente).
            $table->index(['organization_id', 'identifier'], 'isms_vuln_org_ident_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_vulnerabilities');
    }
};
