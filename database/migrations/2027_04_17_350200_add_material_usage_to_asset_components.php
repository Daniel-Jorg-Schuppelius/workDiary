<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_350200_add_material_usage_to_asset_components.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1620 (Feature 118): Ein aus dem Materialverbrauch übernommenes Teil merkt
 * sich seine Herkunft — so bietet die Stückliste jeden Verbrauch nur einmal an.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('asset_components', 'material_usage_id')) {
            return;
        }

        Schema::table('asset_components', function (Blueprint $table): void {
            $table->foreignId('material_usage_id')->nullable()->after('stock_serial_id')
                ->constrained('material_usages')->nullOnDelete();
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('asset_components', 'material_usage_id')) {
            return;
        }

        Schema::table('asset_components', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('material_usage_id');
        });
    }
};
