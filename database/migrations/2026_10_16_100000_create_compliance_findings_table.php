<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_16_100000_create_compliance_findings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistenz erkannter Compliance-Verstöße mit Acknowledge-Workflow
 * (Feature 006, Welle D). Bisher wurden die ArbZG-Verstöße ausschließlich
 * on-the-fly berechnet ({@see \App\Services\Compliance\AttendanceComplianceChecker});
 * diese Tabelle hält sie zusätzlich revisionssicher vor, damit dieselbe
 * Feststellung über Scans hinweg wiedererkannt (dedup_key) und ihr
 * Bearbeitungsstand (offen → quittiert/akzeptiert, bzw. automatisch behoben)
 * nachvollziehbar wird. Kein Hard-Delete-Pfad — verschwundene Verstöße werden
 * auf „behoben" gesetzt, nicht gelöscht (GoBD/append-only-Geist; Statuswechsel
 * laufen zusätzlich über die Audit-Hash-Kette).
 *
 * Additiv, org-gescopt. Reihenfolge: nach `organizations`/`users` (Zieltabellen
 * der FKs existieren längst). Kurze, explizite Index-/FK-Namen (MySQL 64-Zeichen-
 * Limit + DB-weite FK-Eindeutigkeit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('compliance_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            // Herkunft/Regel: category = erkennende Domäne (arbzg), rule_code =
            // konkrete Regel (AttendanceComplianceChecker::KIND_*).
            $table->string('category', 32);
            $table->string('rule_code', 64);
            $table->string('severity', 16); // error | warning

            // Subjekt des Verstoßes (polymorph; heute stets ein User/Mitarbeiter).
            $table->nullableMorphs('subject', 'compliance_findings_subject_idx');

            // Zeitbezug des Verstoßes (Kalendertag bzw. Wochenende bei Wochenregel).
            $table->date('scope_date');

            // Gemessener Wert und Schwelle (Minuten) zum Zeitpunkt der Erkennung.
            $table->integer('detected_value')->default(0);
            $table->integer('threshold_value')->default(0);

            // Stabiler Identitäts-Schlüssel für die Dedup beim erneuten Scan.
            $table->string('dedup_key', 191);

            $table->string('status', 16)->default('open'); // open|acknowledged|resolved|accepted
            $table->timestamp('first_detected_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // Acknowledge-Workflow (Quittierung/Akzeptanz mit Begründung).
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('acknowledge_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Genau ein Befund je Org + Identitäts-Schlüssel (Idempotenz der Erkennung).
            $table->unique(['organization_id', 'dedup_key'], 'compliance_findings_uniq_org_key');
            $table->index(['organization_id', 'status'], 'compliance_findings_idx_org_status');
            $table->index(['organization_id', 'scope_date'], 'compliance_findings_idx_org_date');
        });
    }

    public function down(): void {
        Schema::dropIfExists('compliance_findings');
    }
};
