<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_28_100000_add_freight_and_line_note_to_purchase_orders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frachtkosten (Bestellung) und Positionsnotiz (Bestellzeile) — Datenquellen für
 * den UGL-Export (Feature 050): Fracht → POZ-Zuschlag (Typ 07), Positionsnotiz →
 * POT-Positionstext.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('freight_cost', 18, 4)->nullable()->after('currency');
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->string('note', 500)->nullable()->after('description');
        });
    }

    public function down(): void {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('freight_cost');
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn('note');
        });
    }
};
