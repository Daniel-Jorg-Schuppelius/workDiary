<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_110200_create_isms_incident_control_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpfung Sicherheitsvorfall ↔ Maßnahme (Feature 044, MVP 2):
 * Rückführung eines Vorfalls in die Kontrollbewertung / Maßnahmenverfolgung.
 * Pivot ohne eigene organization_id (analog isms_control_risk).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_incident_control', function (Blueprint $table): void {
            $table->foreignId('incident_id')->constrained('isms_security_incidents')->cascadeOnDelete();
            $table->foreignId('control_id')->constrained('isms_controls')->cascadeOnDelete();

            $table->primary(['incident_id', 'control_id'], 'isms_incident_control_pk');
            $table->index('control_id', 'isms_incident_ctrl_ctrl_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_incident_control');
    }
};
