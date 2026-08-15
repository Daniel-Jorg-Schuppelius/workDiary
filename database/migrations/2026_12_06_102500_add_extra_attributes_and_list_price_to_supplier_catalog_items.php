<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102500_add_extra_attributes_and_list_price_to_supplier_catalog_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalogartikel-Zusatzdaten (Feature 050, MVP-541): frei gemappte Attribute
 * (z. B. Vertragslaufzeit/Zahlungsintervall einer Dienstleistungs-Preisliste,
 * werden bei der Artikel-Übernahme zu Varianten-Optionen) und der
 * Hersteller-UVP/Listenpreis als VK-Vorschlagsbasis.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->decimal('list_price', 18, 4)->nullable()->after('purchase_price');
            $table->json('extra_attributes')->nullable()->after('lead_time_days');
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->dropColumn(['list_price', 'extra_attributes']);
        });
    }
};
