<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_130200_create_isms_corrective_actions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Korrekturmaßnahmen zu Auditfeststellungen (Feature 046, Inkrement C):
 * Ursachenanalyse (root_cause), Maßnahmenplan, Verantwortlicher und
 * Fälligkeit. Die WIRKSAMKEITSPRÜFUNG ist bewusst von der Erledigung
 * getrennt: Status open → inProgress → done → effective|ineffective
 * (done = umgesetzt, setzt completed_on; effective/ineffective =
 * Wirksamkeitsprüfung MIT Pflicht-Notiz effectiveness_note — AuditService;
 * ineffective setzt die Feststellung zurück auf inCorrection). Überfällige
 * Maßnahmen meldet der Fristen-Scanner (isms.correctiveActionOverdue).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_corrective_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_audit_finding_id')->constrained('isms_audit_findings')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('root_cause')->nullable();
            $table->text('action_plan')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 32)->default('open');
            $table->text('effectiveness_note')->nullable();
            $table->date('completed_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->index(['organization_id', 'status'], 'isms_corr_org_status_idx');
            $table->index(['organization_id', 'due_on'], 'isms_corr_org_due_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_corrective_actions');
    }
};
