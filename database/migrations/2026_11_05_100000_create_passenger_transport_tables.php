<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_05_100000_create_passenger_transport_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personenbeförderung (MVP-456, Branchenprofil Taxi/Mietwagen):
 *
 *  - `passenger_rides`: 1:1-Fachakte am `DiaryEntry` (Konzept §4) — damit
 *    Taxi-Daten nicht in allgemeine Auftragsfelder oder Freitext ausweichen.
 *    Adressen und Fahrgastkontakt sind `encrypted` (Konzept §11), Diagnosen
 *    gehören nicht hinein.
 *  - `passenger_concessions`: Konzession/Genehmigung je Betriebsart mit
 *    Behörde, Nummer, Pflichtfahr-/Tarifgebiet, Gültigkeit und Auflagen.
 *  - `passenger_vehicle_profiles`: Ordnungsnummer, zugelassene Betriebsarten,
 *    Barrierefreiheit sowie Taxameter-/Eich-/TSE-Status je Fahrzeug.
 *  - `passenger_fare_tariffs` (+ `_rules`): versionierte Tarifgebiete mit
 *    Grund-/Km-/Zeit-/Zuschlagsregeln und Festpreiskorridoren.
 *  - `passenger_shift_settlements`: Fahrer-/Schichtabrechnung mit getrennten
 *    Umsatzarten und offener Differenz bis zur begründeten Klärung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('passenger_concessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pconc_org_fk')->cascadeOnDelete();
            $table->string('operation_mode', 24);
            $table->string('authority', 160);
            $table->string('reference_no', 64);
            $table->string('business_seat', 200)->nullable();
            $table->string('service_area', 200)->nullable();
            $table->string('tariff_area', 200)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedSmallInteger('licensed_vehicles')->nullable();
            $table->text('conditions')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'pconc_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'operation_mode', 'reference_no'], 'pconc_org_mode_ref_unique');
            $table->index(['organization_id', 'valid_until'], 'pconc_org_valid_idx');
        });

        Schema::create('passenger_vehicle_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pvp_org_fk')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles', indexName: 'pvp_vehicle_fk')->cascadeOnDelete();
            $table->string('order_number', 32)->nullable();          // Ordnungsnummer der Behörde
            $table->json('operation_modes');                          // zugelassene Betriebsarten
            $table->unsignedTinyInteger('passenger_seats')->nullable();
            $table->unsignedTinyInteger('wheelchair_places')->default(0);
            $table->boolean('barrier_free')->default(false);
            $table->boolean('large_capacity')->default(false);
            // Geräte: Taxameter/Wegstreckenzähler + Eichung + TSE-Referenz.
            $table->string('meter_kind', 24)->nullable();             // taxameter | wegstreckenzaehler
            $table->string('meter_serial', 64)->nullable();
            $table->date('meter_calibrated_until')->nullable();
            $table->string('tse_reference', 120)->nullable();
            $table->date('bokraft_checked_until')->nullable();
            $table->date('hu_valid_until')->nullable();
            $table->timestamps();

            $table->unique('vehicle_id', 'pvp_vehicle_unique');
            $table->index('organization_id', 'pvp_org_idx');
        });

        Schema::create('passenger_fare_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pft_org_fk')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('tariff_area', 200)->nullable();
            $table->string('operation_mode', 24);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            // Behördlicher Tarif: Grundpreis + Km-/Zeitanteile (Cent-genau als
            // Dezimal, keine Rundung im Modell).
            $table->decimal('base_price', 10, 4)->default(0);
            $table->decimal('price_per_km', 10, 4)->default(0);
            $table->decimal('price_per_minute', 10, 4)->default(0);
            $table->decimal('min_price', 10, 4)->nullable();
            // Festpreiskorridor (§ 51 Abs. 4 PBefG): zulässige Abweichung.
            $table->decimal('fixed_price_min_percent', 6, 3)->nullable();
            $table->decimal('fixed_price_max_percent', 6, 3)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'operation_mode', 'valid_from'], 'pft_org_mode_from_idx');
        });

        Schema::create('passenger_fare_tariff_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pftr_org_fk')->cascadeOnDelete();
            $table->foreignId('tariff_id')->constrained('passenger_fare_tariffs', indexName: 'pftr_tariff_fk')->cascadeOnDelete();
            $table->string('code', 40);                               // z. B. nacht, gepaeck, grossraum
            $table->string('label', 160);
            $table->string('kind', 16);                               // surcharge | discount
            $table->decimal('amount', 10, 4)->nullable();
            $table->decimal('percent', 6, 3)->nullable();
            $table->json('conditions')->nullable();                   // Zeitfenster/Anforderungen
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tariff_id', 'code'], 'pftr_tariff_code_unique');
            $table->index('organization_id', 'pftr_org_idx');
        });

        Schema::create('passenger_rides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pride_org_fk')->cascadeOnDelete();
            $table->foreignId('diary_entry_id')->constrained('diary_entries', indexName: 'pride_diary_fk')->cascadeOnDelete();

            // Betriebsart wird bei Annahme eingefroren (Konzept §3).
            $table->string('operation_mode', 24);
            $table->string('order_channel', 24);
            $table->string('status', 24)->default('requested');
            $table->string('mediator_reference', 120)->nullable();
            $table->string('mediator_plugin', 64)->nullable();

            // Lebenszyklus-Zeitstempel + Akteure.
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users', indexName: 'pride_acceptor_fk')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('pickup_started_at')->nullable();
            $table->timestamp('waiting_started_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('closing_reason', 32)->nullable();         // storno/no-show/abbruch
            $table->text('closing_note')->nullable();

            // Orte + Zeitfenster: adressbezogene Daten verschlüsselt (§11).
            $table->text('pickup_address')->nullable();
            $table->text('destination_address')->nullable();
            $table->json('waypoints')->nullable();
            $table->boolean('destination_open')->default(false);
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();

            // Fahrgastanforderungen (datensparsam, keine Diagnosen).
            $table->unsignedTinyInteger('passenger_count')->default(1);
            $table->unsignedTinyInteger('luggage_count')->default(0);
            $table->unsignedTinyInteger('child_seats')->default(0);
            $table->boolean('wheelchair')->default(false);
            $table->boolean('animal')->default(false);
            $table->boolean('barrier_free_required')->default(false);
            $table->text('passenger_name')->nullable();
            $table->text('passenger_contact')->nullable();

            // Zuordnung + eingefrorene Snapshots.
            $table->foreignId('driver_user_id')->nullable()->constrained('users', indexName: 'pride_driver_fk')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles', indexName: 'pride_vehicle_fk')->nullOnDelete();
            $table->foreignId('concession_id')->nullable()->constrained('passenger_concessions', indexName: 'pride_conc_fk')->nullOnDelete();
            $table->json('assignment_snapshot')->nullable();          // Fahrer/Fahrzeug/Konzession/Geräte

            // Strecke/Zeiten (soweit vom führenden Gerät geliefert).
            $table->unsignedInteger('odometer_start_km')->nullable();
            $table->unsignedInteger('odometer_end_km')->nullable();
            $table->decimal('occupied_km', 10, 2)->nullable();
            $table->decimal('empty_km', 10, 2)->nullable();
            $table->unsignedInteger('waiting_seconds')->default(0);
            $table->text('route_note')->nullable();                   // vereinbarter abweichender Fahrweg

            // Preis: geplanter Snapshot getrennt vom Gerätewert (Konzept §8).
            $table->string('price_kind', 16)->nullable();
            $table->foreignId('tariff_id')->nullable()->constrained('passenger_fare_tariffs', indexName: 'pride_tariff_fk')->nullOnDelete();
            $table->json('fare_snapshot')->nullable();
            $table->decimal('planned_net', 12, 2)->nullable();
            $table->decimal('meter_net', 12, 2)->nullable();          // tatsächlicher Geräte-/Providerwert
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->json('tax_context')->nullable();                  // TaxResolver-Snapshot (Phase 23)
            $table->string('payment_method', 24)->nullable();
            $table->string('settlement_status', 24)->default('open');
            $table->foreignId('shift_settlement_id')->nullable();

            // Mietwagen: Eingangsnachweis + Rückkehr/Folgeauftrag (§ 49 Abs. 4).
            $table->timestamp('order_received_at')->nullable();
            $table->string('order_receipt_reference', 120)->nullable();
            $table->timestamp('returned_to_base_at')->nullable();
            $table->foreignId('follow_up_ride_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'pride_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('diary_entry_id', 'pride_diary_unique');
            $table->unique(['organization_id', 'mediator_plugin', 'mediator_reference'], 'pride_mediator_unique');
            $table->index(['organization_id', 'status'], 'pride_org_status_idx');
            $table->index(['organization_id', 'operation_mode'], 'pride_org_mode_idx');
            $table->index(['driver_user_id', 'status'], 'pride_driver_status_idx');
        });

        Schema::create('passenger_shift_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pss_org_fk')->cascadeOnDelete();
            $table->foreignId('driver_user_id')->constrained('users', indexName: 'pss_driver_fk')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles', indexName: 'pss_vehicle_fk')->nullOnDelete();
            $table->date('shift_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // Getrennte Umsatzarten (Konzept §8): Geräteumsatz vs. Zahlarten.
            $table->decimal('meter_total', 12, 2)->default(0);
            $table->decimal('cash_total', 12, 2)->default(0);
            $table->decimal('card_total', 12, 2)->default(0);
            $table->decimal('voucher_total', 12, 2)->default(0);
            $table->decimal('invoice_total', 12, 2)->default(0);
            $table->decimal('mediator_total', 12, 2)->default(0);
            $table->decimal('tip_total', 12, 2)->default(0);
            $table->decimal('cancelled_total', 12, 2)->default(0);
            $table->decimal('difference', 12, 2)->default(0);
            $table->text('difference_reason')->nullable();
            $table->string('status', 16)->default('open');            // open | balanced | disputed
            $table->foreignId('closed_by')->nullable()->constrained('users', indexName: 'pss_closer_fk')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'driver_user_id', 'shift_date'], 'pss_org_driver_date_unique');
            $table->index(['organization_id', 'status'], 'pss_org_status_idx');
        });

        // Nachträglich: Selbstreferenz + Schichtbezug (Tabellen existieren jetzt).
        Schema::table('passenger_rides', function (Blueprint $table): void {
            $table->foreign('shift_settlement_id', 'pride_shift_fk')
                ->references('id')->on('passenger_shift_settlements')->nullOnDelete();
            $table->foreign('follow_up_ride_id', 'pride_followup_fk')
                ->references('id')->on('passenger_rides')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('passenger_rides', function (Blueprint $table): void {
            $table->dropForeign('pride_shift_fk');
            $table->dropForeign('pride_followup_fk');
        });
        Schema::dropIfExists('passenger_shift_settlements');
        Schema::dropIfExists('passenger_rides');
        Schema::dropIfExists('passenger_fare_tariff_rules');
        Schema::dropIfExists('passenger_fare_tariffs');
        Schema::dropIfExists('passenger_vehicle_profiles');
        Schema::dropIfExists('passenger_concessions');
    }
};
