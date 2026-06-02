<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120700_add_foreign_customer_id_to_assets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optionaler Endkunden-Bezug eines Assets. Assets, die einer Firma
 * (customer_id) gehören, können einem Fremdkunden (Endkunde der Firma)
 * zugeordnet sein; organisationseigene Assets bleiben null.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('foreign_customer_id')->nullable()->after('customer_id')
                ->constrained('foreign_customers')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('foreign_customer_id');
        });
    }
};
