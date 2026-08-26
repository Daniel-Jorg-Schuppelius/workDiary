<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102300_add_bins_and_kind_to_warehouses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lagerplätze, Fahrzeuglager und Bezug (Feature 048, MVP-706; Vollscan
 * 2026-08-23, H15): `warehouses.kind` (fixed/vehicle/site/team) mit
 * optionalem Bezug auf Standort/Fahrzeug/Team (SET NULL — das Lager bleibt),
 * `warehouse_bins` als optionale Plätze je Lager. Der Ledger referenziert den
 * Platz mit RESTRICT (Nachweis), Reservierungen mit SET NULL.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->string('kind', 16)->default('fixed')->after('name');
            $table->foreignId('site_id')->nullable()->after('location_note')
                ->constrained('sites', indexName: 'warehouses_site_fk')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->after('site_id')
                ->constrained('vehicles', indexName: 'warehouses_vehicle_fk')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('vehicle_id')
                ->constrained('teams', indexName: 'warehouses_team_fk')->nullOnDelete();
        });

        Schema::create('warehouse_bins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'wh_bins_org_fk')->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', indexName: 'wh_bins_wh_fk')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('blocked')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code'], 'wh_bins_wh_code_unique');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('bin_id')->nullable()->after('warehouse_id')
                ->constrained('warehouse_bins', indexName: 'stock_mov_bin_fk')->restrictOnDelete();
            $table->index(['warehouse_id', 'bin_id'], 'stock_mov_wh_bin_idx');
        });

        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->foreignId('bin_id')->nullable()->after('warehouse_id')
                ->constrained('warehouse_bins', indexName: 'stock_resv_bin_fk')->nullOnDelete();
            $table->index(['warehouse_id', 'bin_id'], 'stock_resv_wh_bin_idx');
        });
    }

    public function down(): void {
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropForeign('stock_resv_bin_fk');
            $table->dropIndex('stock_resv_wh_bin_idx');
            $table->dropColumn('bin_id');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign('stock_mov_bin_fk');
            $table->dropIndex('stock_mov_wh_bin_idx');
            $table->dropColumn('bin_id');
        });
        Schema::dropIfExists('warehouse_bins');
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropForeign('warehouses_site_fk');
            $table->dropForeign('warehouses_vehicle_fk');
            $table->dropForeign('warehouses_team_fk');
            $table->dropColumn(['kind', 'site_id', 'vehicle_id', 'team_id']);
        });
    }
};
