<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_09_160000_add_unparsed_records_to_domain_dns_zone_projections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoneneinträge, die die App nicht deuten kann (unbekannter Typ), bleiben als
 * Rohzeile an der Zone stehen. Ohne sie verschwänden sie beim Ersetzen der
 * Zone beim Anbieter — die Voraussetzung für „Zone ersetzen".
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_dns_zone_projections', function (Blueprint $table): void {
            $table->json('unparsed_records')->nullable()->after('soa');
        });
    }

    public function down(): void {
        Schema::table('domain_dns_zone_projections', function (Blueprint $table): void {
            $table->dropColumn('unparsed_records');
        });
    }
};
