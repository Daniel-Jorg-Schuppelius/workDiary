<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_26_100000_create_warranty_periods_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gewährleistungsfristen (Feature 115, MVP-604).
 *
 * `side` trennt die eigene Haftung gegenüber dem Auftraggeber (`owed`) von den
 * Fristen der eingesetzten Subunternehmer (`claimable`). Diese Trennung ist der
 * ganze Zweck: Die Frist des Subunternehmers läuft typischerweise FRÜHER ab
 * als die eigene — wer das übersieht, haftet allein für einen Mangel, den ein
 * anderer verursacht hat. Erst beide Seiten nebeneinander erlauben die
 * Warnung „Sub-Frist endet vor deiner".
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('warranty_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // owed = wir schulden Gewährleistung | claimable = wir können sie einfordern
            $table->string('side', 16);
            // bgb_5y | vob_4y | custom — die Rechtsgrundlage bleibt sichtbar,
            // auch wenn das Enddatum von Hand verschoben wurde.
            $table->string('basis', 16);
            $table->date('starts_on');
            $table->date('ends_on');
            // Abweichendes Ende braucht eine Begründung, sonst ist später nicht
            // nachvollziehbar, warum die Frist von der Grundlage abweicht.
            $table->string('override_reason', 500)->nullable();
            $table->foreignId('protocol_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('trade', 120)->nullable();
            // open | closed | claimed (gerügt)
            $table->string('status', 16)->default('open');
            $table->foreignId('claim_case_id')->nullable()->constrained('claim_cases')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'ends_on'], 'warranty_org_status_end_idx');
            $table->index(['organization_id', 'side', 'project_id'], 'warranty_org_side_project_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('warranty_periods');
    }
};
