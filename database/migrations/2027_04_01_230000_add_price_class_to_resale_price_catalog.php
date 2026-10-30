<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_230000_add_price_class_to_resale_price_catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preisstaffel je Katalogzeile (Feature 185, 2026-10-30): das Domainportal führt
 * seine Preise in Benutzerklassen (STANDARD, RESELLER, …), der Katalog spiegelt
 * je Klasse und Zone Einkauf und Staffelpreis. '' = ohne Staffel (Importlisten).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('resale_price_catalog', function (Blueprint $table): void {
            $table->string('price_class', 64)->default('')->after('product');
            // Erst der neue Index, dann der alte weg: der Fremdschlüssel auf organization_id braucht durchgehend einen
            // Index mit dieser Spalte vorn (MySQL 1553 „needed in a foreign key constraint“).
            $table->unique(['organization_id', 'provider', 'product', 'price_class', 'term_months', 'interval', 'valid_from'], 'resale_prices_class_uq');
            $table->dropUnique('resale_prices_uq');
        });
    }

    public function down(): void {
        Schema::table('resale_price_catalog', function (Blueprint $table): void {
            $table->unique(['organization_id', 'provider', 'product', 'term_months', 'interval', 'valid_from'], 'resale_prices_uq');
            $table->dropUnique('resale_prices_class_uq');
            $table->dropColumn('price_class');
        });
    }
};
