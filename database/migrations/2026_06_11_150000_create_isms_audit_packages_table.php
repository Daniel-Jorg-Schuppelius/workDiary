<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_150000_create_isms_audit_packages_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditpakete (Feature 046, Inkrement E / 044 „Auditbereitschaft"):
 * stichtagsbezogene, integritätsgeschützte Exportpakete je Geltungsbereich
 * mit laufender Nummer je Organisation (package_no, Muster audit_no).
 *
 * Stichtags-Semantik (MVP, ehrlich): as_of_date ist der dokumentierte
 * Berichtsstichtag; die Daten werden zum Zeitpunkt der FINALISIERUNG
 * eingefroren (Snapshot, data_captured_at im JSON-meta) — KEINE
 * rückwirkende Zeitreise-Rekonstruktion (das wäre Event-Sourcing).
 *
 * Finalisierung schreibt die JSON-Datei (file_path, Disk wie ExportRunner),
 * den SHA-256 (file_hash, Integritätsnachweis — prüfbar über
 * `isms:verify-packages` und den UI-Button) und friert das Paket ein
 * (Model-Guard wie IsmsRiskAssessment: finalized = unveränderlich).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_audit_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_scope_id')->constrained('isms_scopes')->cascadeOnDelete();
            // Laufende Nummer je Organisation (P-1, P-2, ...) — Vergabe im Service.
            $table->unsignedInteger('package_no');
            $table->string('title', 180);
            // Dokumentierter Berichtsstichtag (KEINE Zeitreise — siehe oben).
            $table->date('as_of_date');
            // Optionaler Norm-Filter des Pakets (z. B. ISO/IEC 27001 / 2022).
            $table->string('norm', 64)->nullable();
            $table->string('edition', 16)->nullable();
            $table->string('status', 16)->default('draft'); // draft|finalized
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable(); // SHA-256 der Exportdatei
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['organization_id', 'package_no'], 'isms_pkg_org_no_uq');
            $table->index(['organization_id', 'status'], 'isms_pkg_org_status_idx');
            $table->index(['isms_scope_id', 'status'], 'isms_pkg_scope_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_audit_packages');
    }
};
