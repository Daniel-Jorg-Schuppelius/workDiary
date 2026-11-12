<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_320000_clear_undocumented_domain_renewal_mode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * `RENEWONCE` steht nicht im Handbuch des Anbieters (2.3.2.10, StatusDomain:
 * nur AUTORENEW, AUTOEXPIRE, AUTODELETE) und ist kein Fall mehr von
 * `DomainRenewalMode` (MVP-1625). Ein so gespeicherter Wert gilt als unbekannt;
 * der nächste `domain:sync` liest den tatsächlichen Modus beim Anbieter.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('domain_projections')) {
            return;
        }

        DB::table('domain_projections')->where('renewal_mode', 'RENEWONCE')->update(['renewal_mode' => null]);
    }

    public function down(): void {
        // Der alte Wert war nie ein Zustand beim Anbieter.
    }
};
