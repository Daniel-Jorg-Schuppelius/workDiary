<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_090200_create_isms_control_requirement_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot Maßnahme <-> Anforderung (Feature 046: eine betriebliche Maßnahme
 * erfüllt mehrere Normanforderungen, auch normübergreifend; entspricht
 * isms_control_mappings aus Feature 044). Bewusst OHNE eigenes Eloquent-
 * Model und ohne organization_id: die Mandantengrenze wird transitiv über
 * BEIDE tenant-gebundenen Eltern (isms_controls.organization_id,
 * isms_requirements.organization_id) durchgesetzt; die Services verknüpfen
 * nur IDs, die über die org-gescopten Parent-Queries aufgelöst wurden
 * (analog isms_control_risk).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_control_requirement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('control_id')->constrained('isms_controls')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('isms_requirements')->cascadeOnDelete();

            $table->unique(['control_id', 'requirement_id'], 'isms_ctrl_req_uq');
            $table->index('requirement_id', 'isms_ctrl_req_req_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_control_requirement');
    }
};
