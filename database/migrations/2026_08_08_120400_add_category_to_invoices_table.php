<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120400_add_category_to_invoices_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rechnungs-Kategorie: 'service' (Leistung, Leistungsdatum/-zeitraum) oder
 * 'material' (Lieferung, Lieferdatum/-zeitraum). Material wird getrennt
 * abgerechnet, weil die Datumsart (Liefer- statt Leistungsdatum) abweicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('category', 20)->default('service')->after('type');
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
