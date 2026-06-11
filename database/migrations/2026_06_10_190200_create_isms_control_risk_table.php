<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_190200_create_isms_control_risk_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot Risiko <-> Maßnahme (Feature 044, MVP 1). Bewusst OHNE eigenes
 * Eloquent-Model und ohne organization_id: die Mandantengrenze wird
 * transitiv über BEIDE tenant-gebundenen Eltern (isms_risks.organization_id,
 * isms_controls.organization_id) durchgesetzt; die Services verknüpfen nur
 * IDs, die über die org-gescopten Parent-Queries aufgelöst wurden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_control_risk', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('control_id')->constrained('isms_controls')->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained('isms_risks')->cascadeOnDelete();

            $table->unique(['control_id', 'risk_id'], 'isms_control_risk_uq');
            $table->index('risk_id', 'isms_control_risk_risk_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_control_risk');
    }
};
