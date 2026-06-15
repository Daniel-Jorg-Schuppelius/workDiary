<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_110000_create_isms_security_incidents_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sicherheitsvorfälle (Feature 044, MVP 2): Informationssicherheitsvorfälle
 * UNABHÄNGIG vom Personenbezug, mit Bewertung, Eindämmung, Ursachenanalyse,
 * Kommunikation und Lessons Learned. Laufende Nummer je Organisation
 * (SI-1, SI-2, …), Statusmaschine im SecurityIncidentService.
 *
 * Datenschutz-Kopplung BEWUSST lose: personal_data_affected ist ein reiner
 * Hinweis auf eine SEPARATE Datenschutzmeldung; privacy_incident_ref hält
 * optional die ID/Sqid eines Privacy\Incident (WIP) als Freitext — KEIN
 * FK-Constraint, die Fallakten bleiben getrennt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_security_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_scope_id')->nullable()->constrained('isms_scopes')->nullOnDelete();
            // Laufende Nummer je Organisation (SI-1, SI-2, …) — vergeben im Service.
            $table->unsignedInteger('incident_no');
            $table->string('title', 180);
            $table->text('description')->nullable();
            // malware|phishing|dataLoss|unauthorizedAccess|serviceOutage|misconfiguration|physical|other
            $table->string('category', 24);
            // low|medium|high|critical (App\Enums\Isms\IncidentSeverity).
            $table->string('severity', 12);
            // reported|triage|contained|eradicated|recovered|closed (Statusmaschine).
            $table->string('status', 16)->default('reported');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('contained_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('impact')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('lessons_learned')->nullable();
            // Hinweis auf eine SEPARATE Datenschutzmeldung — Fallakten NICHT zusammenlegen.
            $table->boolean('personal_data_affected')->default(false);
            // Lose Kopplung: Sqid/ID eines Privacy\Incident (WIP) als Freitext, KEIN FK.
            $table->string('privacy_incident_ref', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['organization_id', 'incident_no'], 'isms_si_org_no_uq');
            $table->index(['organization_id', 'status', 'severity'], 'isms_si_org_status_idx');
            $table->index(['organization_id', 'detected_at'], 'isms_si_org_detected_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_security_incidents');
    }
};
