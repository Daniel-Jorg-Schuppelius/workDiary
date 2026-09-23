<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_101500_add_free_invoice_source_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freie Rechnungen (Feature 160, MVP-857/858): Variantenbezug und
 * Artikelnummer-Schnappschuss am Posten, Herkunft einer abgerechneten
 * Fertigungsauslieferung (bleibt als Historie) sowie die aktive Reservierung
 * der Auslieferung durch genau einen Posten (Unique auf Datenbankebene).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('article_variant_id')->nullable()->after('article_id')->constrained('article_variants')->nullOnDelete();
            $table->string('article_number_snapshot', 64)->nullable()->after('article_variant_id');
            $table->foreignId('stock_delivery_id')->nullable()->after('rental_charge_id')->constrained('stock_deliveries')->nullOnDelete();
        });
        Schema::table('stock_deliveries', function (Blueprint $table): void {
            $table->foreignId('invoice_item_id')->nullable()->after('external_id')->unique()->constrained('invoice_items')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('stock_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_item_id');
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_delivery_id');
            $table->dropConstrainedForeignId('article_variant_id');
            $table->dropColumn('article_number_snapshot');
        });
    }
};
