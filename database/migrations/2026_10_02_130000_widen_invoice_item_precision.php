<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_02_130000_widen_invoice_item_precision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whitebox 2026-07-10 (G7/G8): Rechnungspositionen übernehmen Mengen aus
 * MaterialUsage (3 NK) und km-Sätze aus dem TravelChargeService (4 NK) —
 * die bisherigen decimal(10,2)-Spalten kappten das still, wodurch der
 * Rechnungsbetrag vom Beleg abwich (2,345 × 10,1234 € → 23,74 € am Beleg,
 * 23,78 € auf der Rechnung). Der Zeilenbetrag (amount) bleibt bewusst 2 NK.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 3)->default(1)->change();
            $table->decimal('unit_price', 12, 4)->default(0)->change();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->decimal('quantity', 10, 2)->default(1)->change();
            $table->decimal('unit_price', 10, 2)->default(0)->change();
        });
    }
};
