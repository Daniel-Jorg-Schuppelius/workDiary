<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_07_120200_add_foreign_customer_id_to_invoices_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datensatz-Marker „Rechnung je Fremdkunde": Die Rechnung bleibt an die Firma
 * (customer_id) adressiert, kann aber optional auf einen Endkunden eingegrenzt
 * sein (Positionen nur dieses Fremdkunden). Dient Auswertung/Filter.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('foreign_customer_id')->nullable()->after('project_id')
                ->constrained('foreign_customers')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('foreign_customer_id');
        });
    }
};
