<?php
/*
 * Created on   : Sat Aug 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_01_100000_add_owner_handle_to_domain_projections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registrant-/Owner-Contact-Handle je Domain (Feature 083): erlaubt es, den
 * Firmennamen des Registranten aus der Contact-Projektion für Kundenvorschläge
 * heranzuziehen. Rein optional — bleibt null, wenn der Provider das Feld nicht
 * liefert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_projections', function (Blueprint $table): void {
            $table->string('owner_handle', 190)->nullable()->after('reseller_account_id');
        });
    }

    public function down(): void {
        Schema::table('domain_projections', function (Blueprint $table): void {
            $table->dropColumn('owner_handle');
        });
    }
};
