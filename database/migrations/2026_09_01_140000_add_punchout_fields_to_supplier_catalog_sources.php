<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_01_140000_add_punchout_fields_to_supplier_catalog_sources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aktiver OCI-Punchout-Absprung in den Lieferanten-Shop (Feature 050,
 * MVP-096): Shop-Login-URL und Zugangsdaten je Katalogquelle. Das Passwort
 * liegt wie remote_password verschlüsselt at-rest (encrypted-Cast, APP_KEY).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->string('punchout_url', 1024)->nullable()->after('remote_password');
            $table->string('punchout_username', 191)->nullable()->after('punchout_url');
            $table->text('punchout_password')->nullable()->after('punchout_username');
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->dropColumn(['punchout_url', 'punchout_username', 'punchout_password']);
        });
    }
};
