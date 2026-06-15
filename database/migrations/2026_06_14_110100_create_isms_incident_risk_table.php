<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_110100_create_isms_incident_risk_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpfung Sicherheitsvorfall ↔ Risiko (Feature 044, MVP 2):
 * Rückführung eines Vorfalls in die Risikobewertung. Pivot ohne eigene
 * organization_id (analog isms_control_risk) — die Mandantengrenze wird
 * transitiv über die org-gescopten Endpunkte gesichert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_incident_risk', function (Blueprint $table): void {
            $table->foreignId('incident_id')->constrained('isms_security_incidents')->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained('isms_risks')->cascadeOnDelete();

            $table->primary(['incident_id', 'risk_id'], 'isms_incident_risk_pk');
            $table->index('risk_id', 'isms_incident_risk_risk_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_incident_risk');
    }
};
