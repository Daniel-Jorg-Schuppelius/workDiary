<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_27_100000_create_meter_billing_agreements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zählerstands-Faktura (Feature 116, MVP-605).
 *
 * Eine Vereinbarung je Kunde + Asset: Grundpreis, Einheitspreis, Freimenge
 * und optionale Mengenstaffel. Die Staffel liegt als JSON, weil ihre Länge
 * vertragsabhängig ist — eine feste Spaltenzahl hätte spätestens beim
 * dritten Vertrag nicht mehr gepasst.
 *
 * `meter_billing_runs` sichert die Idempotenz je Vereinbarung und Periode:
 * Ein Nachlauf darf keinen zweiten Entwurf erzeugen (Muster MVP-415).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('meter_billing_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 191);
            $table->decimal('base_price', 12, 2)->default('0.00');
            $table->decimal('unit_price', 12, 4);
            $table->decimal('free_units', 14, 3)->default('0.000');
            // [{"from": 0, "price": "0.0120"}, {"from": 5000, "price": "0.0090"}]
            $table->json('tiers')->nullable();
            $table->string('unit', 32)->nullable();
            // monthly | quarterly | yearly
            $table->string('interval_unit', 16)->default('monthly');
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->date('next_run_on');
            $table->date('last_run_on')->nullable();
            $table->date('end_on')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'next_run_on'], 'meter_agr_org_status_next_idx');
            $table->index(['asset_id', 'status'], 'meter_agr_asset_status_idx');
        });

        Schema::create('meter_billing_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_billing_agreement_id')->constrained('meter_billing_agreements')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            // Ohne Endstand entsteht KEINE Rechnung, sondern ein Befund —
            // der Grund steht hier, damit der Lauf erklärbar bleibt.
            $table->string('skipped_reason', 191)->nullable();
            $table->decimal('consumption', 14, 3)->nullable();
            $table->timestamps();

            $table->unique(['meter_billing_agreement_id', 'period_start'], 'meter_run_agreement_period_unq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('meter_billing_runs');
        Schema::dropIfExists('meter_billing_agreements');
    }
};
