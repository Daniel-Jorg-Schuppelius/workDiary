<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_25_100000_add_public_career_fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-437: Öffentlicher Karrierebereich.
 *
 * - `job_postings` erhält den öffentlichen Slug, getrennte öffentliche
 *   Inhaltsfelder (nie interne Budget-/Profil-/Pipeline-Daten) und einen
 *   Bewerbungsschluss. Der Lifecycle draft|published|paused|expired|closed
 *   bleibt im String-Statusfeld.
 * - `job_applications` erhält den Nachweis des Datenschutzhinweises
 *   (Zeitpunkt + Version/Hash) und eine idempotente öffentliche
 *   Eingangsreferenz gegen Doppelsendungen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->string('public_slug', 160)->nullable()->after('job_requisition_id');
            $table->string('public_title', 200)->nullable()->after('public_slug');
            $table->string('public_summary', 500)->nullable()->after('public_title');
            $table->text('public_description')->nullable()->after('public_summary');
            $table->text('public_tasks')->nullable()->after('public_description');
            $table->text('public_requirements')->nullable()->after('public_tasks');
            $table->text('public_benefits')->nullable()->after('public_requirements');
            $table->string('work_location', 200)->nullable()->after('public_benefits');
            $table->date('application_deadline')->nullable()->after('work_location');

            $table->unique(['organization_id', 'public_slug'], 'jpo_org_public_slug_unq');
        });

        Schema::table('job_applications', function (Blueprint $table): void {
            $table->timestamp('privacy_ack_at')->nullable()->after('consent_expires_on');
            $table->string('privacy_ack_version', 64)->nullable()->after('privacy_ack_at');
            $table->string('public_intake_ref', 64)->nullable()->after('privacy_ack_version');

            $table->unique(['organization_id', 'public_intake_ref'], 'jap_org_intake_ref_unq');
        });
    }

    public function down(): void {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropUnique('jpo_org_public_slug_unq');
            $table->dropColumn([
                'public_slug', 'public_title', 'public_summary', 'public_description',
                'public_tasks', 'public_requirements', 'public_benefits',
                'work_location', 'application_deadline',
            ]);
        });

        Schema::table('job_applications', function (Blueprint $table): void {
            $table->dropUnique('jap_org_intake_ref_unq');
            $table->dropColumn(['privacy_ack_at', 'privacy_ack_version', 'public_intake_ref']);
        });
    }
};
