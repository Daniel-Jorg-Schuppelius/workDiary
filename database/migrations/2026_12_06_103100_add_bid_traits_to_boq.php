<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103100_add_bid_traits_to_boq.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 108, MVP-567/569: Angebotsmerkmale einer Position. „Nicht angeboten"
 * ist dabei etwas anderes als der Preis 0,00 — ava-sign prüft beim Reimport
 * genau, ob jede Position entweder bepreist oder abgelehnt ist. Dazu Nachlass,
 * USt, freie Menge, Stundenlohn, Bieterkommentar und das Nebenangebotsmerkmal
 * sowie die Summen mit Nachlass am LV und je Abschnitt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->boolean('not_offered')->default(false)->after('unit_price_components');
            $table->boolean('not_applicable')->default(false)->after('not_offered');
            $table->boolean('free_quantity')->default(false)->after('not_applicable');
            $table->boolean('hourly_item')->default(false)->after('free_quantity');
            $table->decimal('discount_percent', 9, 6)->nullable()->after('hourly_item');
            $table->decimal('vat_rate', 5, 2)->nullable()->after('discount_percent');
            $table->text('bidder_comment')->nullable()->after('vat_rate');
            $table->string('alternative_bid_status', 20)->nullable()->after('bidder_comment');
        });

        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->json('totals')->nullable()->after('up_components');
        });

        Schema::table('boq_sections', function (Blueprint $table): void {
            $table->json('totals')->nullable()->after('label');
        });
    }

    public function down(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->dropColumn(['not_offered', 'not_applicable', 'free_quantity', 'hourly_item', 'discount_percent', 'vat_rate', 'bidder_comment', 'alternative_bid_status']);
        });

        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->dropColumn('totals');
        });

        Schema::table('boq_sections', function (Blueprint $table): void {
            $table->dropColumn('totals');
        });
    }
};
