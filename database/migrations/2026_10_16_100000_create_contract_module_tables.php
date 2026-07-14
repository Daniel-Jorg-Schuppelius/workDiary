<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_16_100000_create_contract_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welle D — Allgemeines Contract-Lifecycle-Management (CLM). Additiv zum
 * spezialisierten Leasing-/Finanzierungsmodell (Feature 074): ein generisches
 * Vertragsmodell für Verträge beliebiger Art mit Laufzeit-/Verlängerungslogik,
 * Kündigungsfrist, Indexierungsregel (Datenfeld, keine externe Index-API) und
 * einem Obligationen-/Vertragskalender, der die bestehende Fristen-/Eskalations-
 * mechanik (contract.deadlineDue) speist. Kurze, DB-weit eindeutige FK-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('title');
            $table->string('kind', 30);
            $table->string('status', 20)->default('draft');

            // Vertragspartner: verknüpfter Kunde/Lieferant oder Freitext.
            $table->string('partner_type', 20)->default('other');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('partner_name')->nullable();

            // Laufzeit-/Verlängerungslogik.
            $table->string('term_kind', 20)->default('fixed');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('min_term_months')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('renew_period_months')->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();

            // Indexierung (deskriptiv, keine externe Index-API).
            $table->string('indexation_method', 20)->default('none');
            $table->decimal('indexation_value', 8, 4)->nullable();
            $table->date('indexation_review_on')->nullable();
            $table->string('indexation_note')->nullable();

            // Wert/Währung + Dokumentbezug.
            $table->decimal('value_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('value_period', 20)->default('once');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'contracts_org_number_unique');
            $table->index(['organization_id', 'status', 'ends_on'], 'contracts_org_status_end_idx');
        });

        Schema::create('contract_obligations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('contracts', indexName: 'contract_obl_contract_fk')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('title');
            $table->date('due_on');
            $table->unsignedInteger('warn_days_before')->default(30);
            $table->boolean('recurring')->default(false);
            $table->unsignedSmallInteger('recurrence_months')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'due_on'], 'contract_obl_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('contract_obligations');
        Schema::dropIfExists('contracts');
    }
};
