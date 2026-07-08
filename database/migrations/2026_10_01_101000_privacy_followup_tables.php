<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101000_privacy_followup_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datenschutz-Folgephasen (Nachtrag 043):
 *  (a) privacy_dpia_steps — geführter DSFA-Schritt-Workflow
 *      (Beschreibung→Notwendigkeit→Risiken→Maßnahmen→Freigabe),
 *  (b) privacy_attachments.valid_until — TOM-Nachweise mit Gültig-bis
 *      (Fristen-Scanner),
 *  (c) privacy_requirements — konfigurierbarer Anforderungskatalog
 *      (löst die hartkodierten Prüfregeln ab; Branchenprofile liefern
 *      Vorlagen),
 *  (d) privacy_subprocessors.safeguards — Garantien bei Drittlandbezug.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('privacy_dpia_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'pds_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('dpia_id')
                ->constrained('privacy_dpias', indexName: 'pds_dpia_fk')
                ->cascadeOnDelete();
            $table->string('step', 30); // description|necessity|risks|mitigations|approval
            $table->unsignedTinyInteger('position');
            $table->string('status', 16)->default('pending'); // pending|done
            $table->text('content')->nullable();
            $table->foreignId('completed_by')->nullable()
                ->constrained('users', indexName: 'pds_completed_by_fk')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['dpia_id', 'step'], 'pds_dpia_step_uq');
        });

        Schema::create('privacy_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'preq_org_fk')
                ->cascadeOnDelete();
            $table->string('requirement_key', 40);
            $table->string('label');
            $table->string('category', 40)->nullable();
            $table->string('check_type', 40); // Prüf-Implementierung (Code)
            $table->boolean('active')->default(true);
            $table->json('params')->nullable();
            $table->string('source', 20)->default('manual'); // manual|default|profile
            $table->timestamps();

            $table->unique(['organization_id', 'requirement_key'], 'preq_org_key_uq');
        });

        Schema::table('privacy_attachments', function (Blueprint $table): void {
            $table->date('valid_until')->nullable()->after('mime');
        });

        Schema::table('privacy_subprocessors', function (Blueprint $table): void {
            $table->string('safeguards')->nullable()->after('third_country');
        });
    }

    public function down(): void {
        Schema::dropIfExists('privacy_dpia_steps');
        Schema::dropIfExists('privacy_requirements');
        Schema::table('privacy_attachments', function (Blueprint $table): void {
            $table->dropColumn('valid_until');
        });
        Schema::table('privacy_subprocessors', function (Blueprint $table): void {
            $table->dropColumn('safeguards');
        });
    }
};
