<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_02_100000_add_lexoffice_article_fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergänzt den lokalen Artikel-Cache um weitere Felder, die Lexoffice für
 * Produkte/Leistungen liefert: GTIN/Barcode, interne Notiz, Brutto-Einzelpreis
 * sowie die führende Preisangabe (NET|GROSS).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->string('gtin', 32)->nullable()->after('article_number');
            $table->text('note')->nullable()->after('description');
            $table->decimal('gross_unit_price', 12, 4)->nullable()->after('net_unit_price');
            $table->string('leading_price', 8)->nullable()->after('vat_rate'); // NET|GROSS
        });
    }

    public function down(): void {
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->dropColumn(['gtin', 'note', 'gross_unit_price', 'leading_price']);
        });
    }
};
