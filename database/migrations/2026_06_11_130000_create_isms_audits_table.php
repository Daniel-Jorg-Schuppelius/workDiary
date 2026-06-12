<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_130000_create_isms_audits_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interne Audits (Feature 046, Inkrement C): Auditplan/-durchführung je
 * Geltungsbereich mit laufender Nummer je Organisation (audit_no, Muster
 * risk_no), geprüfter Norm (optional), Kriterien/Umfang, Auditoren inkl.
 * Unabhängigkeitsprüfung (044: „Wahrung der Unabhängigkeit interner
 * Auditoren") und Statuskette planned → inPreparation → inProgress →
 * reportIssued → closed (Rücksprung reportIssued → inProgress erlaubt;
 * AuditStatus::allowedTransitions(), erzwungen im AuditService —
 * reportIssued NUR mit Durchführungszeitraum + Ergebnis-Zusammenfassung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_scope_id')->constrained('isms_scopes')->cascadeOnDelete();
            $table->unsignedInteger('audit_no');
            $table->string('title', 180);
            $table->string('norm', 64)->nullable();
            $table->string('edition', 16)->nullable();
            $table->string('kind', 32)->default('internal');
            $table->string('status', 32)->default('planned');
            $table->date('planned_on')->nullable();
            $table->date('performed_from')->nullable();
            $table->date('performed_to')->nullable();
            $table->foreignId('lead_auditor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('auditors')->nullable();
            $table->text('criteria')->nullable();
            $table->text('independence_note')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['organization_id', 'audit_no'], 'isms_audit_org_no_uq');
            $table->index(['organization_id', 'status'], 'isms_audit_org_status_idx');
            $table->index(['isms_scope_id', 'status'], 'isms_audit_scope_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_audits');
    }
};
