<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_09_100000_add_ids_connect_to_supplier_catalog_sources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1071: IDS-Connect neben OCI — Protokoll und Kundennummer beim Großhändler je Bezugsquelle. */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->string('punchout_protocol', 8)->default('oci');
            $table->string('punchout_customer_number', 50)->nullable();
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->dropColumn(['punchout_protocol', 'punchout_customer_number']);
        });
    }
};
