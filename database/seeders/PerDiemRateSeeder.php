<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRateSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\PerDiemRate;
use Illuminate\Database\Seeder;

/**
 * Seedet die deutschen Verpflegungspauschalen nach BMF (EStG §9 Abs. 4a).
 *
 * Die Sätze sind seit 2020 unverändert (28 EUR Volltag, 14 EUR Anreise-/Abreise- bzw. Eintages-
 * Reisen > 8 h, 20 EUR Übernachtungspauschale ohne Beleg). Versionen werden trotzdem pro
 * Jahr angelegt, um spätere Änderungen sauber abbilden zu können.
 */
class PerDiemRateSeeder extends Seeder {
    public function run(): void {
        $versions = [
            ['valid_from' => '2024-01-01', 'valid_to' => '2024-12-31', 'source' => 'BMF 2023-11-21'],
            ['valid_from' => '2025-01-01', 'valid_to' => '2025-12-31', 'source' => 'BMF 2024-11-21'],
            ['valid_from' => '2026-01-01', 'valid_to' => null, 'source' => 'BMF 2025-11-21'],
        ];

        foreach ($versions as $version) {
            PerDiemRate::query()->updateOrCreate(
                ['country' => 'DE', 'valid_from' => $version['valid_from']],
                [
                    'valid_to' => $version['valid_to'],
                    'full_day_amount' => '28.00',
                    'partial_day_amount' => '14.00',
                    'overnight_amount' => '20.00',
                    'currency' => 'EUR',
                    'source' => $version['source'],
                ]
            );
        }
    }
}
