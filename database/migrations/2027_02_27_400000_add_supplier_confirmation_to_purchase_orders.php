<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_400000_add_supplier_confirmation_to_purchase_orders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-964: Auftragsbestätigung des Lieferanten (openTRANS ORDERRESPONSE) an der Bestellung. */
return new class extends Migration {
    public function up(): void {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->timestamp('supplier_confirmed_at')->nullable();
            $table->string('supplier_order_ref', 64)->nullable();
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('confirmed_qty', 14, 4)->nullable();
            $table->decimal('confirmed_unit_price', 14, 4)->nullable();
            $table->date('confirmed_delivery_on')->nullable();
        });
    }

    public function down(): void {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn(['confirmed_qty', 'confirmed_unit_price', 'confirmed_delivery_on']);
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn(['supplier_confirmed_at', 'supplier_order_ref']);
        });
    }
};
