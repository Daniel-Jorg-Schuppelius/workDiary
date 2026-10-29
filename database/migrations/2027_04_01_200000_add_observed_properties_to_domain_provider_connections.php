<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_200000_add_observed_properties_to_domain_provider_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Abgleich 2026-10-29 (Feature 083): Der Adapter merkt sich je Befehl die Eigenschaftsnamen,
// die der Anbieter tatsächlich liefert — der belegte Vertrag statt der Wissensdatenbank,
// deren Schreibweise vom Live-Gateway abweicht.
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_provider_connections', function (Blueprint $t): void {
            $t->json('observed_properties')->nullable()->after('capabilities');
        });
    }

    public function down(): void {
        Schema::table('domain_provider_connections', function (Blueprint $t): void {
            $t->dropColumn('observed_properties');
        });
    }
};
