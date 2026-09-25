<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_200000_create_shipment_parcels_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-900: Packstücke einer Auslieferung mit Seriennummern je Packstück. */
return new class extends Migration {
    public function up(): void {
        Schema::create('shipment_parcels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_delivery_id')->constrained('stock_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('weight_grams');
            $table->unsignedSmallInteger('length_cm')->nullable();
            $table->unsignedSmallInteger('width_cm')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['stock_delivery_id', 'position'], 'shipment_parcels_delivery_pos_uniq');
        });

        Schema::create('shipment_parcel_serials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_parcel_id')->constrained('shipment_parcels')->cascadeOnDelete();
            $table->foreignId('stock_serial_id')->constrained('stock_serials')->cascadeOnDelete();
            $table->timestamps();
            // Jede Seriennummer liegt in genau einem Packstück.
            $table->unique('stock_serial_id', 'shipment_parcel_serials_serial_uniq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shipment_parcel_serials');
        Schema::dropIfExists('shipment_parcels');
    }
};
