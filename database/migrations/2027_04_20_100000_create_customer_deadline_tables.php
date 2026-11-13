<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_20_100000_create_customer_deadline_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenfristen aus Regelsätzen (Feature 197, MVP-1630/1632).
 *
 * - `customer_services`: Leistungsumfang je Kunde (Eintragsart + Produktgruppe,
 *   Pflicht oder Wahl, Periodenart, gültig ab/bis) — gelesen von den
 *   Regelsätzen und vom Leistungsplan (Feature 199).
 * - `customer_deadline_profiles`: Fristmerkmale je Kunde und Regelsatz als
 *   Feldschema-Dokument mit Gültigkeit und zuständiger Person. Eine Änderung
 *   schließt die alte Fassung ab (`valid_until`), statt sie zu überschreiben.
 * - `customer_deadlines`: Frist je Kunde, Regelsatz, Fristart und Periode mit
 *   berechnetem (`original_due_on`) und geltendem Termin (`due_on`), Status,
 *   Verlängerung, Erledigung und Auftrag.
 * - `customer_deadline_events`: Verlauf je Frist (Journal).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('entry_type_id')->constrained('entry_types')->cascadeOnDelete();
            // Code der Klassifikation `product_group` (leer = jede Produktgruppe der Eintragsart).
            $table->string('product_group_code', 64)->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->string('period_kind', 16);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'customer_id'], 'cs_customer_idx');
            $table->index(['organization_id', 'entry_type_id'], 'cs_entry_type_idx');
        });

        Schema::create('customer_deadline_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('rule_set', 40);
            $table->json('traits')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'customer_id', 'rule_set', 'valid_from'], 'cdp_lookup_idx');
            $table->index(['organization_id', 'responsible_user_id'], 'cdp_responsible_idx');
        });

        Schema::create('customer_deadlines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_deadline_profile_id')->nullable()->constrained('customer_deadline_profiles', indexName: 'cd_profile_fk')->nullOnDelete();
            $table->string('rule_set', 40);
            $table->string('kind', 60);
            $table->string('period_key', 10);
            $table->date('due_on');
            $table->date('original_due_on');
            $table->string('status', 16)->default('open');
            // Von Hand gesetzter Termin (Vorabanforderung § 149 Abs. 4 AO): die Neuberechnung lässt ihn stehen.
            $table->boolean('is_due_fixed')->default(false);
            $table->date('advance_requested_on')->nullable();
            $table->text('extension_reason')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Bundesland fehlte: gerechnet mit der Region der Organisation, die Übersicht markiert die Frist.
            $table->boolean('is_region_assumed')->default(false);
            $table->dateTime('warned_at')->nullable();
            $table->dateTime('overdue_warned_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'customer_id', 'rule_set', 'kind', 'period_key'], 'cd_unique');
            $table->index(['organization_id', 'status', 'due_on'], 'cd_status_due_idx');
            $table->index(['organization_id', 'responsible_user_id', 'due_on'], 'cd_responsible_idx');
        });

        Schema::create('customer_deadline_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_deadline_id')->constrained('customer_deadlines', indexName: 'cde_deadline_fk')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'cde_actor_fk')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['customer_deadline_id', 'occurred_at'], 'cde_chrono_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_deadline_events');
        Schema::dropIfExists('customer_deadlines');
        Schema::dropIfExists('customer_deadline_profiles');
        Schema::dropIfExists('customer_services');
    }
};
