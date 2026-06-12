<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_090300_create_isms_applicability_statements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statement of Applicability je Geltungsbereich (Feature 044/046): trägt
 * pro Scope + Anforderung die SoA-Aussage — Anwendbarkeit, Begründung
 * (Pflicht bei applicable = false, Serviceregel im RequirementService),
 * Umsetzungsstatus und Nachweisverweis. Die frühere SoA-Ebene auf
 * isms_controls wandert hierher (Datenmigration 2026_06_11_090500).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_applicability_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_scope_id')->constrained('isms_scopes')->cascadeOnDelete();
            $table->foreignId('isms_requirement_id')->constrained('isms_requirements')->cascadeOnDelete();
            $table->boolean('applicable')->default(true);
            $table->text('justification')->nullable();
            $table->string('implementation_status', 24)->default('open');
            $table->text('evidence_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['isms_scope_id', 'isms_requirement_id'], 'isms_stmt_scope_req_uq');
            $table->index(['organization_id', 'implementation_status'], 'isms_stmt_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_applicability_statements');
    }
};
