<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000026_create_privacy_compliance_findings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance-/Lueckenbefunde: regelbasiert aus den echten Daten ermittelt, mit
 * Status, Ausloeser und Drill-down-Referenz; manuelle Stati (nicht anwendbar,
 * Abweichung akzeptiert) werden bei der erneuten Analyse respektiert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('privacy_compliance_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('requirement_key', 64);
            $table->string('label');
            $table->string('category', 32)->nullable();
            $table->string('status', 24)->default('missing'); // required|present|in_review|expiring|missing|not_applicable|deviation_accepted
            $table->string('trigger')->nullable();             // fachlicher Ausloeser
            $table->foreignId('activity_id')->nullable()->constrained('privacy_processing_activities')->cascadeOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained('privacy_processing_agreements')->cascadeOnDelete();
            $table->foreignId('processor_id')->nullable()->constrained('privacy_processors')->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->text('justification')->nullable();          // Pflicht bei not_applicable/deviation
            $table->boolean('auto_detected')->default(true);    // false = Status manuell gesetzt
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'requirement_key'], 'pcf_org_reqkey_index');
        });
    }

    public function down(): void {
        Schema::dropIfExists('privacy_compliance_findings');
    }
};
