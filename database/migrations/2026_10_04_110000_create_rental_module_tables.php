<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_04_110000_create_rental_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 073 (Phase 25, MVP-258–269): Geräte- und Maschinenverleih.
 * Assets bleiben führend für das Gerät; Verleihakten führen Zeitraum,
 * Konditionen, Übergabe, Rücknahme und kaufmännische Folge.
 * Entscheidung D10: versionierte Rate Cards, die Akte friert die angewendete
 * Version als Snapshot ein; Kaution ist ein eigener Finanzvorgang
 * (rental_deposits), nie Mietumsatz. Sperren laufen über das gemeinsame
 * Sperrmodell asset_blocks (D12). Zustand/Zubehör leben strukturiert in den
 * Übergabe-/Rücknahmeprotokollen (rental_condition_items/rental_accessory_items).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('rental_rate_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'version'], 'rental_cards_org_name_ver_unique');
        });

        Schema::create('rental_rate_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_rate_card_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('label');
            // Optionale Eingrenzung auf eine Gerätegruppe (classifications-Code)
            $table->string('group_code', 60)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('unit', 20)->default('day');
            $table->unsignedInteger('min_duration_days')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rental_rate_card_id'], 'rental_items_org_card_idx');
        });

        Schema::create('rental_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_rentable')->default(true);
            // Gerätegruppe/Pool (classifications-Code, analog assets.category_code)
            $table->string('group_code', 60)->nullable();
            $table->string('home_site_label')->nullable();
            $table->unsignedInteger('buffer_before_hours')->default(0);
            $table->unsignedInteger('buffer_after_hours')->default(0);
            $table->boolean('requires_inspection')->default(false);
            $table->string('min_condition', 20)->nullable();
            $table->json('accessories')->nullable();
            $table->foreignId('default_rate_card_id')->nullable()->constrained('rental_rate_cards')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'asset_id'], 'rental_profiles_org_asset_unique');
            $table->index(['organization_id', 'group_code'], 'rental_profiles_org_group_idx');
        });

        Schema::create('rental_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('draft');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('contact_name')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('handover_location')->nullable();
            $table->string('return_location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('actual_return_at')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            // D10: angewendete Rate Card + eingefrorene Konditionen und Abweichungen
            $table->foreignId('rental_rate_card_id')->nullable()->constrained('rental_rate_cards')->nullOnDelete();
            $table->json('terms_snapshot')->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->string('insurance_note')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'rental_cases_org_number_unique');
            $table->index(['organization_id', 'status', 'ends_at'], 'rental_cases_org_status_end_idx');
            $table->index(['organization_id', 'customer_id'], 'rental_cases_org_customer_idx');
        });

        Schema::create('rental_case_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('planned');
            // Tauschgerät-Kette: ersetzt-durch verweist auf die neue Zeile
            $table->foreignId('replaced_by_id')->nullable()->constrained('rental_case_assets')->nullOnDelete();
            $table->json('accessories')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['rental_case_id', 'asset_id'], 'rental_case_assets_case_asset_unique');
            $table->index(['organization_id', 'asset_id'], 'rental_case_assets_org_asset_idx');
        });

        Schema::create('rental_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('hard');
            $table->string('status', 20)->default('active');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Puffer (Transport/Rüst-/Reinigungszeit) rund um den Kernzeitraum
            $table->unsignedInteger('buffer_before_hours')->default(0);
            $table->unsignedInteger('buffer_after_hours')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_id', 'starts_at', 'ends_at'], 'rental_res_org_asset_range_idx');
            $table->index(['organization_id', 'rental_case_id'], 'rental_res_org_case_idx');
        });

        Schema::create('rental_handover_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->dateTime('reported_at');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition', 20)->default('good');
            $table->json('checklist')->nullable();
            $table->decimal('meter_value', 18, 4)->nullable();
            $table->decimal('operating_hours', 12, 2)->nullable();
            $table->string('fuel_level', 20)->nullable();
            $table->string('signature_name')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('portal_confirmed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rental_case_id'], 'rental_handover_org_case_idx');
        });

        Schema::create('rental_return_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->dateTime('reported_at');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition', 20)->default('good');
            $table->json('checklist')->nullable();
            $table->decimal('meter_value', 18, 4)->nullable();
            $table->decimal('operating_hours', 12, 2)->nullable();
            $table->string('fuel_level', 20)->nullable();
            $table->text('damages')->nullable();
            $table->text('missing_parts')->nullable();
            $table->boolean('cleaning_required')->default(false);
            $table->json('consumables')->nullable();
            $table->string('follow_up', 20)->default('none');
            $table->text('follow_up_note')->nullable();
            $table->string('signature_name')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rental_case_id'], 'rental_return_org_case_idx');
        });

        Schema::create('rental_condition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Position eines Übergabe- ODER Rücknahmeprotokolls
            $table->string('report_type');
            $table->unsignedBigInteger('report_id');
            $table->string('label');
            $table->string('state', 20)->default('ok');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'report_id'], 'rental_cond_items_report_idx');
        });

        Schema::create('rental_accessory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('report_type');
            $table->unsignedBigInteger('report_id');
            $table->string('label');
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('present')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'report_id'], 'rental_acc_items_report_idx');
        });

        Schema::create('rental_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('status', 20)->default('draft');
            $table->string('label');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 20)->default('day');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            // Pflichtbegründung bei Schaden/Verlust
            $table->text('reason_text')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            // Kaufmännische Folge: lokaler Beleg ODER externe Belegnummer
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_reference')->nullable();
            $table->dateTime('invoiced_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rental_case_id', 'status'], 'rental_charges_org_case_idx');
        });

        Schema::create('rental_deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_case_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('requested');
            $table->decimal('amount', 12, 2);
            $table->decimal('retained_amount', 12, 2)->nullable();
            $table->text('retained_reason')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rental_case_id'], 'rental_deposits_org_case_idx');
        });

        Schema::create('rental_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Quellposten-Konvention der lokalen Faktura: FK-Spalte + Freigabe-
        // Hook in InvoiceItem::booted() (Muster time_entry_id/expense_id).
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('rental_charge_id')->nullable()->after('tour_id')
                ->constrained('rental_charges')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rental_charge_id');
        });
        Schema::dropIfExists('rental_report_snapshots');
        Schema::dropIfExists('rental_deposits');
        Schema::dropIfExists('rental_charges');
        Schema::dropIfExists('rental_accessory_items');
        Schema::dropIfExists('rental_condition_items');
        Schema::dropIfExists('rental_return_reports');
        Schema::dropIfExists('rental_handover_reports');
        Schema::dropIfExists('rental_reservations');
        Schema::dropIfExists('rental_case_assets');
        Schema::dropIfExists('rental_cases');
        Schema::dropIfExists('rental_profiles');
        Schema::dropIfExists('rental_rate_items');
        Schema::dropIfExists('rental_rate_cards');
    }
};
