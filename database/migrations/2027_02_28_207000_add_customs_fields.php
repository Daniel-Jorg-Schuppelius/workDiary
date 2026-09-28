<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_207000_add_customs_fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1007: Zollangaben am Artikel und Versandgrund der Auslieferung. */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('customs_tariff_number', 11)->nullable()->after('gtin');
            $table->char('origin_country', 2)->nullable()->after('customs_tariff_number');
            $table->decimal('net_weight_kg', 10, 4)->nullable()->after('origin_country');
        });
        Schema::table('stock_deliveries', function (Blueprint $table): void {
            $table->string('export_reason', 20)->nullable()->after('currency');
        });
    }

    public function down(): void {
        Schema::table('stock_deliveries', function (Blueprint $table): void {
            $table->dropColumn('export_reason');
        });
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['customs_tariff_number', 'origin_country', 'net_weight_kg']);
        });
    }
};
