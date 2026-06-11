<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_190100_create_isms_controls_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISMS-Maßnahmenkatalog (Feature 044, MVP 1): Controls (ISO/IEC 27001:2022
 * Annex A als Referenzkatalog + eigene Maßnahmen) inkl. SoA-Feldern
 * (anwendbar, Begründung, Umsetzungsstatus, Evidenz-Notiz).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_controls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Referenz-Code, z. B. "A.5.1" (Annex A) oder freie Kennung eigener Maßnahmen.
            $table->string('code', 24);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('source', 24)->default('custom');
            // Statement of Applicability: anwendbar ja/nein + Pflicht-Begründung
            // bei Nicht-Anwendbarkeit (Service-Regel im ControlService).
            $table->boolean('applicable')->default(true);
            $table->text('justification')->nullable();
            $table->string('implementation_status', 24)->default('open');
            $table->text('evidence_note')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'isms_controls_org_code_uq');
            $table->index(['organization_id', 'implementation_status'], 'isms_controls_org_status_idx');
            $table->index(['organization_id', 'source', 'applicable'], 'isms_controls_org_source_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_controls');
    }
};
