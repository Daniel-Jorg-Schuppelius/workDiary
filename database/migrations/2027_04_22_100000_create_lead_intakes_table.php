<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_22_100000_create_lead_intakes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufnahmeakte am Lead (Feature 200, MVP-1641–1644, MVP-1675).
 *
 * - `lead_intakes`: eine Fassung der Akte je Zeile (Wiederholung = neue
 *   Fassung mit `supersedes_id`). Stufe, Gesprächsbogen als
 *   Feldschema-Dokument (`form`), Angaben besonderer Kategorie verschlüsselt
 *   (`sensitive_values`), eingefrorene Prüfpakete (`packages`), Entscheidung
 *   mit Begründung, zweiter Blick, Aktualisierungs- und Aufbewahrungsfrist.
 * - `customers.relationship_ended_on`: Ende der Geschäftsbeziehung, Anker der
 *   Aufbewahrung nach § 8 Abs. 4 Satz 2 GwG.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('lead_intakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('lead_intakes')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->string('stage', 16)->default('inquiry');
            $table->json('packages')->nullable();
            $table->json('form')->nullable();
            // Ausweisangaben (§ 8 Abs. 2 GwG) und Gesundheitsangaben (Art. 9 DSGVO), verschlüsselt.
            $table->text('sensitive_values')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('decision', 16)->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('second_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('second_reviewed_at')->nullable();
            $table->foreignId('protocol_id')->nullable()->constrained('protocols')->nullOnDelete();
            $table->date('refresh_due_on')->nullable();
            $table->timestamp('refresh_warned_at')->nullable();
            $table->date('relationship_ended_on')->nullable();
            $table->date('retention_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'stage'], 'li_stage_idx');
            $table->index(['organization_id', 'lead_id'], 'li_lead_idx');
            $table->index(['organization_id', 'customer_id'], 'li_customer_idx');
            $table->index(['organization_id', 'refresh_due_on'], 'li_refresh_idx');
            $table->index(['organization_id', 'retention_until'], 'li_retention_idx');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->date('relationship_ended_on')->nullable();
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('relationship_ended_on');
        });
        Schema::dropIfExists('lead_intakes');
    }
};
