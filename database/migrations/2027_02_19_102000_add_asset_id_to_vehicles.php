<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102000_add_asset_id_to_vehicles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fahrzeug-Fristen mit Sperrwirkung (Feature 138, MVP-703; Vollscan
 * 2026-08-23, H14): Fahrzeug ↔ Asset-Zuordnung, damit HU/AU/UVV/SP aus dem
 * Asset-Prüfwesen (Feature 075) auf Reservierungen durchgreifen (D12).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->after('organization_id')
                ->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });
    }
};
