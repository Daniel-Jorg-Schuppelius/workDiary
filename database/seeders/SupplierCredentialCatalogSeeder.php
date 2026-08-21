<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialCatalogSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier\SupplierCredentialType;
use Illuminate\Database\Seeder;

/**
 * Katalog der Pflichtnachweise (Feature 117, MVP-606), `organization_id NULL`.
 *
 * Die Gültigkeitsdauern sind **betriebliche Richtwerte**, keine
 * Rechtsauskunft: Welche Pflicht im Einzelfall gilt und wie lange ein
 * Nachweis trägt, entscheidet die ausstellende Stelle. Eine neue Nachweisart
 * ist damit Datenpflege, kein Release (Muster: Prüfmittel-Katalog).
 */
class SupplierCredentialCatalogSeeder extends Seeder {
    public const FRAME_VERSION = '2026-08';

    public function run(): void {
        $types = [
            [
                'code' => 'freistellung_48b',
                'name' => 'Freistellungsbescheinigung § 48b EStG',
                'default_validity_months' => 36,
                'warn_days_before' => 60,
                // Ohne Freistellung greift der 15-%-Steuerabzug — das ist ein
                // Zahlungsthema, kein Hinweis.
                'blocking_mode' => SupplierCredentialType::MODE_BLOCK,
                'description' => 'Bauabzugsteuer: ohne gültige Bescheinigung sind 15 % einzubehalten und abzuführen (Richtwert 3 Jahre).',
            ],
            [
                'code' => 'soka_bau',
                'name' => 'SOKA-BAU-Unbedenklichkeitsbescheinigung',
                'default_validity_months' => 6,
                'warn_days_before' => 30,
                'blocking_mode' => SupplierCredentialType::MODE_WARN,
                'description' => 'Sozialkassenverfahren des Baugewerbes; kurze Laufzeit, deshalb häufige Erneuerung.',
            ],
            [
                'code' => 'bg_unbedenklichkeit',
                'name' => 'Unbedenklichkeitsbescheinigung Berufsgenossenschaft',
                'default_validity_months' => 6,
                'warn_days_before' => 30,
                'blocking_mode' => SupplierCredentialType::MODE_WARN,
                'description' => 'Nachweis der abgeführten Beiträge zur gesetzlichen Unfallversicherung.',
            ],
            [
                'code' => 'milog_erklaerung',
                'name' => 'MiLoG-Erklärung (§ 13 MiLoG)',
                'default_validity_months' => 12,
                'warn_days_before' => 45,
                'blocking_mode' => SupplierCredentialType::MODE_WARN,
                'description' => 'Zusicherung der Mindestlohnzahlung inkl. Weitergabe an eigene Nachunternehmer (Auftraggeberhaftung).',
            ],
            [
                'code' => 'a1_entsendung',
                'name' => 'A1-Bescheinigung (Entsendung)',
                'default_validity_months' => 12,
                'warn_days_before' => 30,
                'blocking_mode' => SupplierCredentialType::MODE_WARN,
                'description' => 'Nachweis der Sozialversicherung im Heimatstaat bei Entsendung; je Einsatz zu prüfen.',
            ],
            [
                'code' => 'betriebshaftpflicht',
                'name' => 'Betriebshaftpflicht-Nachweis',
                'default_validity_months' => 12,
                'warn_days_before' => 30,
                'blocking_mode' => SupplierCredentialType::MODE_WARN,
                'description' => 'Versicherungsbestätigung mit Deckungssummen; üblich als Vergabevoraussetzung.',
            ],
        ];

        foreach ($types as $type) {
            SupplierCredentialType::query()->updateOrCreate(
                ['organization_id' => null, 'code' => $type['code']],
                array_merge($type, [
                    'frame_version' => self::FRAME_VERSION,
                    'is_active' => true,
                    // Pflicht ab Werk: Wer die Nachweise nicht braucht,
                    // deaktiviert den Typ — das ist die seltenere Entscheidung.
                    'is_required_default' => true,
                ]),
            );
        }
    }
}
