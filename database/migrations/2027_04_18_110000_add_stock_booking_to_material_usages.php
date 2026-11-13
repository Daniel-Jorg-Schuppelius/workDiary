<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_110000_add_stock_booking_to_material_usages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1679: Der Materialverbrauch eines lagergeführten Artikels bucht den
 * Bestand. Am Verbrauch steht, ob und aus welchem Lager gebucht wurde; die
 * Bewegungen selbst tragen den Verbrauch als Quelle. Bestandszeilen bleiben
 * leer — sie haben nie gebucht.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('material_usages', 'stock_status')) {
            return;
        }

        Schema::table('material_usages', function (Blueprint $table): void {
            $table->string('stock_status', 20)->nullable()->after('billed');
            $table->foreignId('stock_warehouse_id')->nullable()->after('stock_status')
                ->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('material_usages', 'stock_status')) {
            return;
        }

        Schema::table('material_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_warehouse_id');
            $table->dropColumn('stock_status');
        });
    }
};
