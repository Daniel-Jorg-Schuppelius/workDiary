<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MinimumWageSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\{MinimumWage, Organization};
use Illuminate\Database\Seeder;

/**
 * Befüllt jede Organisation mit der gesetzlichen Mindestlohn-Historie ihres
 * Landes (Org-Setting `payroll.country`, Default `DE`). Mindestlöhne sind
 * länderspezifisch — die Sätze liegen pro Organisation, geseedet wird der für
 * das Land der Organisation passende Verlauf.
 *
 * Idempotent: bestehende Sätze (Organisation + valid_from) werden NICHT
 * überschrieben, damit organisationseigene Anpassungen erhalten bleiben.
 */
class MinimumWageSeeder extends Seeder {
    /**
     * Gesetzlicher Mindestlohn (€/Std.) je Land und Stichtag. Quellen:
     * - DE: Mindestlohngesetz / Mindestlohnkommission (2026/2027 bereits beschlossen).
     * Weitere Länder können hier ergänzt werden.
     *
     * @var array<string, array<string, string>>
     */
    public const HISTORY = [
        'DE' => [
            '2015-01-01' => '8.50',
            '2017-01-01' => '8.84',
            '2019-01-01' => '9.19',
            '2020-01-01' => '9.35',
            '2021-01-01' => '9.50',
            '2021-07-01' => '9.60',
            '2022-01-01' => '9.82',
            '2022-07-01' => '10.45',
            '2022-10-01' => '12.00',
            '2024-01-01' => '12.41',
            '2025-01-01' => '12.82',
            '2026-01-01' => '13.90',
            '2027-01-01' => '14.60',
        ],
    ];

    public const DEFAULT_COUNTRY = 'DE';

    public function run(): void {
        Organization::query()->orderBy('id')->get()->each(function (Organization $org): void {
            self::seedOrganization($org);
        });
    }

    /** Legt die Historie für das Land der Organisation an (ohne Überschreiben). */
    public static function seedOrganization(Organization $organization): void {
        $country = strtoupper((string) ($organization->payroll('country') ?? self::DEFAULT_COUNTRY));
        $history = self::HISTORY[$country] ?? [];

        foreach ($history as $validFrom => $amount) {
            // whereDate-Abgleich, da valid_from mit Zeitanteil gespeichert wird;
            // bestehende (ggf. angepasste) Sätze nicht überschreiben.
            $exists = MinimumWage::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereDate('valid_from', $validFrom)
                ->exists();

            if (! $exists) {
                MinimumWage::query()->withoutGlobalScopes()->create([
                    'organization_id' => $organization->id,
                    'valid_from' => $validFrom,
                    'hourly_amount' => $amount,
                    'note' => __('Gesetzlicher Mindestlohn'),
                ]);
            }
        }
    }
}
