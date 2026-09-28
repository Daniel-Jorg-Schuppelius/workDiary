<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_191000_add_private_use_fields_to_vehicles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-993: Bruttolistenpreis und Entfernung Wohnung–Arbeit je Fahrzeug, sonstige Jahreskosten für den 1-%-Vergleich. */
return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->decimal('list_price_amount', 12, 2)->nullable()->after('wltp_consumption');
            $table->char('currency', 3)->nullable()->after('list_price_amount');
            $table->unsignedSmallInteger('commute_distance_km')->nullable()->after('currency');
        });

        Schema::create('vehicle_annual_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'veh_cost_org_fk')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles', indexName: 'veh_cost_vehicle_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('cost_amount', 12, 2);
            $table->char('currency', 3);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'veh_cost_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vehicle_id', 'year'], 'veh_cost_vehicle_year_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('vehicle_annual_costs');
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn(['list_price_amount', 'currency', 'commute_distance_km']);
        });
    }
};
