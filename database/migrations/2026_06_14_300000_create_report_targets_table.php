<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_300000_create_report_targets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 002 (Zielwerte & Benchmarks): hinterlegt je Kennzahl/Bereich einen
 * Soll-Wert, der in den bestehenden Reports gegen den Ist-Wert geprüft wird.
 * Rein additiv — keine bestehende Kennzahl-Berechnung wird verändert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('report_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // contributionMargin | billableRate | reworkShare | slaComplianceRate | utilization
            $table->string('metric', 40);
            // org | customer | project | user
            $table->string('scope', 16)->default('org');
            // Verweis auf customer/project/user je nach scope; NULL bei scope=org.
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->decimal('target_value', 12, 2);
            // month | quarter | year (dokumentarisch); NULL = unspezifisch.
            $table->string('period', 16)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'metric'], 'report_targets_idx_org_metric');
            $table->index(['organization_id', 'metric', 'scope', 'scope_id'], 'report_targets_idx_lookup');
        });
    }

    public function down(): void {
        Schema::dropIfExists('report_targets');
    }
};
