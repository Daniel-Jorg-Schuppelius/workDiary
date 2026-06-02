<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120300_add_service_date_to_invoice_items_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leistungsdatum je Rechnungsposition (§14 UStG). Spannt die Rechnung mehrere
 * Tage, wird daraus der Leistungszeitraum abgeleitet und jede Position trägt
 * ihr eigenes Leistungsdatum. Liegt alles an einem Tag, genügt das
 * Leistungsdatum im Rechnungskopf.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->date('service_date')->nullable()->after('time_entry_id');
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn('service_date');
        });
    }
};
